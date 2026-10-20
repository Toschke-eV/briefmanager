<?php

declare(strict_types=1);

namespace OCA\BriefManager\Controller;

use OCA\BriefManager\Service\LetterService;
use OCA\BriefManager\Service\TrackingService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * Einschreiben-Rückverfolgung (Deutsche Post).
 */
class TrackingController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private LetterService $letters,
		private TrackingService $tracking,
		private IURLGenerator $url,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function refresh(int $id): RedirectResponse {
		$letter = $this->letters->get($id);
		if ($letter === null || !$this->letters->canEdit($letter)) {
			return $this->back(null, 'Brief nicht gefunden.', true);
		}
		try {
			$fetched = $this->tracking->refresh($letter, true);
			$status = (string)($fetched['status'] ?? $letter->getTrackingStatus());
			$msg = $status !== ''
				? 'Sendungsstatus: ' . $status
				: 'Sendungsverfolgung aktualisiert.';
			return $this->back($id, $msg);
		} catch (\Throwable $e) {
			return $this->back($id, $e->getMessage(), true);
		}
	}

	#[NoAdminRequired]
	public function receipt(int $id): RedirectResponse {
		$letter = $this->letters->get($id);
		if ($letter === null || !$this->letters->canEdit($letter)) {
			return $this->back(null, 'Brief nicht gefunden.', true);
		}
		try {
			$this->tracking->fetchReceipt($letter);
			return $this->back($id, 'Zustellbeleg gespeichert.');
		} catch (\Throwable $e) {
			return $this->back($id, $e->getMessage(), true);
		}
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function receiptDownload(int $id): DataDownloadResponse|DataResponse {
		$letter = $this->letters->get($id);
		if ($letter === null || !$this->letters->canAccess($letter)) {
			return new DataResponse(['error' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		$key = trim((string)($letter->getTrackingReceipt() ?? ''));
		if ($key === '') {
			return new DataResponse(['error' => 'Kein Beleg vorhanden'], Http::STATUS_NOT_FOUND);
		}
		$path = $this->tracking->resolveReceiptToLocalPath($key);
		if ($path === null) {
			return new DataResponse(['error' => 'Belegdatei fehlt'], Http::STATUS_NOT_FOUND);
		}
		try {
			$binary = (string)file_get_contents($path);
			$name = 'zustellbeleg-' . preg_replace('/[^A-Z0-9]/i', '', $letter->getSendungsnummer()) . '.pdf';
			$response = new DataDownloadResponse($binary, $name !== '.pdf' ? $name : 'zustellbeleg.pdf', 'application/pdf');
			$response->addHeader('Content-Disposition', 'inline; filename="' . $name . '"');
			return $response;
		} finally {
			if (str_contains($path, sys_get_temp_dir())) {
				@unlink($path);
			}
		}
	}

	private function back(?int $id, string $msg, bool $error = false): RedirectResponse {
		$params = [
			'msg' => $msg,
			'err' => $error ? '1' : '0',
		];
		if ($id !== null) {
			$params['id'] = $id;
		}
		try {
			$url = $this->url->linkToRoute('briefmanager.page.index', $params);
		} catch (\Throwable) {
			$url = '';
		}
		if ($url === '') {
			$url = '/apps/briefmanager/?' . http_build_query($params);
		}
		return new RedirectResponse($url);
	}
}
