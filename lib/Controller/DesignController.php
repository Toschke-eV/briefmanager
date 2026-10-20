<?php

declare(strict_types=1);

namespace OCA\BriefManager\Controller;

use OCA\BriefManager\Design\DesignRegistry;
use OCA\BriefManager\Service\LetterService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class DesignController extends Controller {
	use RequestPayloadTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private DesignRegistry $designs,
		private LetterService $letters,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		return new DataResponse([
			'designs' => $this->designs->listDetailed(),
			'labels' => $this->designs->labels(),
		]);
	}

	#[NoAdminRequired]
	public function create(): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		try {
			$saved = $this->designs->saveCustom($this->payload($this->request));
			return new DataResponse([
				'design' => $saved,
				'designs' => $this->designs->listDetailed(),
				'labels' => $this->designs->labels(),
			]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function update(string $slug): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		try {
			$data = $this->payload($this->request);
			$data['slug'] = $slug;
			$saved = $this->designs->saveCustom($data);
			return new DataResponse([
				'design' => $saved,
				'designs' => $this->designs->listDetailed(),
				'labels' => $this->designs->labels(),
			]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function destroy(string $slug): DataResponse {
		if (!$this->letters->isAdmin()) {
			return new DataResponse(['error' => 'Nur Admin'], Http::STATUS_FORBIDDEN);
		}
		try {
			$this->designs->deleteCustom($slug);
			return new DataResponse([
				'designs' => $this->designs->listDetailed(),
				'labels' => $this->designs->labels(),
			]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
