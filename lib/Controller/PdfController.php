<?php

declare(strict_types=1);

namespace OCA\BriefManager\Controller;

use OCA\BriefManager\Db\Letter;
use OCA\BriefManager\Service\LetterService;
use OCA\BriefManager\Service\PdfService;
use OCA\BriefManager\Service\StorageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;
use OCP\AppFramework\Http\DataDownloadResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

class PdfController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private LetterService $letters,
		private PdfService $pdf,
		private StorageService $storage,
		private IURLGenerator $url,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function download(int $id): DataDownloadResponse|DataResponse {
		return $this->pdfResponse($id, false);
	}

	/** Inline-PDF für eingebettete Vorschau (Safari/iframe/object). */
	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function inline(int $id): DataDownloadResponse|DataResponse {
		return $this->pdfResponse($id, true);
	}

	#[NoAdminRequired]
	public function save(int $id): RedirectResponse {
		$letter = $this->letters->get($id);
		if ($letter === null || !$this->letters->canEdit($letter)) {
			return $this->back(null, 'Brief nicht gefunden.', true);
		}
		try {
			$binary = $this->pdf->render($letter);
			$stored = $this->persist($letter, $binary);
			return $this->back($id, 'PDF gespeichert unter Dateien/' . $stored['path']);
		} catch (\Throwable $e) {
			return $this->back($id, $e->getMessage(), true);
		}
	}

	private function pdfResponse(int $id, bool $inline): DataDownloadResponse|DataResponse {
		$letter = $this->letters->get($id);
		if ($letter === null || !$this->letters->canAccess($letter)) {
			return new DataResponse(['error' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		try {
			$binary = $this->pdf->render($letter);
			$this->persist($letter, $binary);
			$name = preg_replace('/[^A-Za-z0-9._-]+/', '_', $letter->getTitle()) ?: ('brief-' . $id);
			$fileName = $name . '.pdf';
			$response = new DataDownloadResponse($binary, $fileName, 'application/pdf');
			$disposition = $inline ? 'inline' : 'attachment';
			$response->addHeader('Content-Disposition', $disposition . '; filename="' . $fileName . '"');
			$response->addHeader('X-Content-Type-Options', 'nosniff');
			$response->addHeader('Cache-Control', 'private, max-age=60');
			return $response;
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}

	/** @return array{path: string, file_id: int, files_url: string, viewer_url: string, office_url: string} */
	private function persist(Letter $letter, string $binary): array {
		$stored = $this->storage->storePdf($letter, $binary);
		$path = $stored['path'];
		$current = $letter->getPdfFile();
		if ($current === $path) {
			$letter->setPdfFile($path . ' ');
		}
		$letter->setPdfFile($path);
		$this->letters->saveEntity($letter);
		return $stored;
	}

	private function back(?int $id, string $msg, bool $error = false): RedirectResponse {
		$params = ['msg' => $msg, 'err' => $error ? '1' : '0'];
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
