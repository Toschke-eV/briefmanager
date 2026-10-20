<?php

declare(strict_types=1);

namespace OCA\BriefManager\Controller;

use OCA\BriefManager\Service\LayoutService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class LayoutController extends Controller {
	use RequestPayloadTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private LayoutService $layouts,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		return new DataResponse($this->layouts->listMine());
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$layout = $this->layouts->getOwned($id);
		if ($layout === null) {
			return new DataResponse(['error' => 'Layout nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse($layout->jsonSerialize());
	}

	#[NoAdminRequired]
	public function create(): DataResponse {
		try {
			$layout = $this->layouts->create($this->payload($this->request));
			return new DataResponse($layout->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function update(int $id): DataResponse {
		$layout = $this->layouts->getOwned($id);
		if ($layout === null) {
			return new DataResponse(['error' => 'Layout nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		try {
			$layout = $this->layouts->update($layout, $this->payload($this->request));
			return new DataResponse($layout->jsonSerialize());
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		$layout = $this->layouts->getOwned($id);
		if ($layout === null) {
			return new DataResponse(['error' => 'Layout nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		try {
			$this->layouts->delete($layout);
			return new DataResponse(['ok' => true]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function uploadLogo(int $id): DataResponse {
		$layout = $this->layouts->getOwned($id);
		if ($layout === null) {
			return new DataResponse(['error' => 'Layout nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		try {
			$key = $this->layouts->storeLogoFromRequest($this->request, $layout);
			return new DataResponse(['logo_path' => $key, 'layout' => $layout->jsonSerialize()]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}
}
