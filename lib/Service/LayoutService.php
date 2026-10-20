<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCA\BriefManager\Db\Layout;
use OCA\BriefManager\Db\LayoutMapper;
use OCA\BriefManager\Design\DesignRegistry;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Private Familienbrief-Layouts je authentifiziertem Benutzer.
 */
class LayoutService {
	private const MAX_LOGO = 4_000_000;

	public function __construct(
		private LayoutMapper $mapper,
		private IUserSession $session,
		private DesignRegistry $designs,
		private IAppData $appData,
		private IGroupManager $groups,
	) {
	}

	private function isAdmin(): bool {
		$user = $this->session->getUser();
		return $user !== null && $this->groups->isAdmin($user->getUID());
	}

	public function currentUserId(): ?string {
		return $this->session->getUser()?->getUID();
	}

	/** @return list<array<string, mixed>> */
	public function listMine(): array {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return [];
		}
		return array_map(static fn (Layout $l) => $l->jsonSerialize(), $this->mapper->findForUser($userId));
	}

	public function getOwned(int $id): ?Layout {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return null;
		}
		try {
			return $this->mapper->findOwned($id, $userId);
		} catch (DoesNotExistException) {
			return null;
		}
	}

	public function canUse(Layout $layout): bool {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return false;
		}
		return LayoutOwnership::canEdit($layout->getUserId(), $userId, $this->isAdmin());
	}

	public function canView(Layout $layout): bool {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return false;
		}
		return LayoutOwnership::canView($layout->getUserId(), $userId, $this->isAdmin());
	}

	/** @param array<string, mixed> $data */
	public function create(array $data): Layout {
		$userId = $this->currentUserId();
		if ($userId === null) {
			throw new \RuntimeException('Nicht angemeldet');
		}
		$layout = new Layout();
		$layout->setUserId($userId);
		$now = (new \DateTimeImmutable('now'))->format('Y-m-d H:i:s');
		$layout->setCreatedAt($now);
		$layout->setUpdatedAt($now);
		$this->apply($layout, $data, true);
		if (!empty($data['is_default'])) {
			$this->mapper->clearDefaultForUser($userId);
			$layout->setIsDefault(1);
		}
		return $this->mapper->insert($layout);
	}

	/** @param array<string, mixed> $data */
	public function update(Layout $layout, array $data): Layout {
		if (!$this->canUse($layout) || $layout->getUserId() !== $this->currentUserId()) {
			throw new \RuntimeException('Kein Zugriff auf dieses Layout.');
		}
		$this->apply($layout, $data, false);
		if (array_key_exists('is_default', $data) && !empty($data['is_default'])) {
			$this->mapper->clearDefaultForUser($layout->getUserId());
			$layout->setIsDefault(1);
		} elseif (array_key_exists('is_default', $data) && empty($data['is_default'])) {
			$layout->setIsDefault(0);
		}
		$layout->setUpdatedAt((new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
		return $this->mapper->update($layout);
	}

	public function delete(Layout $layout): void {
		$userId = $this->currentUserId() ?? '';
		if (!LayoutOwnership::canEdit($layout->getUserId(), $userId, false)) {
			throw new \RuntimeException('Kein Zugriff auf dieses Layout.');
		}
		$logo = trim((string)($layout->getLogoPath() ?? ''));
		if ($logo !== '') {
			$this->deleteLogoFile($logo);
		}
		$this->mapper->delete($layout);
	}

	public function storeLogoFromRequest(IRequest $request, Layout $layout): string {
		if ($layout->getUserId() !== $this->currentUserId()) {
			throw new \RuntimeException('Kein Zugriff auf dieses Layout.');
		}
		$uploaded = $request->getUploadedFile('logo');
		if (!is_array($uploaded) || ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
			throw new \InvalidArgumentException('Kein Logo empfangen.');
		}
		if (($uploaded['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			throw new \RuntimeException('Logo-Upload fehlgeschlagen.');
		}
		$tmp = (string)($uploaded['tmp_name'] ?? '');
		$size = (int)($uploaded['size'] ?? 0);
		if ($tmp === '' || !is_readable($tmp) || $size <= 0 || $size > self::MAX_LOGO) {
			throw new \InvalidArgumentException('Ungültige Logo-Datei (max. 4 MB).');
		}
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mime = (string)$finfo->file($tmp);
		$ext = match ($mime) {
			'image/png' => 'png',
			'image/jpeg', 'image/jpg' => 'jpg',
			'image/webp' => 'webp',
			'image/gif' => 'gif',
			default => throw new \InvalidArgumentException('Nur PNG/JPEG/WebP/GIF als Logo erlaubt.'),
		};
		$binary = (string)file_get_contents($tmp);
		$folder = $this->logosFolder();
		$fileName = 'layout-' . $layout->getId() . '.' . $ext;
		try {
			$file = $folder->getFile($fileName);
		} catch (NotFoundException) {
			$file = $folder->newFile($fileName);
		}
		$file->putContent($binary);
		$old = trim((string)($layout->getLogoPath() ?? ''));
		$key = 'layout-logos/' . $fileName;
		if ($old !== '' && $old !== $key) {
			$this->deleteLogoFile($old);
		}
		$layout->setLogoPath($key);
		$layout->setUpdatedAt((new \DateTimeImmutable('now'))->format('Y-m-d H:i:s'));
		$this->mapper->update($layout);
		return $key;
	}

	public function defaultForCurrentUser(): ?Layout {
		$userId = $this->currentUserId();
		if ($userId === null) {
			return null;
		}
		$def = $this->mapper->findDefaultForUser($userId);
		if ($def !== null) {
			return $def;
		}
		$list = $this->mapper->findForUser($userId);
		return $list[0] ?? null;
	}

	/** @param array<string, mixed> $data */
	private function apply(Layout $layout, array $data, bool $isCreate): void {
		$map = [
			'name' => 'setName',
			'design' => 'setDesign',
			'sender_name' => 'setSenderName',
			'tagline' => 'setTagline',
			'address_extra' => 'setAddressExtra',
			'street' => 'setStreet',
			'zip' => 'setZip',
			'city' => 'setCity',
			'phone' => 'setPhone',
			'email' => 'setEmail',
			'website' => 'setWebsite',
			'color_primary' => 'setColorPrimary',
			'color_gray' => 'setColorGray',
			'color_accent' => 'setColorAccent',
		];
		foreach ($map as $key => $setter) {
			if (!$isCreate && !array_key_exists($key, $data)) {
				continue;
			}
			$value = trim((string)($data[$key] ?? ''));
			if ($key === 'design') {
				$known = $this->designs->labels();
				if ($value === '' || !isset($known[$value])) {
					$value = $isCreate ? 'ft' : $layout->getDesign();
				}
			}
			if ($key === 'name' && $value === '') {
				$value = $isCreate ? 'Mein Briefpapier' : $layout->getName();
			}
			if (str_starts_with($key, 'color_')) {
				$value = $this->sanitizeHex($value);
			}
			$layout->$setter(mb_substr($value, 0, $key === 'tagline' || $key === 'website' || $key === 'address_extra' ? 255 : 190));
		}
	}

	private function sanitizeHex(string $value): string {
		$value = trim($value);
		if ($value === '') {
			return '';
		}
		if (preg_match('/^#?[0-9a-fA-F]{6}$/', $value) !== 1) {
			return '';
		}
		return str_starts_with($value, '#') ? strtoupper($value) : ('#' . strtoupper($value));
	}

	private function logosFolder(): \OCP\Files\SimpleFS\ISimpleFolder {
		try {
			return $this->appData->getFolder('layout-logos');
		} catch (NotFoundException) {
			return $this->appData->newFolder('layout-logos');
		}
	}

	private function deleteLogoFile(string $key): void {
		if (!str_starts_with($key, 'layout-logos/')) {
			return;
		}
		try {
			$this->logosFolder()->getFile(basename($key))->delete();
		} catch (\Throwable) {
		}
	}
}
