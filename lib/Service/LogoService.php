<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IRequest;

/**
 * Vereinslogos in App-Daten (kein festes Preset-Logo nötig).
 */
class LogoService {
	private const MAX_BYTES = 4_000_000;

	/** @var array<string, string> */
	private const MIME_EXT = [
		'image/png' => 'png',
		'image/jpeg' => 'jpg',
		'image/jpg' => 'jpg',
		'image/gif' => 'gif',
		'image/webp' => 'webp',
	];

	public function __construct(
		private IAppData $appData,
	) {
	}

	/**
	 * @return string Relativer Schlüssel logos/{id}.{ext}
	 */
	public function storeFromRequest(IRequest $request, string $orgId): string {
		$uploaded = $request->getUploadedFile('logo');
		if (!is_array($uploaded) || ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
			throw new \InvalidArgumentException('Keine Logo-Datei empfangen.');
		}
		if (($uploaded['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			throw new \RuntimeException('Logo-Upload fehlgeschlagen (Code ' . (int)$uploaded['error'] . ').');
		}
		$tmp = (string)($uploaded['tmp_name'] ?? '');
		$size = (int)($uploaded['size'] ?? 0);
		if ($tmp === '' || !is_readable($tmp) || $size <= 0) {
			throw new \InvalidArgumentException('Ungültige Upload-Datei.');
		}
		if ($size > self::MAX_BYTES) {
			throw new \InvalidArgumentException('Logo zu groß (max. 4 MB).');
		}
		$mime = (string)($uploaded['type'] ?? '');
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$detected = (string)$finfo->file($tmp);
		$useMime = isset(self::MIME_EXT[$detected]) ? $detected : (isset(self::MIME_EXT[$mime]) ? $mime : '');
		if ($useMime === '') {
			throw new \InvalidArgumentException('Nur PNG, JPEG, GIF oder WebP erlaubt.');
		}
		$ext = self::MIME_EXT[$useMime];
		$binary = (string)file_get_contents($tmp);
		if ($binary === '') {
			throw new \RuntimeException('Logo-Datei leer.');
		}

		$folder = $this->logosFolder();
		$fileName = preg_replace('/[^a-zA-Z0-9_-]/', '', $orgId) . '.' . $ext;
		if ($fileName === '.' . $ext || $fileName === '') {
			$fileName = 'logo.' . $ext;
		}
		foreach ($folder->getDirectoryListing() as $existing) {
			$name = $existing->getName();
			$base = pathinfo($name, PATHINFO_FILENAME);
			$want = pathinfo($fileName, PATHINFO_FILENAME);
			if ($base === $want && $name !== $fileName) {
				$existing->delete();
			}
		}
		try {
			$file = $folder->getFile($fileName);
		} catch (NotFoundException) {
			$file = $folder->newFile($fileName);
		}
		$file->putContent($binary);
		return 'logos/' . $fileName;
	}

	/** AppData-Logo in temporäre Datei für TCPDF. */
	public function resolveToLocalPath(string $logoKey): ?string {
		$logoKey = trim($logoKey);
		if ($logoKey === '') {
			return null;
		}
		if (is_readable($logoKey)) {
			return $logoKey;
		}
		$folderName = null;
		if (str_starts_with($logoKey, 'logos/')) {
			$folderName = 'logos';
		} elseif (str_starts_with($logoKey, 'layout-logos/')) {
			$folderName = 'layout-logos';
		} else {
			return null;
		}
		$name = basename($logoKey);
		try {
			$file = $this->appDataFolder($folderName)->getFile($name);
		} catch (NotFoundException) {
			return null;
		}
		$content = $file->getContent();
		if ($content === '' || $content === false) {
			return null;
		}
		$ext = pathinfo($name, PATHINFO_EXTENSION) ?: 'png';
		$tmp = tempnam(sys_get_temp_dir(), 'bmlogo_');
		if ($tmp === false) {
			return null;
		}
		$target = $tmp . '.' . $ext;
		@rename($tmp, $target);
		if (@file_put_contents($target, $content) === false) {
			@unlink($target);
			return null;
		}
		return $target;
	}

	/** data:-URI für Admin-Vorschau, oder null. */
	public function asDataUri(string $logoKey): ?string {
		$logoKey = trim($logoKey);
		if ($logoKey === '') {
			return null;
		}
		$binary = null;
		$mime = 'image/png';
		if (is_readable($logoKey)) {
			$binary = (string)file_get_contents($logoKey);
			$finfo = new \finfo(FILEINFO_MIME_TYPE);
			$mime = (string)$finfo->file($logoKey) ?: $mime;
		} elseif (str_starts_with($logoKey, 'logos/') || str_starts_with($logoKey, 'layout-logos/')) {
			try {
				$folder = str_starts_with($logoKey, 'layout-logos/') ? 'layout-logos' : 'logos';
				$file = $this->appDataFolder($folder)->getFile(basename($logoKey));
				$binary = $file->getContent();
				$ext = strtolower(pathinfo($logoKey, PATHINFO_EXTENSION));
				$mime = match ($ext) {
					'jpg', 'jpeg' => 'image/jpeg',
					'gif' => 'image/gif',
					'webp' => 'image/webp',
					default => 'image/png',
				};
			} catch (\Throwable) {
				return null;
			}
		}
		if ($binary === null || $binary === '' || $binary === false) {
			return null;
		}
		return 'data:' . $mime . ';base64,' . base64_encode((string)$binary);
	}

	private function logosFolder(): \OCP\Files\SimpleFS\ISimpleFolder {
		return $this->appDataFolder('logos');
	}

	private function appDataFolder(string $name): \OCP\Files\SimpleFS\ISimpleFolder {
		try {
			return $this->appData->getFolder($name);
		} catch (NotFoundException) {
			return $this->appData->newFolder($name);
		}
	}
}
