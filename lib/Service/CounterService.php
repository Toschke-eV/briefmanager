<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCA\BriefManager\AppInfo\Application;
use OCP\IConfig;

/**
 * Fortlaufende Titel- und Aktenzeichen-Nummern (unabhängig von gelöschten Briefen).
 */
class CounterService {
	private const KEY_TITLE = 'seq_title';
	private const KEY_TITLE_DAY = 'seq_title_day';
	private const KEY_REF = 'seq_ref';
	private const KEY_REF_DAY = 'seq_ref_day';

	public function __construct(
		private IConfig $config,
	) {
	}

	/** Nächste Titel-Laufnummer (täglich neu, weil Titel Ymd-… nutzt). */
	public function nextTitleNumber(): int {
		$day = (new \DateTimeImmutable('now'))->format('Ymd');
		$storedDay = $this->config->getAppValue(Application::APP_ID, self::KEY_TITLE_DAY, '');
		$n = (int)$this->config->getAppValue(Application::APP_ID, self::KEY_TITLE, '0');
		if ($storedDay !== $day) {
			$n = 0;
			$this->config->setAppValue(Application::APP_ID, self::KEY_TITLE_DAY, $day);
		}
		$n++;
		$this->config->setAppValue(Application::APP_ID, self::KEY_TITLE, (string)$n);
		return $n;
	}

	/** Nächste AZ-Laufnummer; Modus daily|global aus Organisations-Stammdaten. */
	public function nextRefNumber(string $resetMode): int {
		$day = (new \DateTimeImmutable('now'))->format('Ymd');
		$storedDay = $this->config->getAppValue(Application::APP_ID, self::KEY_REF_DAY, '');
		$n = (int)$this->config->getAppValue(Application::APP_ID, self::KEY_REF, '0');
		if ($resetMode !== 'global' && $storedDay !== $day) {
			$n = 0;
			$this->config->setAppValue(Application::APP_ID, self::KEY_REF_DAY, $day);
		} elseif ($storedDay === '') {
			$this->config->setAppValue(Application::APP_ID, self::KEY_REF_DAY, $day);
		}
		$n++;
		$this->config->setAppValue(Application::APP_ID, self::KEY_REF, (string)$n);
		return $n;
	}

	public function reset(): void {
		$this->config->setAppValue(Application::APP_ID, self::KEY_TITLE, '0');
		$this->config->setAppValue(Application::APP_ID, self::KEY_REF, '0');
		$this->config->setAppValue(Application::APP_ID, self::KEY_TITLE_DAY, '');
		$this->config->setAppValue(Application::APP_ID, self::KEY_REF_DAY, '');
	}

	/** @return array{title: int, ref: int, title_day: string, ref_day: string} */
	public function status(): array {
		return [
			'title' => (int)$this->config->getAppValue(Application::APP_ID, self::KEY_TITLE, '0'),
			'ref' => (int)$this->config->getAppValue(Application::APP_ID, self::KEY_REF, '0'),
			'title_day' => $this->config->getAppValue(Application::APP_ID, self::KEY_TITLE_DAY, ''),
			'ref_day' => $this->config->getAppValue(Application::APP_ID, self::KEY_REF_DAY, ''),
		];
	}
}
