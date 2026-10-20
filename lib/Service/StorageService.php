<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCA\BriefManager\Db\Letter;
use OCP\Files\Folder;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\IUserSession;
use OCP\IURLGenerator;

/**
 * Speichert Brief-PDFs im Benutzer-Dateisystem (WebDAV/Files).
 * Ordner: Briefe/{Jahr}/
 */
class StorageService {
	public const ROOT_FOLDER = 'Briefe';

	public function __construct(
		private IRootFolder $rootFolder,
		private IUserSession $userSession,
		private IURLGenerator $url,
	) {
	}

	/**
	 * Speichert PDF und liefert relative Pfadangabe + Files-URL.
	 *
	 * @return array{path: string, file_id: int, files_url: string, viewer_url: string, office_url: string}
	 */
	public function storePdf(Letter $letter, string $binary): array {
		$uid = $this->ownerUid($letter);
		$folder = $this->ensureYearFolder($uid);
		$fileName = $this->safeFileName($letter);
		try {
			try {
				$file = $folder->get($fileName);
				if ($file instanceof Folder) {
					throw new \RuntimeException('Dateiname kollidiert mit Ordner: ' . $fileName);
				}
			} catch (NotFoundException) {
				$file = $folder->newFile($fileName);
			}
			$file->putContent($binary);
		} catch (NotPermittedException $e) {
			throw new \RuntimeException('Keine Schreibrechte im Ordner Briefe: ' . $e->getMessage(), 0, $e);
		}

		$path = self::ROOT_FOLDER . '/' . date('Y') . '/' . $fileName;
		return $this->metaForFileId($file->getId(), $path);
	}

	/**
	 * @return array{path: string, file_id: int, files_url: string, viewer_url: string, office_url: string}|null
	 */
	public function resolve(Letter $letter): ?array {
		$path = trim((string)($letter->getPdfFile() ?? ''));
		if ($path === '') {
			return null;
		}
		try {
			$uid = $this->ownerUid($letter);
			$node = $this->rootFolder->getUserFolder($uid)->get($path);
			if ($node instanceof Folder) {
				return null;
			}
			return $this->metaForFileId($node->getId(), $path);
		} catch (\Throwable) {
			return null;
		}
	}

	public function folderUrl(): string {
		$url = '';
		try {
			$url = $this->url->linkToRoute('files.view.index', [
				'dir' => '/' . self::ROOT_FOLDER,
			]);
		} catch (\Throwable) {
			$url = '';
		}
		return $url !== '' ? $url : ('/apps/files/files?dir=/' . rawurlencode(self::ROOT_FOLDER));
	}

	/** Entfernt die abgelegte Brief-PDF-Datei, falls vorhanden. */
	public function deletePdf(Letter $letter): void {
		$path = trim((string)($letter->getPdfFile() ?? ''));
		if ($path === '') {
			return;
		}
		try {
			$uid = $this->ownerUid($letter);
			$node = $this->rootFolder->getUserFolder($uid)->get($path);
			if (!$node instanceof Folder) {
				$node->delete();
			}
		} catch (\Throwable) {
			// Datei schon weg oder keine Rechte
		}
	}

	/**
	 * @return array{path: string, file_id: int, files_url: string, viewer_url: string, office_url: string}
	 */
	private function metaForFileId(int $fileId, string $path): array {
		$office = '';
		try {
			$office = $this->url->linkToRoute('richdocuments.document.editOnline', [
				'path' => $path,
			]);
		} catch (\Throwable) {
			$office = '';
		}
		if ($office === '') {
			// editOnline hat NoCSRFRequired; index?fileId=… scheitert ohne Token
			$office = '/apps/richdocuments/editonline?path=' . rawurlencode($path);
		}
		return [
			'path' => $path,
			'file_id' => $fileId,
			'files_url' => $this->filesUrl($fileId),
			'viewer_url' => '/f/' . $fileId,
			'office_url' => $office,
		];
	}

	private function filesUrl(int $fileId): string {
		try {
			$url = $this->url->linkToRoute('files.view.showfile', ['fileid' => $fileId]);
			if (is_string($url) && $url !== '') {
				return $url;
			}
		} catch (\Throwable) {
			// Fallback
		}
		return '/f/' . $fileId;
	}

	public function ensureRootFolder(?string $uid = null): Folder {
		$uid = $uid ?? $this->currentUid();
		$userFolder = $this->rootFolder->getUserFolder($uid);
		try {
			$node = $userFolder->get(self::ROOT_FOLDER);
			if ($node instanceof Folder) {
				return $node;
			}
			throw new \RuntimeException('„Briefe“ existiert als Datei, nicht als Ordner.');
		} catch (NotFoundException) {
			return $userFolder->newFolder(self::ROOT_FOLDER);
		}
	}

	private function ensureYearFolder(string $uid): Folder {
		$root = $this->ensureRootFolder($uid);
		$year = date('Y');
		try {
			$node = $root->get($year);
			if ($node instanceof Folder) {
				return $node;
			}
			throw new \RuntimeException('Jahresordner ist eine Datei: ' . $year);
		} catch (NotFoundException) {
			return $root->newFolder($year);
		}
	}

	private function ownerUid(Letter $letter): string {
		$uid = $letter->getUserId();
		if ($uid !== '') {
			return $uid;
		}
		return $this->currentUid();
	}

	private function currentUid(): string {
		$user = $this->userSession->getUser();
		if ($user === null) {
			throw new \RuntimeException('Nicht angemeldet');
		}
		return $user->getUID();
	}

	private function safeFileName(Letter $letter): string {
		$base = preg_replace('/[^A-Za-z0-9._-]+/', '_', $letter->getTitle()) ?: ('brief-' . $letter->getId());
		$base = trim((string)$base, '._-');
		if ($base === '') {
			$base = 'brief-' . $letter->getId();
		}
		return $base . '.pdf';
	}
}
