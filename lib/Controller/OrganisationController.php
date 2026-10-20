<?php

declare(strict_types=1);

namespace OCA\BriefManager\Controller;

use OCA\BriefManager\Design\DesignRegistry;
use OCA\BriefManager\Service\LetterService;
use OCA\BriefManager\Service\LogoService;
use OCA\BriefManager\Service\OrgAssignmentService;
use OCA\BriefManager\Service\OrganisationService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class OrganisationController extends Controller {
	use RequestPayloadTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private OrganisationService $organisation,
		private LetterService $letters,
		private DesignRegistry $designs,
		private LogoService $logos,
		private OrgAssignmentService $assignments,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function show(): DataResponse {
		if ($this->letters->isAdmin()) {
			$org = $this->organisation->get();
			return new DataResponse([
				'organisation' => $org,
				'organisations' => $this->organisation->listAll(),
				'active_id' => $this->organisation->getActiveId(),
				'designs' => $this->designs->labels(),
				'logo_data_uri' => $this->logos->asDataUri((string)($org['logo_path'] ?? '')),
			]);
		}
		$org = $this->assignments->activeOrgForCurrentUser();
		return new DataResponse([
			'organisation' => $org,
			'organisations' => $this->assignments->availableOrgsForCurrentUser(),
			'active_id' => (string)($org['id'] ?? ''),
			'designs' => $this->designs->labels(),
			'logo_data_uri' => $this->logos->asDataUri((string)($org['logo_path'] ?? '')),
		]);
	}

	#[NoAdminRequired]
	public function detail(string $id): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		$org = $this->organisation->find($id);
		if ($org === null) {
			return new DataResponse(['error' => 'Verein nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse([
			'organisation' => $org,
			'logo_data_uri' => $this->logos->asDataUri((string)($org['logo_path'] ?? '')),
		]);
	}

	#[NoAdminRequired]
	public function create(): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		try {
			$org = $this->organisation->create($this->payload($this->request));
			$this->organisation->setActive((string)$org['id']);
			return new DataResponse([
				'organisation' => $org,
				'organisations' => $this->organisation->listAll(),
				'active_id' => $this->organisation->getActiveId(),
				'logo_data_uri' => $this->logos->asDataUri((string)($org['logo_path'] ?? '')),
			]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function update(): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		try {
			$org = $this->organisation->save($this->payload($this->request));
			return new DataResponse([
				'organisation' => $org,
				'organisations' => $this->organisation->listAll(),
				'active_id' => $this->organisation->getActiveId(),
				'logo_data_uri' => $this->logos->asDataUri((string)($org['logo_path'] ?? '')),
			]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function destroy(string $id): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		try {
			$this->organisation->delete($id);
			$org = $this->organisation->get();
			return new DataResponse([
				'organisation' => $org,
				'organisations' => $this->organisation->listAll(),
				'active_id' => $this->organisation->getActiveId(),
				'logo_data_uri' => $this->logos->asDataUri((string)($org['logo_path'] ?? '')),
			]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function setActive(string $id): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		try {
			$org = $this->organisation->setActive($id);
			return new DataResponse([
				'organisation' => $org,
				'organisations' => $this->organisation->listAll(),
				'active_id' => $id,
				'logo_data_uri' => $this->logos->asDataUri((string)($org['logo_path'] ?? '')),
			]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	/** Benutzer wählt einen der ihm zugeordneten Vereine. */
	#[NoAdminRequired]
	public function selectMine(string $id): DataResponse {
		try {
			$org = $this->assignments->selectForCurrentUser($id);
			return new DataResponse([
				'organisation' => $org,
				'organisations' => $this->assignments->availableOrgsForCurrentUser(),
				'active_id' => (string)$org['id'],
			]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function uploadLogo(string $id): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		if ($this->organisation->find($id) === null) {
			return new DataResponse(['error' => 'Verein nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		try {
			$key = $this->logos->storeFromRequest($this->request, $id);
			$org = $this->organisation->save(['id' => $id, 'logo_path' => $key]);
			return new DataResponse([
				'organisation' => $org,
				'logo_path' => $key,
				'logo_data_uri' => $this->logos->asDataUri($key),
			]);
		} catch (\InvalidArgumentException $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_INTERNAL_SERVER_ERROR);
		}
	}
}
