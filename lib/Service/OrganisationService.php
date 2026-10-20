<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCA\BriefManager\AppInfo\Application;
use OCA\BriefManager\Design\DesignRegistry;
use OCP\IConfig;

/**
 * Mehrere Vereine/Organisationen, eine aktive für Briefe und PDF.
 */
class OrganisationService {
	public const CONFIG_KEY = 'organisation_json';
	public const CONFIG_ORGS = 'organisations_json';
	public const CONFIG_ACTIVE = 'active_organisation_id';

	public function __construct(
		private IConfig $config,
		private DesignRegistry $designs,
	) {
	}

	/** @return array<string, mixed> */
	public function blank(): array {
		return [
			'id' => '',
			'design' => '',
			'name' => '',
			'tagline' => '',
			'address_extra' => '',
			'street' => '',
			'zip' => '',
			'city' => '',
			'phone' => '',
			'fax' => '',
			'email' => '',
			'website' => '',
			'slogan' => '',
			'iban' => '',
			'bic' => '',
			'bank_name' => '',
			'register_court' => '',
			'vr_number' => '',
			'tax_office' => '',
			'tax_id' => '',
			'board' => [],
			'seat_city' => '',
			'seat_region' => '',
			'logo_path' => '',
			'ref_prefix' => '',
			'ref_vorab' => '',
			'ref_pattern' => '{prefix}{vorab}{Y}{m}{d}-{n}',
			'title_pad' => 5,
			'ref_pad' => 3,
			'ref_reset' => 'daily',
			'howto' => '',
		];
	}

	/** Aktive Organisation (für Briefe/PDF). */
	public function get(): array {
		$this->migrateLegacy();
		$activeId = $this->config->getAppValue(Application::APP_ID, self::CONFIG_ACTIVE, '');
		$orgs = $this->listAll();
		if ($orgs === []) {
			$created = $this->create(['name' => '']);
			return $created;
		}
		foreach ($orgs as $org) {
			if (($org['id'] ?? '') === $activeId) {
				return $org;
			}
		}
		$first = $orgs[0];
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_ACTIVE, (string)$first['id']);
		return $first;
	}

	/** @return list<array<string, mixed>> */
	public function listAll(): array {
		$this->migrateLegacy();
		$raw = $this->config->getAppValue(Application::APP_ID, self::CONFIG_ORGS, '[]');
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}
		$out = [];
		foreach ($decoded as $row) {
			if (!is_array($row)) {
				continue;
			}
			$out[] = $this->normalize($row);
		}
		return $out;
	}

	public function getActiveId(): string {
		$this->migrateLegacy();
		return $this->config->getAppValue(Application::APP_ID, self::CONFIG_ACTIVE, '');
	}

	public function setActive(string $id): array {
		$org = $this->find($id);
		if ($org === null) {
			throw new \RuntimeException('Verein nicht gefunden');
		}
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_ACTIVE, $id);
		return $org;
	}

	/** @param array<string, mixed> $data */
	public function create(array $data): array {
		$org = $this->normalize(array_merge($this->blank(), $data, [
			'id' => $this->newId(),
		]));
		$orgs = $this->listAll();
		$orgs[] = $org;
		$this->persist($orgs);
		if ($this->getActiveId() === '') {
			$this->config->setAppValue(Application::APP_ID, self::CONFIG_ACTIVE, $org['id']);
		}
		return $org;
	}

	/** @param array<string, mixed> $data */
	public function save(array $data): array {
		$id = (string)($data['id'] ?? $this->getActiveId());
		if ($id === '') {
			return $this->create($data);
		}
		$orgs = $this->listAll();
		$found = false;
		foreach ($orgs as $i => $org) {
			if (($org['id'] ?? '') !== $id) {
				continue;
			}
			$merged = array_merge($org, $data, ['id' => $id]);
			$orgs[$i] = $this->normalize($merged);
			$found = true;
			$saved = $orgs[$i];
			break;
		}
		if (!$found) {
			throw new \RuntimeException('Verein nicht gefunden');
		}
		$this->persist($orgs);
		return $saved;
	}

	public function delete(string $id): void {
		$orgs = array_values(array_filter(
			$this->listAll(),
			static fn (array $o) => ($o['id'] ?? '') !== $id
		));
		if ($orgs === []) {
			throw new \RuntimeException('Mindestens ein Verein muss bestehen.');
		}
		$this->persist($orgs);
		if ($this->getActiveId() === $id) {
			$this->config->setAppValue(Application::APP_ID, self::CONFIG_ACTIVE, (string)$orgs[0]['id']);
		}
	}

	public function find(string $id): ?array {
		foreach ($this->listAll() as $org) {
			if (($org['id'] ?? '') === $id) {
				return $org;
			}
		}
		return null;
	}

	/** @param list<array<string, mixed>> $orgs */
	private function persist(array $orgs): void {
		$json = json_encode(array_values($orgs), JSON_UNESCAPED_UNICODE);
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_ORGS, $json ?: '[]');
	}

	/** @param array<string, mixed> $row @return array<string, mixed> */
	private function normalize(array $row): array {
		$org = array_merge($this->blank(), $row);
		if (($org['id'] ?? '') === '') {
			$org['id'] = $this->newId();
		}
		$org['board'] = $this->normalizeBoard($org['board'] ?? [], $row);
		$design = trim((string)$org['design']);
		$labels = $this->designs->labels();
		if ($design !== '' && !isset($labels[$design])) {
			$design = '';
		}
		$org['design'] = $design;
		foreach (['name', 'tagline', 'street', 'zip', 'city', 'phone', 'fax', 'email', 'website', 'slogan', 'iban', 'bic', 'bank_name', 'register_court', 'vr_number', 'tax_office', 'tax_id', 'seat_city', 'seat_region', 'logo_path', 'ref_prefix', 'ref_vorab', 'ref_pattern', 'howto', 'address_extra'] as $strKey) {
			$org[$strKey] = mb_substr(trim((string)$org[$strKey]), 0, $strKey === 'howto' ? 4000 : 255);
		}
		$org['title_pad'] = max(1, min(8, (int)$org['title_pad']));
		$org['ref_pad'] = max(1, min(8, (int)$org['ref_pad']));
		$org['ref_reset'] = in_array($org['ref_reset'], ['daily', 'global'], true) ? $org['ref_reset'] : 'daily';
		return $org;
	}

	/**
	 * @param mixed $board
	 * @param array<string, mixed> $row
	 * @return list<array{name: string, role: string}>
	 */
	private function normalizeBoard(mixed $board, array $row): array {
		if (isset($row['board_name']) && is_array($row['board_name'])) {
			$names = $row['board_name'];
			$roles = is_array($row['board_role'] ?? null) ? $row['board_role'] : [];
			$out = [];
			foreach ($names as $i => $name) {
				$name = trim((string)$name);
				$role = trim((string)($roles[$i] ?? ''));
				if ($name === '' && $role === '') {
					continue;
				}
				$out[] = ['name' => mb_substr($name, 0, 190), 'role' => mb_substr($role, 0, 190)];
			}
			return array_slice($out, 0, 12);
		}
		if (!is_array($board)) {
			return [];
		}
		$out = [];
		foreach ($board as $member) {
			if (!is_array($member)) {
				continue;
			}
			$name = trim((string)($member['name'] ?? ''));
			$role = trim((string)($member['role'] ?? ''));
			if ($name === '' && $role === '') {
				continue;
			}
			$out[] = ['name' => mb_substr($name, 0, 190), 'role' => mb_substr($role, 0, 190)];
		}
		return array_slice($out, 0, 12);
	}

	private function migrateLegacy(): void {
		$existing = $this->config->getAppValue(Application::APP_ID, self::CONFIG_ORGS, '');
		if ($existing !== '' && $existing !== '[]') {
			return;
		}
		$legacy = $this->config->getAppValue(Application::APP_ID, self::CONFIG_KEY, '');
		if ($legacy === '') {
			return;
		}
		$decoded = json_decode($legacy, true);
		if (!is_array($decoded)) {
			return;
		}
		$org = $this->normalize(array_merge($decoded, ['id' => $this->newId()]));
		$this->persist([$org]);
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_ACTIVE, $org['id']);
	}

	private function newId(): string {
		try {
			return bin2hex(random_bytes(8));
		} catch (\Throwable) {
			return uniqid('org_', true);
		}
	}
}
