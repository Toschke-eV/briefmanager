<?php

declare(strict_types=1);

namespace OCA\BriefManager\Controller;

use OCA\BriefManager\Service\LetterService;
use OCA\BriefManager\Service\OrgAssignmentService;
use OCA\BriefManager\Service\PdfService;
use OCA\BriefManager\Service\StampService;
use OCA\BriefManager\Service\StorageService;
use OCA\BriefManager\Service\TrackingService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\IRequest;
use OCP\IURLGenerator;

/**
 * Klassische Formular-Endpunkte ohne JavaScript.
 */
class FormController extends Controller {
	public function __construct(
		string $appName,
		IRequest $request,
		private LetterService $letters,
		private PdfService $pdf,
		private StorageService $storage,
		private StampService $stamps,
		private TrackingService $tracking,
		private OrgAssignmentService $assignments,
		private IURLGenerator $url,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function create(): RedirectResponse {
		try {
			$letter = $this->letters->create($this->request->getParams());
			return $this->redirect($letter->getId(), 'Brief angelegt.');
		} catch (\Throwable $e) {
			return $this->redirect(null, $e->getMessage(), true);
		}
	}

	#[NoAdminRequired]
	public function update(int $id): RedirectResponse {
		$letter = $this->letters->get($id);
		if ($letter === null || !$this->letters->canEdit($letter)) {
			return $this->redirect(null, 'Brief nicht gefunden.', true);
		}
		$params = $this->request->getParams();
		$oldStamp = trim((string)($letter->getInternetmarkePath() ?? ''));

		if (!empty($params['internetmarke_remove'])) {
			if ($oldStamp !== '') {
				$this->stamps->delete($oldStamp);
			}
			$params['internetmarke_path'] = '';
			$params['internetmarke_remove'] = '1';
		}

		$letter = $this->letters->update($letter, $params);

		$uploaded = $this->request->getUploadedFile('internetmarke_pdf');
		if (is_array($uploaded) && ($uploaded['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
			try {
				if ($oldStamp !== '' && empty($params['internetmarke_remove'])) {
					$this->stamps->delete($oldStamp);
				}
				$key = $this->stamps->storeFromRequest($this->request, $id);
				$letter->setInternetmarkePath($key);
				$parsed = $this->tracking->extractFromStampKey($key);
				if ($letter->getSendungsnummer() === '' && $parsed['sendungsnummer'] !== '') {
					$letter->setSendungsnummer($parsed['sendungsnummer']);
				}
				if ($letter->getEmpfaengerPlz() === '' && $parsed['plz'] !== '') {
					$letter->setEmpfaengerPlz($parsed['plz']);
				}
				$this->letters->saveEntity($letter);
			} catch (\Throwable $e) {
				return $this->redirect($id, 'Gespeichert, Internetmarke fehlgeschlagen: ' . $e->getMessage(), true);
			}
		} elseif ($letter->getSendungsnummer() === '' || $letter->getEmpfaengerPlz() === '') {
			$stampKey = trim((string)($letter->getInternetmarkePath() ?? ''));
			if ($stampKey !== '') {
				try {
					$parsed = $this->tracking->extractFromStampKey($stampKey);
					$changed = false;
					if ($letter->getSendungsnummer() === '' && $parsed['sendungsnummer'] !== '') {
						$letter->setSendungsnummer($parsed['sendungsnummer']);
						$changed = true;
					}
					if ($letter->getEmpfaengerPlz() === '' && $parsed['plz'] !== '') {
						$letter->setEmpfaengerPlz($parsed['plz']);
						$changed = true;
					}
					if ($changed) {
						$this->letters->saveEntity($letter);
					}
				} catch (\Throwable) {
					// Marke bleibt, Extraktion optional
				}
			}
			if ($letter->getEmpfaengerPlz() === '' && preg_match('/\b(\d{5})\b/', $letter->getEmpfaenger(), $m)) {
				$letter->setEmpfaengerPlz($m[1]);
				$this->letters->saveEntity($letter);
			}
		}

		$trackAfter = (string)$this->request->getParam('track_after_save', '') === '1';
		$msg = 'Gespeichert.';
		try {
			$binary = $this->pdf->render($letter);
			$stored = $this->storage->storePdf($letter, $binary);
			$path = $stored['path'];
			if ($letter->getPdfFile() === $path) {
				$letter->setPdfFile($path . ' ');
			}
			$letter->setPdfFile($path);
			$this->letters->saveEntity($letter);
			$msg = 'Gespeichert und PDF unter Dateien/' . $path . ' abgelegt.';
		} catch (\Throwable $e) {
			$msg = 'Gespeichert, PDF-Ablage fehlgeschlagen: ' . $e->getMessage();
		}

		if ($trackAfter) {
			try {
				$fetched = $this->tracking->refresh($letter, true);
				$status = (string)($fetched['status'] ?? $letter->getTrackingStatus());
				$msg .= $status !== '' ? ' Sendungsstatus: ' . $status : ' Sendungsverfolgung aktualisiert.';
			} catch (\Throwable $e) {
				return $this->redirect($id, $msg . ' Rückverfolgung: ' . $e->getMessage(), true);
			}
		}
		return $this->redirect($id, $msg);
	}

	#[NoAdminRequired]
	public function destroy(int $id): RedirectResponse {
		$letter = $this->letters->get($id);
		if ($letter === null || !$this->letters->canEdit($letter)) {
			return $this->redirect(null, 'Brief nicht gefunden.', true);
		}
		$this->storage->deletePdf($letter);
		$stamp = trim((string)($letter->getInternetmarkePath() ?? ''));
		if ($stamp !== '') {
			$this->stamps->delete($stamp);
		}
		$this->letters->delete($letter);
		return $this->redirect(null, 'Brief gelöscht.');
	}

	#[NoAdminRequired]
	public function selectOrg(): RedirectResponse {
		$id = trim((string)$this->request->getParam('organisation_id', ''));
		try {
			$this->assignments->selectForCurrentUser($id);
			$selectedId = (int)$this->request->getParam('id', 0);
			return $this->redirect($selectedId > 0 ? $selectedId : null, 'Verein gewechselt.');
		} catch (\Throwable $e) {
			return $this->redirect(null, $e->getMessage(), true);
		}
	}

	private function redirect(?int $id, string $msg, bool $error = false): RedirectResponse {
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
			$q = http_build_query($params);
			$url = '/apps/briefmanager/' . ($q !== '' ? ('?' . $q) : '');
		}
		return new RedirectResponse($url);
	}
}
