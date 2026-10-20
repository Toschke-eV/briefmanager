<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

/**
 * Offizielle REST-API Deutsche Post INTERNETMARKE (aktiv, SOAP retired 31.12.2025).
 *
 * Base: https://api-eu.dhl.com/post/de/shipping/im/v1
 * Products: https://api-eu.dhl.com/post/de/information/products/v1/products?profile=IM-PARTNER
 *
 * Keine erfundenen Endpunkte. Kauf nur wenn Aufrufer das explizit verlangt.
 */
class InternetmarkeClient {
	public const BASE = 'https://api-eu.dhl.com/post/de/shipping/im/v1';
	public const PRODUCTS = 'https://api-eu.dhl.com/post/de/information/products/v1/products';

	public function __construct(
		private InternetmarkeCredentialStore $credentials,
	) {
	}

	/** @return array{ok:bool, version?:string, error?:string} */
	public function health(): array {
		$res = $this->raw('GET', self::BASE . '/', null, [], false);
		if (!$res['ok']) {
			return ['ok' => false, 'error' => $res['error'] ?? ('HTTP ' . $res['status'])];
		}
		$amp = is_array($res['json']['amp'] ?? null) ? $res['json']['amp'] : [];
		return [
			'ok' => true,
			'version' => (string)($amp['version'] ?? ''),
		];
	}

	/**
	 * @return array{access_token:string, expires_in?:int, walletBalance?:mixed}
	 */
	public function authenticate(): array {
		$app = $this->credentials->getAppCredentials();
		$user = $this->credentials->getUserPortokasse();
		if (!$app['configured'] || !$user['configured']) {
			throw new \RuntimeException('Internetmarke-Zugangsdaten unvollständig (App-API und/oder Portokasse).');
		}
		$body = http_build_query([
			'grant_type' => 'client_credentials',
			'client_id' => $app['client_id'],
			'client_secret' => $app['client_secret'],
			'username' => $user['username'],
			'password' => $user['password'],
		], '', '&', PHP_QUERY_RFC3986);
		$res = $this->raw('POST', self::BASE . '/user', $body, [
			'Content-Type: application/x-www-form-urlencoded; charset=UTF-8',
		], false);
		if (!$res['ok']) {
			throw new \RuntimeException('Token-Abruf fehlgeschlagen: ' . ($res['error'] ?? ('HTTP ' . $res['status'])));
		}
		$token = (string)($res['json']['access_token'] ?? '');
		if ($token === '') {
			throw new \RuntimeException('Token-Antwort ohne access_token.');
		}
		return [
			'access_token' => $token,
			'expires_in' => isset($res['json']['expires_in']) ? (int)$res['json']['expires_in'] : null,
			'walletBalance' => $res['json']['walletBalance'] ?? null,
		];
	}

	/** @return array<string, mixed> */
	public function profile(string $bearer): array {
		$res = $this->raw('GET', self::BASE . '/user/profile', null, [
			'Authorization: Bearer ' . $bearer,
		], false);
		if (!$res['ok']) {
			throw new \RuntimeException('Profilabruf fehlgeschlagen: ' . ($res['error'] ?? ('HTTP ' . $res['status'])));
		}
		return is_array($res['json']) ? $res['json'] : [];
	}

	/**
	 * Produktkatalog (Products API, gleiche API-Keys).
	 *
	 * @return list<array<string, mixed>>
	 */
	public function listProducts(): array {
		$app = $this->credentials->getAppCredentials();
		if (!$app['configured']) {
			throw new \RuntimeException('Products API benötigt den INTERNETMARKE API-Key (client_id).');
		}
		$url = self::PRODUCTS . '?profile=' . rawurlencode('IM-PARTNER');
		$res = $this->raw('GET', $url, null, [
			'dhl-api-key: ' . $app['client_id'],
		], false);
		if (!$res['ok']) {
			throw new \RuntimeException('Produktkatalog fehlgeschlagen: ' . ($res['error'] ?? ('HTTP ' . $res['status'])));
		}
		$json = $res['json'];
		$products = [];
		if (isset($json['shortSalesProducts']) && is_array($json['shortSalesProducts'])) {
			$products = $json['shortSalesProducts'];
		} elseif (isset($json['products']) && is_array($json['products'])) {
			$products = $json['products'];
		} elseif (is_array($json)) {
			$products = $json;
		}
		$out = [];
		foreach ($products as $row) {
			if (!is_array($row)) {
				continue;
			}
			$out[] = $row;
		}
		return $out;
	}

	/**
	 * Preview (validate=true): erzeugt keine kostenpflichtige Marke.
	 *
	 * @param array<string, mixed> $previewBody
	 * @return array<string, mixed>
	 */
	public function previewPdf(array $previewBody): array {
		$previewBody['type'] = 'AppShoppingCartPreviewPDFRequest';
		$url = self::BASE . '/app/shoppingcart/pdf?validate=true';
		$res = $this->raw('POST', $url, json_encode($previewBody, JSON_UNESCAPED_UNICODE), [
			'Content-Type: application/json',
			'Accept: application/json',
		], false);
		if (!$res['ok']) {
			throw new \RuntimeException('Preview fehlgeschlagen: ' . ($res['error'] ?? ('HTTP ' . $res['status'])));
		}
		return is_array($res['json']) ? $res['json'] : [];
	}

	/**
	 * Kostenpflichtiger Kauf (directCheckout=true). Nur aufrufen wenn freigeschaltet.
	 *
	 * @param array<string, mixed> $checkoutBody
	 * @return array<string, mixed>
	 */
	public function checkoutPdf(string $bearer, array $checkoutBody): array {
		$checkoutBody['type'] = 'AppShoppingCartPDFRequest';
		$url = self::BASE . '/app/shoppingcart/pdf?directCheckout=true';
		$res = $this->raw('POST', $url, json_encode($checkoutBody, JSON_UNESCAPED_UNICODE), [
			'Content-Type: application/json',
			'Accept: application/json',
			'Authorization: Bearer ' . $bearer,
		], false);
		if (!$res['ok']) {
			throw new \RuntimeException('Checkout fehlgeschlagen: ' . ($res['error'] ?? ('HTTP ' . $res['status'])));
		}
		return is_array($res['json']) ? $res['json'] : [];
	}

	public function downloadBinary(string $url): string {
		$res = $this->raw('GET', $url, null, [], true);
		if (!$res['ok'] || !is_string($res['body']) || $res['body'] === '') {
			throw new \RuntimeException('Download fehlgeschlagen: ' . ($res['error'] ?? ('HTTP ' . $res['status'])));
		}
		return $res['body'];
	}

	/**
	 * @param list<string> $headers
	 * @return array{ok:bool, status:int, json:?array, body:?string, error?:string}
	 */
	private function raw(string $method, string $url, ?string $body, array $headers, bool $binary): array {
		$ch = curl_init($url);
		if ($ch === false) {
			return ['ok' => false, 'status' => 0, 'json' => null, 'body' => null, 'error' => 'curl_init fehlgeschlagen'];
		}
		$opts = [
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_TIMEOUT => 45,
			CURLOPT_HTTPHEADER => $headers,
			CURLOPT_USERAGENT => 'Briefmanager-Nextcloud/0.9',
		];
		if ($body !== null) {
			$opts[CURLOPT_POSTFIELDS] = $body;
		}
		curl_setopt_array($ch, $opts);
		$raw = curl_exec($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err = curl_error($ch);
		curl_close($ch);
		if ($raw === false) {
			return ['ok' => false, 'status' => $status, 'json' => null, 'body' => null, 'error' => $err !== '' ? $err : 'Netzwerkfehler'];
		}
		if ($binary) {
			return [
				'ok' => $status >= 200 && $status < 300,
				'status' => $status,
				'json' => null,
				'body' => (string)$raw,
				'error' => $status >= 200 && $status < 300 ? null : ('HTTP ' . $status),
			];
		}
		$json = json_decode((string)$raw, true);
		$ok = $status >= 200 && $status < 300;
		$error = null;
		if (!$ok) {
			if (is_array($json)) {
				$error = (string)($json['message'] ?? $json['error_description'] ?? $json['title'] ?? ('HTTP ' . $status));
				if (isset($json['errors']) && is_array($json['errors'])) {
					$error .= ' | ' . json_encode($json['errors'], JSON_UNESCAPED_UNICODE);
				}
			} else {
				$error = 'HTTP ' . $status . ': ' . mb_substr((string)$raw, 0, 240);
			}
		}
		return [
			'ok' => $ok,
			'status' => $status,
			'json' => is_array($json) ? $json : null,
			'body' => (string)$raw,
			'error' => $error,
		];
	}
}
