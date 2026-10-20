<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCP\Files\IAppData;
use OCP\Files\NotFoundException;
use OCP\IRequest;

/**
 * Internetmarken-PDFs (Deutsche Post) pro Brief in App-Daten.
 */
class StampService {
	private const MAX_BYTES = 8_000_000;

	public function __construct(
		private IAppData $appData,
	) {
	}

	/**
	 * Speichert PDF-Bytes (z. B. aus INTERNETMARKE-API-Download).
	 *
	 * @return string Relativer Schlüssel stamps/letter-{id}.pdf
	 */
	public function storeBinary(string $binary, int $letterId): string {
		if ($binary === '' || !str_starts_with($binary, '%PDF')) {
			throw new \RuntimeException('Internetmarke ist keine gültige PDF-Datei.');
		}
		if (strlen($binary) > self::MAX_BYTES) {
			throw new \InvalidArgumentException('Internetmarke zu groß (max. 8 MB).');
		}
		$folder = $this->stampsFolder();
		$fileName = 'letter-' . $letterId . '.pdf';
		try {
			$file = $folder->getFile($fileName);
		} catch (NotFoundException) {
			$file = $folder->newFile($fileName);
		}
		$file->putContent($binary);
		return 'stamps/' . $fileName;
	}

	/**
	 * @return string Relativer Schlüssel stamps/{id}.pdf
	 */
	public function storeFromRequest(IRequest $request, int $letterId): string {
		$uploaded = $request->getUploadedFile('internetmarke_pdf');
		if (!is_array($uploaded) || ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
			throw new \InvalidArgumentException('Keine Internetmarke empfangen.');
		}
		if (($uploaded['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
			throw new \RuntimeException('Internetmarke-Upload fehlgeschlagen (Code ' . (int)$uploaded['error'] . ').');
		}
		$tmp = (string)($uploaded['tmp_name'] ?? '');
		$size = (int)($uploaded['size'] ?? 0);
		if ($tmp === '' || !is_readable($tmp) || $size <= 0) {
			throw new \InvalidArgumentException('Ungültige Upload-Datei.');
		}
		if ($size > self::MAX_BYTES) {
			throw new \InvalidArgumentException('Internetmarke zu groß (max. 8 MB).');
		}
		$finfo = new \finfo(FILEINFO_MIME_TYPE);
		$mime = (string)$finfo->file($tmp);
		$head = (string)file_get_contents($tmp, false, null, 0, 5);
		if ($mime !== 'application/pdf' && $head !== '%PDF-') {
			throw new \InvalidArgumentException('Nur PDF-Dateien der Internetmarke sind erlaubt.');
		}
		$binary = (string)file_get_contents($tmp);
		if ($binary === '' || !str_starts_with($binary, '%PDF')) {
			throw new \RuntimeException('Internetmarke ist keine gültige PDF-Datei.');
		}

		$folder = $this->stampsFolder();
		$fileName = 'letter-' . $letterId . '.pdf';
		try {
			$file = $folder->getFile($fileName);
		} catch (NotFoundException) {
			$file = $folder->newFile($fileName);
		}
		$file->putContent($binary);
		return 'stamps/' . $fileName;
	}

	public function resolveToLocalPath(string $stampKey): ?string {
		$stampKey = trim($stampKey);
		if ($stampKey === '') {
			return null;
		}
		if (is_readable($stampKey) && str_starts_with((string)@file_get_contents($stampKey, false, null, 0, 5), '%PDF')) {
			return $stampKey;
		}
		if (!str_starts_with($stampKey, 'stamps/')) {
			return null;
		}
		$name = basename($stampKey);
		try {
			$file = $this->stampsFolder()->getFile($name);
		} catch (NotFoundException) {
			return null;
		}
		$content = $file->getContent();
		if ($content === '' || $content === false || !str_starts_with((string)$content, '%PDF')) {
			return null;
		}
		$tmp = tempnam(sys_get_temp_dir(), 'bmstamp_');
		if ($tmp === false) {
			return null;
		}
		$target = $tmp . '.pdf';
		@rename($tmp, $target);
		if (@file_put_contents($target, $content) === false) {
			@unlink($target);
			return null;
		}
		return $target;
	}

	public function delete(string $stampKey): void {
		$stampKey = trim($stampKey);
		if ($stampKey === '' || !str_starts_with($stampKey, 'stamps/')) {
			return;
		}
		try {
			$this->stampsFolder()->getFile(basename($stampKey))->delete();
		} catch (\Throwable) {
			// bereits weg
		}
	}

	private function stampsFolder(): \OCP\Files\SimpleFS\ISimpleFolder {
		try {
			return $this->appData->getFolder('stamps');
		} catch (NotFoundException) {
			return $this->appData->newFolder('stamps');
		}
	}
}
