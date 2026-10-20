<?php

declare(strict_types=1);

namespace OCA\BriefManager\Controller;

use OCA\BriefManager\Service\LetterService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;

class LetterController extends Controller {
	use RequestPayloadTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private LetterService $letters,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function index(): DataResponse {
		return new DataResponse($this->letters->listForCurrentUser());
	}

	#[NoAdminRequired]
	public function show(int $id): DataResponse {
		$letter = $this->letters->get($id);
		if ($letter === null || !$this->letters->canAccess($letter)) {
			return new DataResponse(['error' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		return new DataResponse($letter->jsonSerialize());
	}

	#[NoAdminRequired]
	public function create(): DataResponse {
		try {
			$letter = $this->letters->create($this->payload($this->request));
			return new DataResponse($letter->jsonSerialize(), Http::STATUS_CREATED);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function update(int $id): DataResponse {
		$letter = $this->letters->get($id);
		if ($letter === null || !$this->letters->canEdit($letter)) {
			return new DataResponse(['error' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		$letter = $this->letters->update($letter, $this->payload($this->request));
		return new DataResponse($letter->jsonSerialize());
	}

	#[NoAdminRequired]
	public function destroy(int $id): DataResponse {
		$letter = $this->letters->get($id);
		if ($letter === null || !$this->letters->canEdit($letter)) {
			return new DataResponse(['error' => 'Nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		$this->letters->delete($letter);
		return new DataResponse(['ok' => true]);
	}
}
