<?php

declare(strict_types=1);

namespace OCA\BriefManager\Design;

use OCA\BriefManager\AppInfo\Application;
use OCP\IConfig;

/**
 * Eingebaute Design-Presets plus vom Admin angelegte eigene Presets.
 */
class DesignRegistry {
	public const CONFIG_CUSTOM = 'custom_designs_json';

	public function __construct(
		private IConfig $config,
	) {
	}

	/** @return array<string, array<string, mixed>> */
	private function builtins(): array {
		return [
			'muster' => ['label' => 'Musterorganisation', 'blue' => [13, 94, 148], 'gray' => [75, 85, 99], 'accent' => [201, 162, 39], 'line' => [215, 224, 234], 'font' => 'dejavusans', 'logo' => 'muster.png', 'crest' => false, 'title_size' => 12.5, 'tagline_size' => 8.2, 'builtin' => true],
			'verein' => ['label' => 'Musterverein', 'blue' => [21, 74, 138], 'gray' => [90, 101, 112], 'accent' => [224, 168, 58], 'line' => [155, 184, 216], 'font' => 'dejavusans', 'logo' => 'muster.png', 'crest' => false, 'title_size' => 11.0, 'tagline_size' => 8.0, 'builtin' => true],
			'familie' => ['label' => 'Familienbrief', 'blue' => [26, 58, 107], 'gray' => [90, 101, 112], 'accent' => [201, 162, 39], 'line' => [215, 224, 234], 'font' => 'dejavusans', 'logo' => 'muster.png', 'crest' => false, 'title_size' => 16.0, 'tagline_size' => 9.0, 'builtin' => true],
			'buero' => ['label' => 'Büro / Organisation', 'blue' => [31, 42, 55], 'gray' => [75, 85, 99], 'accent' => [245, 215, 110], 'line' => [215, 224, 234], 'font' => 'dejavusans', 'logo' => 'muster.png', 'crest' => false, 'title_size' => 12.5, 'tagline_size' => 8.2, 'builtin' => true],
		];
	}

	/** @return array<string, array<string, mixed>> */
	public function all(): array {
		$all = $this->builtins();
		foreach ($this->loadCustom() as $slug => $row) {
			$all[$slug] = $row;
		}
		return $all;
	}

	/** @return array<string, string> */
	public function labels(): array {
		$out = [];
		foreach ($this->all() as $slug => $row) {
			$out[$slug] = (string)($row['label'] ?? $slug);
		}
		return $out;
	}

	/**
	 * @return array{blue:int[], gray:int[], accent:int[], line:int[], font:string, logo:string, crest:bool, title_size:float, tagline_size:float, logo_w?:float, label?:string, builtin?:bool}
	 */
	public function get(string $slug): array {
		$all = $this->all();
		$row = $all[$slug] ?? $all['muster'];
		return [
			'blue' => $this->rgb($row['blue'] ?? [13, 94, 148]),
			'gray' => $this->rgb($row['gray'] ?? [75, 85, 99]),
			'accent' => $this->rgb($row['accent'] ?? [201, 162, 39]),
			'line' => $this->rgb($row['line'] ?? [215, 224, 234]),
			'font' => (string)($row['font'] ?? 'dejavusans'),
			'logo' => (string)($row['logo'] ?? 'muster.png'),
			'crest' => !empty($row['crest']),
			'title_size' => (float)($row['title_size'] ?? 12.5),
			'tagline_size' => (float)($row['tagline_size'] ?? 8.2),
			'logo_w' => isset($row['logo_w']) ? (float)$row['logo_w'] : 28.0,
			'label' => (string)($row['label'] ?? $slug),
			'builtin' => !empty($row['builtin']),
		];
	}

	public function primaryHex(string $slug): string {
		$rgb = $this->get($slug !== '' ? $slug : 'muster')['blue'];
		return sprintf('#%02X%02X%02X', $rgb[0], $rgb[1], $rgb[2]);
	}

	/** @return list<array<string, mixed>> */
	public function listDetailed(): array {
		$out = [];
		foreach ($this->all() as $slug => $row) {
			$full = $this->get($slug);
			$out[] = [
				'slug' => $slug,
				'label' => $full['label'],
				'builtin' => !empty($row['builtin']),
				'primary' => $this->primaryHex($slug),
				'blue' => $this->rgbToHex($full['blue']),
				'gray' => $this->rgbToHex($full['gray']),
				'accent' => $this->rgbToHex($full['accent']),
				'line' => $this->rgbToHex($full['line']),
				'title_size' => $full['title_size'],
				'tagline_size' => $full['tagline_size'],
				'logo_w' => $full['logo_w'] ?? 28.0,
				'crest' => $full['crest'],
			];
		}
		return $out;
	}

	/** @param array<string, mixed> $data */
	public function saveCustom(array $data): array {
		$slug = $this->slugify((string)($data['slug'] ?? $data['label'] ?? ''));
		if ($slug === '' || isset($this->builtins()[$slug])) {
			throw new \InvalidArgumentException('Slug ungültig oder bereits als eingebautes Preset vergeben.');
		}
		$label = mb_substr(trim((string)($data['label'] ?? $slug)), 0, 120);
		if ($label === '') {
			$label = $slug;
		}
		$row = [
			'label' => $label,
			'blue' => $this->parseColor($data['blue'] ?? '#0D5E94'),
			'gray' => $this->parseColor($data['gray'] ?? '#4B5563'),
			'accent' => $this->parseColor($data['accent'] ?? '#C9A227'),
			'line' => $this->parseColor($data['line'] ?? '#D7E0EA'),
			'font' => 'dejavusans',
			'logo' => 'muster.png',
			'crest' => !empty($data['crest']),
			'title_size' => max(8.0, min(24.0, (float)($data['title_size'] ?? 12.5))),
			'tagline_size' => max(6.0, min(16.0, (float)($data['tagline_size'] ?? 8.2))),
			'logo_w' => max(16.0, min(50.0, (float)($data['logo_w'] ?? 28.0))),
			'builtin' => false,
		];
		$custom = $this->loadCustom();
		$custom[$slug] = $row;
		$this->persistCustom($custom);
		return $this->get($slug) + ['slug' => $slug];
	}

	public function deleteCustom(string $slug): void {
		$slug = $this->slugify($slug);
		if ($slug === '' || isset($this->builtins()[$slug])) {
			throw new \InvalidArgumentException('Eingebaute Presets können nicht gelöscht werden.');
		}
		$custom = $this->loadCustom();
		if (!isset($custom[$slug])) {
			throw new \RuntimeException('Preset nicht gefunden.');
		}
		unset($custom[$slug]);
		$this->persistCustom($custom);
	}

	/** @return array<string, array<string, mixed>> */
	private function loadCustom(): array {
		$raw = $this->config->getAppValue(Application::APP_ID, self::CONFIG_CUSTOM, '{}');
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}
		$out = [];
		foreach ($decoded as $slug => $row) {
			if (!is_string($slug) || !is_array($row)) {
				continue;
			}
			$slug = $this->slugify($slug);
			if ($slug === '' || isset($this->builtins()[$slug])) {
				continue;
			}
			$row['builtin'] = false;
			$row['label'] = (string)($row['label'] ?? $slug);
			$out[$slug] = $row;
		}
		return $out;
	}

	/** @param array<string, array<string, mixed>> $custom */
	private function persistCustom(array $custom): void {
		$json = json_encode($custom, JSON_UNESCAPED_UNICODE);
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_CUSTOM, $json ?: '{}');
	}

	private function slugify(string $value): string {
		$value = strtolower(trim($value));
		$value = preg_replace('/[^a-z0-9_-]+/', '-', $value) ?? '';
		return trim($value, '-_');
	}

	/** @param mixed $value @return int[] */
	private function rgb(mixed $value): array {
		if (is_string($value)) {
			return $this->parseColor($value);
		}
		if (!is_array($value) || count($value) < 3) {
			return [13, 94, 148];
		}
		return [
			max(0, min(255, (int)$value[0])),
			max(0, min(255, (int)$value[1])),
			max(0, min(255, (int)$value[2])),
		];
	}

	/** @return int[] */
	private function parseColor(mixed $value): array {
		if (is_array($value)) {
			return $this->rgb($value);
		}
		$hex = ltrim(trim((string)$value), '#');
		if (strlen($hex) === 3) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}
		if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
			return [13, 94, 148];
		}
		return [
			hexdec(substr($hex, 0, 2)),
			hexdec(substr($hex, 2, 2)),
			hexdec(substr($hex, 4, 2)),
		];
	}

	/** @param int[] $rgb */
	private function rgbToHex(array $rgb): string {
		return sprintf('#%02X%02X%02X', $rgb[0] ?? 0, $rgb[1] ?? 0, $rgb[2] ?? 0);
	}
}
