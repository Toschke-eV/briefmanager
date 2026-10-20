<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCA\BriefManager\Db\Letter;
use OCA\BriefManager\Db\PostageOrder;
use OCA\BriefManager\Db\PostageOrderMapper;
use OCP\DB\Exception as DbException;
use OCP\IUserSession;

/**
 * Preview, Preisbestätigung und idempotenter Kauf von Internetmarken.
 * Preis ausschließlich aus Products API; Live-Kauf standardmäßig aus.
 */
class InternetmarkePurchaseService {
	public const REGISTERED_KEYWORDS = [
		'einschreiben',
		'einwurf',
		'rückschein',
		'rueckschein',
		'registered',
	];

	/** Status, bei denen kein erneuter Checkout ausgelöst wird. */
	private const TERMINAL_OR_LOCKED = [
		'purchased',
		'preview_ok',
		'uncertain',
		'pending',
	];

	public function __construct(
		private InternetmarkeClient $client,
		private InternetmarkeCredentialStore $credentials,
		private PostageOrderMapper $orders,
		private LetterService $letters,
		private StampService $stamps,
		private IUserSession $session,
	) {
	}

	/**
	 * Öffentliche Statusinfo ohne Secrets.
	 *
	 * @return array<string, mixed>
	 */
	public function status(): array {
		$app = $this->credentials->getAppCredentials();
		$user = $this->credentials->getUserPortokasse();
		$health = ['ok' => false, 'error' => 'nicht geprüft'];
		try {
			$health = $this->client->health();
		} catch (\Throwable $e) {
			$health = ['ok' => false, 'error' => $e->getMessage()];
		}
		return [
			'health' => $health,
			'blockers' => $this->credentials->missingRequirements(),
			'allow_live_purchase' => $this->credentials->allowLivePurchase(),
			'app_configured' => $app['configured'],
			'portokasse_configured' => $user['configured'],
			'portokasse_username' => $user['username'] !== '' ? $user['username'] : null,
			// niemals client_secret / password
		];
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function catalog(bool $registeredOnly = false): array {
		$raw = $this->client->listProducts();
		$out = [];
		foreach ($raw as $row) {
			$normalized = $this->normalizeProductRow($row);
			if ($normalized === null) {
				continue;
			}
			if ($registeredOnly && !$normalized['is_registered']) {
				continue;
			}
			unset($normalized['raw']);
			$out[] = $normalized;
		}
		return $out;
	}

	/**
	 * Vertrauenswürdiger Produktpreis aus Products API.
	 *
	 * @return array{product_code:int, label:string, price_cents:int, price_eur:string, is_registered:bool}|null
	 */
	public function resolveTrustedProduct(int $productCode): ?array {
		if ($productCode < 1) {
			return null;
		}
		foreach ($this->client->listProducts() as $row) {
			$normalized = $this->normalizeProductRow($row);
			if ($normalized === null || $normalized['product_code'] !== $productCode) {
				continue;
			}
			if ($normalized['price_cents'] === null || $normalized['price_cents'] < 1) {
				throw new \RuntimeException('Products API liefert keinen gültigen Preis für Produkt ' . $productCode . '.');
			}
			return [
				'product_code' => $normalized['product_code'],
				'label' => $normalized['label'],
				'price_cents' => (int)$normalized['price_cents'],
				'price_eur' => (string)$normalized['price_eur'],
				'is_registered' => $normalized['is_registered'],
			];
		}
		return null;
	}

	/**
	 * @param array{product_code:int, voucher_layout?:string, confirm_price_cents?:int, idempotency_key:string, mode?:string, registered_only?:bool} $opts
	 */
	public function quoteOrPurchase(Letter $letter, array $opts): PostageOrder {
		$userId = $this->session->getUser()?->getUID();
		if ($userId === null) {
			throw new \RuntimeException('Nicht angemeldet');
		}
		if (!$this->letters->canEdit($letter)) {
			throw new \RuntimeException('Kein Schreibrecht für diesen Brief.');
		}

		$key = trim((string)($opts['idempotency_key'] ?? ''));
		if ($key === '' || strlen($key) < 8) {
			throw new \InvalidArgumentException('idempotency_key fehlt (mind. 8 Zeichen).');
		}

		$productCode = (int)($opts['product_code'] ?? 0);
		$layout = strtoupper((string)($opts['voucher_layout'] ?? 'FRANKING_ZONE'));
		if (!in_array($layout, ['FRANKING_ZONE', 'ADDRESS_ZONE'], true)) {
			$layout = 'FRANKING_ZONE';
		}
		$mode = (string)($opts['mode'] ?? 'preview');
		if (!in_array($mode, ['preview', 'purchase'], true)) {
			$mode = 'preview';
		}
		$letterId = $letter->getId();

		// Mapper filtert Idempotenz-Key global (Unique), nicht nach UID –
		// daher Bindung vor jeder Rückgabe/Serialisierung prüfen.
		$existing = $this->orders->findByIdempotencyKey($key);
		if ($existing !== null) {
			return $this->acceptExistingBoundOrder($existing, $userId, $letterId, $productCode, $mode);
		}

		$trusted = $this->resolveTrustedProduct($productCode);
		if ($trusted === null) {
			throw new \InvalidArgumentException('Produktcode ' . $productCode . ' nicht in der Products API gefunden.');
		}
		if (!empty($opts['registered_only']) && !$trusted['is_registered']) {
			throw new \InvalidArgumentException('Nur API-bestätigte Einschreiben-Produkte erlaubt.');
		}

		$priceCents = $trusted['price_cents'];

		$confirm = (int)($opts['confirm_price_cents'] ?? -1);
		if ($mode === 'purchase' || array_key_exists('confirm_price_cents', $opts)) {
			if ($confirm !== $priceCents) {
				throw new \InvalidArgumentException(
					'Preisbestätigung weicht vom API-Preis ab (erwartet ' . $priceCents . ' Cent / ' . $trusted['price_eur'] . '). Client-Preise werden ignoriert.'
				);
			}
		}

		$now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
		$order = new PostageOrder();
		$order->setUserId($userId);
		$order->setLetterId($letterId);
		$order->setIdempotencyKey($key);
		$order->setProductCode($productCode);
		$order->setProductLabel($trusted['label']);
		$order->setPriceCents($priceCents);
		$order->setVoucherLayout($layout);
		$order->setStatus('pending');
		$order->setMode($mode);
		$order->setCreatedAt($now);
		$order->setUpdatedAt($now);

		try {
			$order = $this->orders->insert($order);
		} catch (DbException $e) {
			// Parallelrequest: Unique Index auf idempotency_key
			$race = $this->orders->findByIdempotencyKey($key);
			if ($race !== null) {
				return $this->acceptExistingBoundOrder($race, $userId, $letterId, $productCode, $mode);
			}
			throw $e;
		}

		try {
			if ($mode === 'purchase') {
				if (!$this->credentials->allowLivePurchase()) {
					throw new \RuntimeException(
						'Live-Kauf ist deaktiviert. Nur Preview erlaubt. Kein Porto wurde gekauft.'
					);
				}
				$auth = $this->client->authenticate();
				$body = $this->buildCheckoutBody($letter, $productCode, $layout, $priceCents, $key);
				try {
					$result = $this->client->checkoutPdf($auth['access_token'], $body);
				} catch (\Throwable $checkoutError) {
					$order->setStatus('uncertain');
					$order->setErrorMessage(
						'Kaufausgang unklar (kein automatischer Wiederholungskauf): ' . mb_substr($checkoutError->getMessage(), 0, 1800)
					);
					$order->setUpdatedAt((new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
					$this->orders->update($order);
					throw new \RuntimeException($order->getErrorMessage() ?? 'Kaufausgang unklar');
				}
				// Checkout war erfolgreich: Nachfehler (Download/DB) dürfen nicht „error“ setzen
				// und keinen erneuten Kauf auslösen.
				$this->applyCheckoutResult($order, $letter, $result, true);
			} else {
				$body = [
					'type' => 'AppShoppingCartPreviewPDFRequest',
					'productCode' => $productCode,
					'voucherLayout' => $layout,
				];
				$result = $this->client->previewPdf($body);
				$order->setStatus('preview_ok');
				$order->setResponseJson(json_encode($result, JSON_UNESCAPED_UNICODE) ?: null);
				$order->setUpdatedAt((new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
				$order = $this->orders->update($order);
			}
		} catch (\Throwable $e) {
			$statusNow = $order->getStatus();
			// Nach erfolgreichem Checkout: purchased/uncertain beibehalten, kein error, kein Re-Kauf
			if (in_array($statusNow, ['uncertain', 'purchased'], true)) {
				throw $e;
			}
			$order->setStatus('error');
			$order->setErrorMessage(mb_substr($e->getMessage(), 0, 2000));
			$order->setUpdatedAt((new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
			$this->orders->update($order);
			throw $e;
		}

		return $order;
	}

	/**
	 * Bestehenden Auftrag nur zurückgeben, wenn UID/Brief/Produkt/Modus passen.
	 * Fremde Keys: Ablehnung ohne Auftragsdaten.
	 */
	private function acceptExistingBoundOrder(
		PostageOrder $existing,
		string $userId,
		int $letterId,
		int $productCode,
		string $mode,
	): PostageOrder {
		PostageIdempotencyGuard::assertBindingMatches(
			$userId,
			$letterId,
			$productCode,
			$mode,
			$existing->getUserId(),
			$existing->getLetterId(),
			$existing->getProductCode(),
			$existing->getMode(),
		);
		return $existing;
	}

	/**
	 * @param array<string, mixed> $row
	 * @return array{product_code:int, label:string, price_cents:?int, price_eur:?string, is_registered:bool, raw:array}|null
	 */
	private function normalizeProductRow(array $row): ?array {
		$label = (string)($row['name'] ?? $row['productName'] ?? $row['description'] ?? '');
		$code = (int)($row['extProductId'] ?? $row['productCode'] ?? $row['id'] ?? 0);
		if ($code < 1) {
			return null;
		}
		$price = $row['grossPrice'] ?? $row['grossprice'] ?? $row['price'] ?? null;
		$priceCents = null;
		if (is_numeric($price)) {
			$asFloat = (float)$price;
			if ($asFloat > 0 && $asFloat < 50) {
				$priceCents = (int)round($asFloat * 100);
			} elseif ($asFloat >= 50 && $asFloat < 1000 && abs($asFloat - (int)$asFloat) < 0.001) {
				$priceCents = (int)round($asFloat);
			} else {
				$priceCents = (int)round($asFloat * 100);
			}
		}
		$hay = mb_strtolower($label . ' ' . json_encode($row, JSON_UNESCAPED_UNICODE));
		$isRegistered = false;
		foreach (self::REGISTERED_KEYWORDS as $kw) {
			if (str_contains($hay, $kw)) {
				$isRegistered = true;
				break;
			}
		}
		return [
			'product_code' => $code,
			'label' => $label !== '' ? $label : ('Produkt ' . $code),
			'price_cents' => $priceCents,
			'price_eur' => $priceCents !== null ? number_format($priceCents / 100, 2, ',', '.') . ' €' : null,
			'is_registered' => $isRegistered,
			'raw' => $row,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function buildCheckoutBody(Letter $letter, int $productCode, string $layout, int $totalCents, string $shopOrderId): array {
		$addr = $this->parseRecipient($letter->getEmpfaenger());
		$position = [
			'positionType' => 'AppShoppingCartPDFPosition',
			'productCode' => $productCode,
			'voucherLayout' => $layout,
			'position' => [
				'page' => 1,
				'labelX' => 0,
				'labelY' => 0,
			],
		];
		if ($addr['street'] !== '' || $addr['city'] !== '') {
			$position['address'] = [
				'sender' => null,
				'receiver' => [
					'name' => $addr['name'],
					'additionalName' => $addr['additional'],
					'address' => [
						'street' => $addr['street'],
						'houseNo' => $addr['houseNo'],
						'zip' => $addr['zip'] !== '' ? $addr['zip'] : $letter->getEmpfaengerPlz(),
						'city' => $addr['city'],
						'country' => 'DEU',
					],
				],
			];
		}
		return [
			'type' => 'AppShoppingCartPDFRequest',
			'shopOrderId' => mb_substr(preg_replace('/[^A-Za-z0-9_-]/', '', $shopOrderId) ?? $shopOrderId, 0, 18),
			'total' => $totalCents,
			'createManifest' => true,
			'positions' => [$position],
		];
	}

	/**
	 * @param array<string, mixed> $result
	 */
	private function applyCheckoutResult(PostageOrder $order, Letter $letter, array $result, bool $paid): void {
		$recoveryNotes = [];
		try {
			$order->setResponseJson(json_encode($result, JSON_UNESCAPED_UNICODE) ?: null);
			$order->setShopOrderId(isset($result['shopOrderId']) ? (string)$result['shopOrderId'] : $order->getShopOrderId());
			$link = (string)($result['link'] ?? $result['manifestLink'] ?? '');
			$vouchers = $result['shoppingCart']['voucherList'] ?? $result['voucherList'] ?? [];
			if (is_array($vouchers) && $vouchers !== []) {
				$first = $vouchers[0];
				if (is_array($first)) {
					$order->setVoucherId(isset($first['voucherId']) ? (string)$first['voucherId'] : null);
					$track = (string)($first['trackId'] ?? $first['voucherId'] ?? '');
					if ($track !== '') {
						$order->setTrackingNumber($track);
						if ($letter->getSendungsnummer() === '') {
							try {
								$letter->setSendungsnummer(strtoupper(preg_replace('/[^A-Z0-9]/i', '', $track) ?? $track));
								$this->letters->saveEntity($letter);
							} catch (\Throwable $e) {
								$recoveryNotes[] = 'Sendungsnummer lokal nicht speicherbar: ' . $e->getMessage();
							}
						}
					}
				}
			}
			$stampStored = false;
			if ($link !== '' && $paid) {
				try {
					$pdf = $this->client->downloadBinary($link);
					if (str_starts_with($pdf, '%PDF')) {
						$path = $this->stamps->storeBinary($pdf, $letter->getId());
						$order->setStampPath($path);
						$letter->setInternetmarkePath($path);
						$this->letters->saveEntity($letter);
						$stampStored = true;
					} else {
						$recoveryNotes[] = 'Download war keine gültige PDF-Datei.';
					}
				} catch (\Throwable $e) {
					$recoveryNotes[] = 'PDF-Download/Speicherung fehlgeschlagen: ' . $e->getMessage();
				}
			}
			if ($paid) {
				// Checkout erfolgt: purchased bei Voucher/Tracking/Marke, sonst uncertain (Recovery, kein Re-Kauf)
				$hasProof = $stampStored
					|| trim((string)($order->getVoucherId() ?? '')) !== ''
					|| trim((string)($order->getTrackingNumber() ?? '')) !== '';
				if ($hasProof) {
					$order->setStatus('purchased');
					if ($recoveryNotes !== []) {
						$order->setErrorMessage('Kauf erfolgt, Nacharbeit nötig: ' . implode(' | ', $recoveryNotes));
					}
				} else {
					$order->setStatus('uncertain');
					$order->setErrorMessage(
						'Checkout-Antwort ohne klare Marke/Sendungsnummer. Kein erneuter Kauf mit diesem Schlüssel. '
						. implode(' | ', $recoveryNotes)
					);
				}
			} else {
				$order->setStatus('preview_ok');
			}
		} catch (\Throwable $e) {
			if ($paid) {
				$order->setStatus('uncertain');
				$order->setErrorMessage(
					'Checkout erfolgt, Nachverarbeitung fehlgeschlagen (kein erneuter Kauf): ' . mb_substr($e->getMessage(), 0, 1800)
				);
			} else {
				throw $e;
			}
		}
		$order->setUpdatedAt((new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
		$this->orders->update($order);
	}

	/** @return array{name:string, additional:string, street:string, houseNo:string, zip:string, city:string} */
	private function parseRecipient(string $raw): array {
		$lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $raw) ?: [])));
		$name = $lines[0] ?? '';
		$additional = '';
		$street = '';
		$houseNo = '';
		$zip = '';
		$city = '';
		$streetLine = '';
		$cityLine = '';
		if (count($lines) >= 3) {
			$additional = $lines[1];
			$streetLine = $lines[count($lines) - 2];
			$cityLine = $lines[count($lines) - 1];
		} elseif (count($lines) === 2) {
			$cityLine = $lines[1];
		}
		if ($streetLine !== '') {
			if (preg_match('/^(.*)\s+(\d+\s*[a-zA-Z]?)$/u', $streetLine, $m)) {
				$street = trim($m[1]);
				$houseNo = trim($m[2]);
			} else {
				$street = $streetLine;
			}
		}
		if ($cityLine !== '' && preg_match('/^(\d{5})\s+(.+)$/u', $cityLine, $m)) {
			$zip = $m[1];
			$city = $m[2];
		} elseif ($cityLine !== '') {
			$city = $cityLine;
		}
		return compact('name', 'additional', 'street', 'houseNo', 'zip', 'city');
	}
}
