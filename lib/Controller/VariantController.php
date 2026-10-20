<?php

declare(strict_types=1);

namespace OCA\BriefManager\Controller;

use OCA\BriefManager\Service\VariantService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class VariantController extends Controller {
	use RequestPayloadTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private VariantService $variants,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		return new DataResponse($this->variants->list(false));
	}

	#[NoAdminRequired]
	public function create(): DataResponse {
		$v = $this->variants->create($this->payload($this->request));
		return new DataResponse($v->jsonSerialize(), Http::STATUS_CREATED);
	}

	#[NoAdminRequired]
	public function update(int $id): DataResponse {
		$v = $this->variants->update($id, $this->payload($this->request));
		if ($v === null) {
			return new DataResponse(['error' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse($v->jsonSerialize());
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		if (!$this->variants->delete($id)) {
			return new DataResponse(['error' => 'Löschen nicht möglich (Standardvariante oder noch in Verwendung)'], Http::STATUS_BAD_REQUEST);
		}
		return new DataResponse(['ok' => true]);
	}
}
