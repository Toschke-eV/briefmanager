<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCA\BriefManager\Db\Letter;
use OCA\BriefManager\Db\LetterMapper;
use OCP\IGroupManager;
use OCP\IUserSession;

class LetterService {
	public function __construct(
		private LetterMapper $mapper,
		private VariantService $variants,
		private OrgAssignmentService $assignments,
		private CounterService $counters,
		private IUserSession $userSession,
		private IGroupManager $groupManager,
		private LayoutService $layouts,
	) {
	}

	public function currentUserId(): ?string {
		$user = $this->userSession->getUser();
		return $user?->getUID();
	}

	public function isAdmin(): bool {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return false;
		}
		return $this->groupManager->isAdmin($user->getUID());
	}

	/** @return array<int, array<string, mixed>> */
	public function listForCurrentUser(): array {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return [];
		}
		$letters = $this->isAdmin()
			? $this->mapper->findAll()
			: $this->mapper->findForUser($userId, true);
		$out = [];
		foreach ($letters as $letter) {
			$variant = $this->variants->get($letter->getVariantId());
			$out[] = $letter->jsonSerialize($variant?->getName());
		}
		return $out;
	}

	public function get(int $id): ?Letter {
		return $this->mapper->tryFind($id);
	}

	public function canAccess(Letter $letter): bool {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return false;
		}
		if ($this->isAdmin() || $letter->getUserId() === $userId) {
			return true;
		}
		return $letter->getVisibility() === 'team';
	}

	public function canEdit(Letter $letter): bool {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return false;
		}
		return $this->isAdmin() || $letter->getUserId() === $userId;
	}

	/** @param array<string, mixed> $data */
	public function create(array $data): Letter {
		$userId = $this->currentUserId();
		if ($userId === null) {
			throw new \RuntimeException('Nicht angemeldet');
		}
		$this->variants->ensureSeed();
		$letter = new Letter();
		$letter->setUserId($userId);
		$now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
		$letter->setCreatedAt($now);
		$letter->setUpdatedAt($now);
		$this->applyPayload($letter, $data, true);
		if ($letter->getTitle() === '') {
			$letter->setTitle($this->nextTitle());
		}
		if ($letter->getUnserZeichen() === '') {
			$letter->setUnserZeichen($this->nextReference());
		}
		if ($letter->getDatum() === '') {
			$letter->setDatum((new \DateTimeImmutable('now'))->format('d.m.Y'));
		}
		// Entity::setter bricht ab, wenn Wert === PHP-Default (z. B. '').
		// Dann fehlen Spalten im INSERT und MySQL Strict meldet "no default value".
		$this->forceDirtyForInsert($letter);
		return $this->mapper->insert($letter);
	}

	/**
	 * Stellt sicher, dass alle NOT-NULL-Spalten im INSERT landen.
	 */
	private function forceDirtyForInsert(Letter $letter): void {
		$fields = [
			'empfaenger' => $letter->getEmpfaenger(),
			'brieftext' => $letter->getBrieftext(),
			'betreff' => $letter->getBetreff(),
			'anrede' => $letter->getAnrede(),
			'schluss' => $letter->getSchluss(),
			'title' => $letter->getTitle(),
			'contentFormat' => $letter->getContentFormat(),
			'unserZeichen' => $letter->getUnserZeichen(),
			'ihrSchreibenVom' => $letter->getIhrSchreibenVom(),
			'ihrZeichen' => $letter->getIhrZeichen(),
			'datum' => $letter->getDatum(),
			'sachbearbeiter' => $letter->getSachbearbeiter(),
			'telefon' => $letter->getTelefon(),
			'email' => $letter->getEmail(),
			'windowMode' => $letter->getWindowMode(),
			'visibility' => $letter->getVisibility(),
			'status' => $letter->getStatus(),
			'signaturen' => $letter->getSignaturen() ?? '[]',
			'sendungsnummer' => $letter->getSendungsnummer(),
			'empfaengerPlz' => $letter->getEmpfaengerPlz(),
			'trackingStatus' => $letter->getTrackingStatus(),
			'trackingDelivered' => $letter->getTrackingDelivered(),
			'trackingChecked' => $letter->getTrackingChecked(),
		];
		foreach ($fields as $prop => $value) {
			$setter = 'set' . ucfirst($prop);
			$placeholder = is_string($value) ? ($value === '' ? '_' : $value . '_') : $value;
			$letter->$setter($placeholder);
			$letter->$setter($value === null ? '' : $value);
		}
		// variantId: Default 0, muss >0 sein und dirty
		$vid = $letter->getVariantId();
		$letter->setVariantId($vid < 1 ? 1 : ($vid === 1 ? 2 : 1));
		$letter->setVariantId($vid < 1 ? 1 : $vid);
	}

	/** @param array<string, mixed> $data */
	public function update(Letter $letter, array $data): Letter {
		$this->applyPayload($letter, $data, false);
		$letter->setUpdatedAt((new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
		return $this->mapper->update($letter);
	}

	public function saveEntity(Letter $letter): Letter {
		$letter->setUpdatedAt((new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
		return $this->mapper->update($letter);
	}

	public function delete(Letter $letter): void {
		$this->mapper->delete($letter);
	}

	/** @return Letter[] */
	public function listEntities(): array {
		return $this->mapper->findAll();
	}

	/** @return array<int, array<string, mixed>> */
	public function listWithTracking(): array {
		$out = [];
		foreach ($this->mapper->findWithTracking() as $letter) {
			if (!$this->canAccess($letter)) {
				continue;
			}
			$variant = $this->variants->get($letter->getVariantId());
			$out[] = $letter->jsonSerialize($variant?->getName());
		}
		return $out;
	}

	public function countByVariant(int $variantId): int {
		return $this->mapper->countByVariant($variantId);
	}

	/** @param array<string, mixed> $data */
	private function applyPayload(Letter $letter, array $data, bool $isCreate): void {
		$map = [
			'variant_id' => 'setVariantId',
			'layout_id' => 'setLayoutId',
			'title' => 'setTitle',
			'empfaenger' => 'setEmpfaenger',
			'betreff' => 'setBetreff',
			'anrede' => 'setAnrede',
			'brieftext' => 'setBrieftext',
			'content_format' => 'setContentFormat',
			'schluss' => 'setSchluss',
			'unser_zeichen' => 'setUnserZeichen',
			'ihr_schreiben_vom' => 'setIhrSchreibenVom',
			'ihr_zeichen' => 'setIhrZeichen',
			'datum' => 'setDatum',
			'sachbearbeiter' => 'setSachbearbeiter',
			'telefon' => 'setTelefon',
			'email' => 'setEmail',
			'window_mode' => 'setWindowMode',
			'visibility' => 'setVisibility',
			'status' => 'setStatus',
			'sendungsnummer' => 'setSendungsnummer',
			'empfaenger_plz' => 'setEmpfaengerPlz',
		];
		foreach ($map as $key => $setter) {
			if (!$isCreate && !array_key_exists($key, $data)) {
				continue;
			}
			if ($isCreate && !array_key_exists($key, $data) && in_array($key, ['variant_id', 'empfaenger', 'brieftext'], true) === false) {
				continue;
			}
			$value = $data[$key] ?? match ($key) {
				'variant_id' => 1,
				'empfaenger', 'brieftext' => '',
				'content_format' => 'plain',
				'schluss' => 'Mit freundlichen Grüßen',
				'anrede' => 'Sehr geehrte Damen und Herren,',
				'visibility' => 'private',
				'status' => 'entwurf',
				'window_mode' => 'sichtfenster',
				'ihr_zeichen' => '-',
				default => '',
			};
			if ($key === 'variant_id') {
				$letter->setVariantId((int)$value);
				continue;
			}
			if ($key === 'layout_id') {
				$id = (int)$value;
				if ($id < 1) {
					$letter->setLayoutId(null);
					continue;
				}
				$layout = $this->layouts->getOwned($id);
				$letterOwner = $letter->getUserId() !== '' ? $letter->getUserId() : ($this->currentUserId() ?? '');
				if ($layout === null
					|| !LayoutOwnership::canAssignToLetter($layout->getUserId(), $letterOwner)
				) {
					throw new \RuntimeException('Layout gehört nicht zum Brief-Eigentümer.');
				}
				$letter->setLayoutId($id);
				continue;
			}
			if ($key === 'visibility') {
				$letter->setVisibility(in_array($value, ['private', 'team'], true) ? (string)$value : 'private');
				continue;
			}
			if ($key === 'signaturen') {
				continue;
			}
			if ($key === 'sendungsnummer') {
				$clean = strtoupper(preg_replace('/[^A-Z0-9]/i', '', (string)$value) ?? '');
				$letter->setSendungsnummer($clean);
				continue;
			}
			if ($key === 'empfaenger_plz') {
				$letter->setEmpfaengerPlz(preg_replace('/\D+/', '', (string)$value) ?? '');
				continue;
			}
			$letter->$setter(is_string($value) ? $value : (string)$value);
		}
		if (array_key_exists('signaturen', $data)) {
			$letter->setSignaturen(json_encode($data['signaturen'] ?? [], JSON_UNESCAPED_UNICODE) ?: '[]');
		} elseif ($isCreate) {
			$letter->setSignaturen('[]');
		}
		if (array_key_exists('internetmarke_path', $data)) {
			$path = trim((string)$data['internetmarke_path']);
			$letter->setInternetmarkePath($path !== '' ? $path : null);
		}
		if (array_key_exists('internetmarke_remove', $data) && (string)$data['internetmarke_remove'] === '1') {
			$letter->setInternetmarkePath(null);
		}
		if ($letter->getVariantId() < 1) {
			$variants = $this->variants->list(true);
			$letter->setVariantId((int)($variants[0]['id'] ?? 1));
		}
	}

	private function nextTitle(): string {
		$org = $this->assignments->activeOrgForCurrentUser();
		$pad = (int)$org['title_pad'];
		$day = (new \DateTimeImmutable('now'))->format('Ymd');
		$n = $this->counters->nextTitleNumber();
		return $day . '-' . str_pad((string)$n, $pad, '0', STR_PAD_LEFT);
	}

	private function nextReference(): string {
		$org = $this->assignments->activeOrgForCurrentUser();
		$pattern = (string)$org['ref_pattern'];
		$now = new \DateTimeImmutable('now');
		$pad = (int)$org['ref_pad'];
		$n = $this->counters->nextRefNumber((string)($org['ref_reset'] ?? 'daily'));
		$repl = [
			'{prefix}' => (string)$org['ref_prefix'],
			'{vorab}' => (string)$org['ref_vorab'],
			'{Y}' => $now->format('Y'),
			'{m}' => $now->format('m'),
			'{d}' => $now->format('d'),
			'{Ymd}' => $now->format('Ymd'),
			'{n}' => str_pad((string)$n, $pad, '0', STR_PAD_LEFT),
			'{title}' => '',
		];
		return strtr($pattern, $repl);
	}
}
