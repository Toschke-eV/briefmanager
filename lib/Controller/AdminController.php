<?php

declare(strict_types=1);

namespace OCA\BriefManager\Controller;

use OCA\BriefManager\Service\CounterService;
use OCA\BriefManager\Service\LetterService;
use OCA\BriefManager\Service\OrgAssignmentService;
use OCA\BriefManager\Service\StampService;
use OCA\BriefManager\Service\StorageService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

/**
 * Admin-Aktionen: Counter, Brief-Bereinigung, Benutzerzuordnung.
 */
class AdminController extends Controller {
	use RequestPayloadTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private LetterService $letters,
		private CounterService $counters,
		private StorageService $storage,
		private StampService $stamps,
		private OrgAssignmentService $assignments,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function counters(): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse(['counters' => $this->counters->status()]);
	}

	#[NoAdminRequired]
	public function resetCounters(): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		$this->counters->reset();
		return new DataResponse([
			'ok' => true,
			'counters' => $this->counters->status(),
			'message' => 'Zähler zurückgesetzt. Der nächste Brief beginnt wieder bei 1.',
		]);
	}

	#[NoAdminRequired]
	public function letters(): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse(['letters' => $this->letters->listForCurrentUser()]);
	}

	#[NoAdminRequired]
	public function deleteLetter(int $id): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		$letter = $this->letters->get($id);
		if ($letter === null) {
			return new DataResponse(['error' => 'Brief nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		$this->cleanupAndDelete($letter);
		return new DataResponse([
			'ok' => true,
			'letters' => $this->letters->listForCurrentUser(),
			'message' => 'Brief gelöscht.',
		]);
	}

	#[NoAdminRequired]
	public function deleteAllLetters(): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		$count = 0;
		foreach ($this->letters->listEntities() as $letter) {
			$this->cleanupAndDelete($letter);
			$count++;
		}
		return new DataResponse([
			'ok' => true,
			'deleted' => $count,
			'letters' => [],
			'message' => $count . ' Brief(e) gelöscht.',
		]);
	}

	#[NoAdminRequired]
	public function assignments(): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		return new DataResponse([
			'users' => $this->assignments->usersWithAssignments(),
		]);
	}

	#[NoAdminRequired]
	public function saveAssignment(): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		try {
			$data = $this->payload($this->request);
			$uid = (string)($data['uid'] ?? '');
			$ids = $data['organisation_ids'] ?? [];
			if (!is_array($ids)) {
				$ids = [];
			}
			$saved = $this->assignments->setForUser($uid, $ids);
			return new DataResponse([
				'ok' => true,
				'uid' => $uid,
				'organisation_ids' => $saved,
				'users' => $this->assignments->usersWithAssignments(),
				'message' => 'Zuordnung gespeichert.',
			]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	private function cleanupAndDelete(\OCA\BriefManager\Db\Letter $letter): void {
		$this->storage->deletePdf($letter);
		$path = trim((string)($letter->getInternetmarkePath() ?? ''));
		if ($path !== '') {
			$this->stamps->delete($path);
		}
		$this->letters->delete($letter);
	}
}
