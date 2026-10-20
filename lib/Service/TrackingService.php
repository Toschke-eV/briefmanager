<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCA\BriefManager\Db\Letter;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\Http\Client\IClientService;

/**
 * Einschreiben-Rückverfolgung über die JSON-API der Deutschen Post
 * (Port von briefpapier-einschreiben).
 */
class TrackingService {
	private const SEARCH = 'https://www.deutschepost.de/int-verfolgen/data/search';
	private const RECEIPT = 'https://www.deutschepost.de/int-verfolgen/data/receipt';
	private const UA = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36';

	public function __construct(
		private IClientService $http,
		private IAppData $appData,
		private StampService $stamps,
		private LetterService $letters,
	) {
	}

	/**
	 * @return array{sendungsnummer: string, plz: string}
	 */
	public function extractFromStampKey(string $stampKey): array {
		$path = $this->stamps->resolveToLocalPath($stampKey);
		if ($path === null) {
			return ['sendungsnummer' => '', 'plz' => ''];
		}
		try {
			return $this->extractFromFile($path);
		} finally {
			if (str_contains($path, sys_get_temp_dir())) {
				@unlink($path);
			}
		}
	}

	/**
	 * @return array{sendungsnummer: string, plz: string}
	 */
	public function extractFromFile(string $path): array {
		$empty = ['sendungsnummer' => '', 'plz' => ''];
		if (!is_readable($path)) {
			return $empty;
		}
		$raw = (string)file_get_contents($path);
		if ($raw === '') {
			return $empty;
		}
		$texts = [basename($path)];
		$rawBlobs = [$raw];
		if (preg_match_all('/stream\r?\n(.*?)endstream/s', $raw, $matches)) {
			foreach ($matches[1] as $stream) {
				$stream = ltrim($stream, "\r\n");
				foreach (['gzuncompress', 'gzinflate'] as $decoder) {
					$decoded = @$decoder($stream);
					if (is_string($decoded) && strlen($decoded) > 8) {
						$rawBlobs[] = $decoded;
						break;
					}
				}
			}
		}
		foreach ($rawBlobs as $blob) {
			if (preg_match_all('/\((?:\\\\.|[^\\\\)]){2,}\)/', $blob, $strings)) {
				foreach ($strings[0] as $token) {
					$inner = stripcslashes(substr($token, 1, -1));
					if (strncmp($inner, "\xFE\xFF", 2) === 0 && function_exists('mb_convert_encoding')) {
						$inner = (string)mb_convert_encoding(substr($inner, 2), 'UTF-8', 'UTF-16BE');
					}
					$texts[] = $inner;
				}
			}
			if (preg_match_all('/<([0-9A-Fa-f]{12,})>/', $blob, $hexes)) {
				foreach ($hexes[1] as $hex) {
					$bin = @hex2bin($hex);
					if (is_string($bin) && $bin !== '') {
						$texts[] = $bin;
					}
				}
			}
		}
		$hay = implode(' ', $texts);
		return [
			'sendungsnummer' => $this->findPiececode($hay),
			'plz' => $this->findPlz($hay),
		];
	}

	/**
	 * Sendungsnummer/PLZ aus Marke oder Empfänger ergänzen und Status abrufen.
	 *
	 * @return array<string, mixed>
	 */
	public function refresh(Letter $letter, bool $force = true): array {
		$code = strtoupper(preg_replace('/[^A-Z0-9]/i', '', $letter->getSendungsnummer()) ?? '');
		$plz = preg_replace('/\D+/', '', $letter->getEmpfaengerPlz()) ?? '';

		if ($code === '' || $plz === '') {
			$stampKey = trim((string)($letter->getInternetmarkePath() ?? ''));
			if ($stampKey !== '') {
				$parsed = $this->extractFromStampKey($stampKey);
				if ($code === '') {
					$code = $parsed['sendungsnummer'];
				}
				if ($plz === '') {
					$plz = $parsed['plz'];
				}
			}
			if ($plz === '' && preg_match('/\b(\d{5})\b/', $letter->getEmpfaenger(), $m)) {
				$plz = $m[1];
			}
			if ($code !== '' || $plz !== '') {
				if ($code !== '') {
					$letter->setSendungsnummer($code);
				}
				if ($plz !== '') {
					$letter->setEmpfaengerPlz($plz);
				}
				$this->letters->saveEntity($letter);
			}
		}

		if ($code === '') {
			throw new \RuntimeException('Keine Sendungsnummer gefunden. Internetmarke prüfen oder Nummer manuell eintragen.');
		}

		if (!$force && $letter->getTrackingChecked() !== '') {
			$checked = strtotime($letter->getTrackingChecked());
			if ($checked !== false && (time() - $checked) < 300) {
				$cached = json_decode($letter->getTrackingJson() ?: '{}', true);
				return is_array($cached) ? $cached : ['status' => $letter->getTrackingStatus()];
			}
		}

		$fetched = $this->fetch($code, $plz !== '' ? $plz : null);
		if (empty($fetched['events']) && empty($fetched['eingeschraenkt']) && $plz === '') {
			throw new \RuntimeException('Die Post verlangt die Empfänger-PLZ. Bitte PLZ eintragen und erneut abrufen.');
		}
		$this->store($letter, $fetched, $plz);
		return $fetched;
	}

	/**
	 * @return array{nummer:string,produkt:string,status:string,events:list,zustelldatum:string,eingeschraenkt:bool,tokens:list,uuid:string,raw:array}
	 */
	public function fetch(string $piececode, ?string $plz = null): array {
		$first = $this->requestSearch($piececode, null);
		$needsPlz = in_array('POSTCODE', (array)($first['tokens'] ?? []), true) || empty($first['events']);
		if ($needsPlz && $plz) {
			$second = $this->requestSearch($piececode, $plz);
			if (!empty($second['events']) || ($second['status'] ?? '') !== '') {
				return $second;
			}
		}
		return $first;
	}

	/** @return string Relativer AppData-Schlüssel receipts/... */
	public function fetchReceipt(Letter $letter): string {
		$fetched = $this->refresh($letter, true);
		$uuid = (string)($fetched['uuid'] ?? '');
		if ($uuid === '') {
			$data = json_decode($letter->getTrackingJson() ?: '{}', true);
			$uuid = is_array($data) ? (string)($data['uuid'] ?? '') : '';
		}
		if ($uuid === '') {
			throw new \RuntimeException('Für diese Sendung liegt kein elektronischer Beleg vor.');
		}
		$client = $this->http->newClient();
		$body = json_encode([
			'shipmentId' => $letter->getSendungsnummer(),
			'eventObjectUuid' => $uuid,
			'zip' => $letter->getEmpfaengerPlz(),
			'postedDate' => '',
			'postedTime' => '',
		], JSON_UNESCAPED_UNICODE);
		$response = $client->post(self::RECEIPT, [
			'timeout' => 30,
			'headers' => [
				'Content-Type' => 'application/json',
				'Accept' => 'application/pdf',
				'User-Agent' => self::UA,
			],
			'body' => $body ?: '{}',
		]);
		$code = $response->getStatusCode();
		$raw = $response->getBody();
		if ($code !== 200 || !str_starts_with($raw, '%PDF')) {
			throw new \RuntimeException('Beleg konnte nicht geladen werden. PLZ prüfen oder später erneut versuchen.');
		}
		$folder = $this->receiptsFolder();
		$fileName = 'beleg-' . preg_replace('/[^A-Z0-9]/i', '', $letter->getSendungsnummer()) . '.pdf';
		try {
			$file = $folder->getFile($fileName);
		} catch (NotFoundException) {
			$file = $folder->newFile($fileName);
		}
		$file->putContent($raw);
		$key = 'receipts/' . $fileName;
		$letter->setTrackingReceipt($key);
		$this->letters->saveEntity($letter);
		return $key;
	}

	public function resolveReceiptToLocalPath(string $key): ?string {
		$key = trim($key);
		if ($key === '' || !str_starts_with($key, 'receipts/')) {
			return null;
		}
		try {
			$file = $this->receiptsFolder()->getFile(basename($key));
		} catch (NotFoundException) {
			return null;
		}
		$content = $file->getContent();
		if ($content === '' || $content === false || !str_starts_with((string)$content, '%PDF')) {
			return null;
		}
		$tmp = tempnam(sys_get_temp_dir(), 'bmreceipt_') . '.pdf';
		if (@file_put_contents($tmp, $content) === false) {
			return null;
		}
		return $tmp;
	}

	/** @param array<string, mixed> $fetched */
	private function store(Letter $letter, array $fetched, string $plz): void {
		$nummer = trim((string)($fetched['nummer'] ?? ''));
		if ($nummer === '') {
			$nummer = $letter->getSendungsnummer();
		}
		$status = (string)($fetched['status'] ?? '');
		if ($status === '' && !empty($fetched['eingeschraenkt'])) {
			$status = 'Verlauf nicht mehr verfügbar';
		} elseif ($status === '') {
			$status = 'Unbekannt';
		}
		$delivered = (string)($fetched['zustelldatum'] ?? '');
		if ($delivered === '' && !empty($fetched['eingeschraenkt'])) {
			$delivered = $letter->getTrackingDelivered();
		}
		$letter->setSendungsnummer(strtoupper(preg_replace('/[^A-Z0-9]/i', '', $nummer) ?? ''));
		if ($plz !== '') {
			$letter->setEmpfaengerPlz($plz);
		}
		$letter->setTrackingStatus($status);
		$letter->setTrackingDelivered($delivered);
		$letter->setTrackingJson(json_encode($fetched, JSON_UNESCAPED_UNICODE) ?: '{}');
		$letter->setTrackingChecked((new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
		$this->letters->saveEntity($letter);
	}

	/** @return array<string, mixed> */
	private function requestSearch(string $piececode, ?string $plz): array {
		$params = [
			'piececode' => $piececode,
			'language' => 'de',
			'lang' => 'de',
			'noRedirect' => 'true',
		];
		if ($plz) {
			$params['plz'] = $plz;
		}
		$url = self::SEARCH . '?' . http_build_query($params);
		$client = $this->http->newClient();
		$response = $client->get($url, [
			'timeout' => 30,
			'headers' => [
				'Accept' => 'application/json',
				'User-Agent' => self::UA,
			],
			'allow_redirects' => false,
		]);
		$code = $response->getStatusCode();
		if ($code === 404) {
			throw new \RuntimeException('Die Sendungsverfolgung hat 404 geliefert. Nummer und PLZ prüfen.');
		}
		if ($code !== 200) {
			throw new \RuntimeException('Die Post-API antwortete mit HTTP ' . $code . '.');
		}
		$json = json_decode($response->getBody(), true);
		if (!is_array($json)) {
			throw new \RuntimeException('Ungültige Antwort der Sendungsverfolgung.');
		}
		if (!empty($json['rateLimited'])) {
			throw new \RuntimeException('Die Post drosselt Anfragen. Bitte in einer Minute erneut versuchen.');
		}
		$sendung = ($json['sendungen'] ?? [null])[0] ?? null;
		if (!is_array($sendung)) {
			return [
				'nummer' => $piececode,
				'produkt' => '',
				'status' => 'Unbekannt',
				'events' => [],
				'zustelldatum' => '',
				'eingeschraenkt' => false,
				'tokens' => [],
				'uuid' => '',
				'raw' => $json,
			];
		}
		$details = is_array($sendung['sendungsdetails'] ?? null) ? $sendung['sendungsdetails'] : [];
		$verlauf = is_array($details['sendungsverlauf'] ?? null) ? $details['sendungsverlauf'] : [];
		$events = is_array($verlauf['events'] ?? null) ? $verlauf['events'] : [];
		$last = $events !== [] ? $events[count($events) - 1] : [];
		$receipt = is_array($sendung['receipt'] ?? null) ? $sendung['receipt'] : [];
		return [
			'nummer' => (string)(($sendung['id'] ?? '') !== '' ? $sendung['id'] : $piececode),
			'produkt' => (string)($details['produktName'] ?? ''),
			'status' => (string)($verlauf['status'] ?? ''),
			'events' => $events,
			'zustelldatum' => substr((string)($last['datum'] ?? ''), 0, 10),
			'eingeschraenkt' => !empty($details['eingeschraenkteVerfolgung']),
			'tokens' => (array)($receipt['requiredRequestTokens'] ?? []),
			'uuid' => (string)($receipt['eventObjectUuid'] ?? ''),
			'raw' => $json,
		];
	}

	private function findPiececode(string $hay): string {
		$source = strtoupper((string)preg_replace('/[^A-Z0-9]+/i', ' ', $hay));
		$patterns = [
			'/A[0-9A-Z](?:\s*[0-9A-Z]){19}/',
			'/A[0-9A-Z](?:\s*[0-9A-Z]){21}/',
			'/C[0-9A-Z](?:\s*[0-9A-Z]){19}/',
			'/C[0-9A-Z](?:\s*[0-9A-Z]){21}/',
			'/R[A-Z0-9](?:\s*[A-Z0-9]){19}/',
			'/[A-Z]{2}\s*\d{9}\s*[A-Z]{2}/',
			'/\d(?:\s*\d){19}/',
			'/\d(?:\s*\d){21}/',
			'/\d(?:\s*\d){11}/',
		];
		$hits = [];
		foreach ($patterns as $pattern) {
			if (preg_match_all($pattern, $source, $matches)) {
				foreach ($matches[0] as $hit) {
					$code = preg_replace('/\s+/', '', $hit) ?? '';
					if ($this->looksLikePiececode($code)) {
						$hits[] = $code;
					}
				}
			}
		}
		usort($hits, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
		return $hits[0] ?? '';
	}

	private function looksLikePiececode(string $code): bool {
		$code = strtoupper($code);
		if (strlen($code) < 12 || preg_match('/^0+$/', $code)) {
			return false;
		}
		if (preg_match('/^20\d{6,}$/', $code) && strlen($code) <= 14) {
			return false;
		}
		return true;
	}

	private function findPlz(string $hay): string {
		if (preg_match('/\b(?:PLZ|Postleitzahl)[:\s]*([0-9]{5})\b/i', $hay, $match)) {
			return $match[1];
		}
		if (preg_match_all('/\b([0-9]{5})\b/', $hay, $matches)) {
			foreach (array_reverse($matches[1]) as $plz) {
				if ($plz !== '00000') {
					return $plz;
				}
			}
		}
		return '';
	}

	private function receiptsFolder(): \OCP\Files\SimpleFS\ISimpleFolder {
		try {
			return $this->appData->getFolder('receipts');
		} catch (NotFoundException) {
			return $this->appData->newFolder('receipts');
		}
	}
}
