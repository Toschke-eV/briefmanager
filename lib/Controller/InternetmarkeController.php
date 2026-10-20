<?php

declare(strict_types=1);

namespace OCA\BriefManager\Controller;

use OCA\BriefManager\Db\PostageOrderMapper;
use OCA\BriefManager\Service\InternetmarkeCredentialStore;
use OCA\BriefManager\Service\InternetmarkePurchaseService;
use OCA\BriefManager\Service\LetterService;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\DataResponse;
use OCP\IGroupManager;
use OCP\IRequest;
use OCP\IUserSession;

/**
 * Internetmarke REST: Katalog, Preview, blockierter Live-Kauf, Portokasse-Credentials.
 * Kein stiller Retry bei unklarem Ausgang; Idempotenz über idempotency_key.
 */
class InternetmarkeController extends Controller {
	use RequestPayloadTrait;

	public function __construct(
		string $appName,
		IRequest $request,
		private InternetmarkePurchaseService $purchase,
		private InternetmarkeCredentialStore $credentials,
		private LetterService $letters,
		private PostageOrderMapper $orders,
		private IUserSession $session,
		private IGroupManager $groups,
	) {
		parent::__construct($appName, $request);
	}

	#[NoAdminRequired]
	public function status(): DataResponse {
		return new DataResponse($this->purchase->status());
	}

	#[NoAdminRequired]
	public function catalog(): DataResponse {
		$registeredOnly = $this->request->getParam('registered_only', '0') === '1';
		try {
			return new DataResponse([
				'products' => $this->purchase->catalog($registeredOnly),
				'source' => 'https://api-eu.dhl.com/post/de/information/products/v1/products?profile=IM-PARTNER',
				'note' => 'Produktcodes und Preise kommen live aus der Products API. Ohne API-Key leer/Fehler.',
			]);
		} catch (\Throwable $e) {
			return new DataResponse([
				'products' => [],
				'error' => $e->getMessage(),
				'blockers' => $this->credentials->missingRequirements(),
			], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function savePortokasse(): DataResponse {
		$data = $this->payload($this->request);
		try {
			$user = trim((string)($data['username'] ?? ''));
			$pass = array_key_exists('password', $data) ? (string)$data['password'] : null;
			if ($user === '') {
				throw new \InvalidArgumentException('Portokasse-Benutzername fehlt.');
			}
			$this->credentials->setUserPortokasse($user, $pass !== null && $pass !== '' ? $pass : null);
			// Secret niemals zurückliefern
			return new DataResponse([
				'ok' => true,
				'username' => $user,
				'password_set' => $pass !== null && $pass !== '',
				'portokasse_configured' => $this->credentials->getUserPortokasse()['configured'],
			]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function clearPortokasse(): DataResponse {
		$this->credentials->clearUserPortokasse();
		return new DataResponse(['ok' => true]);
	}

	/** Admin: DHL client_id/secret + Live-Kauf-Schalter (Standard aus). */
	public function saveAppCredentials(): DataResponse {
		if (!$this->isAdmin()) {
			return new DataResponse(['error' => 'Nur Administratoren'], Http::STATUS_FORBIDDEN);
		}
		$data = $this->payload($this->request);
		try {
			$this->credentials->setAppCredentials(
				isset($data['client_id']) ? (string)$data['client_id'] : null,
				isset($data['client_secret']) ? (string)$data['client_secret'] : null,
			);
			if (array_key_exists('allow_live_purchase', $data)) {
				$this->credentials->setAllowLivePurchase(!empty($data['allow_live_purchase']));
			}
			return new DataResponse([
				'ok' => true,
				'allow_live_purchase' => $this->credentials->allowLivePurchase(),
				'app_configured' => $this->credentials->getAppCredentials()['configured'],
			]);
		} catch (\Throwable $e) {
			return new DataResponse(['error' => $e->getMessage()], Http::STATUS_BAD_REQUEST);
		}
	}

	#[NoAdminRequired]
	public function preview(int $letterId): DataResponse {
		return $this->runOrder($letterId, 'preview');
	}

	/**
	 * Kauf nur wenn Admin allow_live_purchase=1 und confirm_price_cents gesetzt.
	 * Standard: 403/400, kein Porto.
	 */
	#[NoAdminRequired]
	public function purchase(int $letterId): DataResponse {
		if (!$this->credentials->allowLivePurchase()) {
			return new DataResponse([
				'error' => 'Live-Kauf deaktiviert. Kein Porto gekauft.',
				'blockers' => $this->credentials->missingRequirements(),
				'allow_live_purchase' => false,
			], Http::STATUS_FORBIDDEN);
		}
		return $this->runOrder($letterId, 'purchase');
	}

	#[NoAdminRequired]
	public function orders(int $letterId): DataResponse {
		$letter = $this->letters->get($letterId);
		if ($letter === null || !$this->letters->canAccess($letter)) {
			return new DataResponse(['error' => 'Brief nicht gefunden'], Http::STATUS_NOT_FOUND);
		}
		$uid = $this->session->getUser()?->getUID() ?? '';
		if ($letter->getUserId() !== $uid && !$this->isAdmin()) {
			return new DataResponse(['error' => 'Kein Zugriff auf Aufträge'], Http::STATUS_FORBIDDEN);
		}
		$list = $this->orders->findForLetter($letterId, $letter->getUserId());
		return new DataResponse(array_map(static fn ($o) => $o->jsonSerialize(), $list));
	}

	private function runOrder(int $letterId, string $mode): DataResponse {
		$letter = $this->letters->get($letterId);
		if ($letter === null || !$this->letters->canEdit($letter)) {
			return new DataResponse(['error' => 'Brief nicht gefunden oder keine Schreibrechte'], Http::STATUS_NOT_FOUND);
		}
		$data = $this->payload($this->request);
		$data['mode'] = $mode;
		try {
			$order = $this->purchase->quoteOrPurchase($letter, $data);
			return new DataResponse($order->jsonSerialize());
		} catch (\Throwable $e) {
			// Kein automatischer Retry bei unklarem Ausgang; Client sieht Fehler + Idempotenz-Key.
			return new DataResponse([
				'error' => $e->getMessage(),
				'mode' => $mode,
				'idempotency_key' => (string)($data['idempotency_key'] ?? ''),
				'retry_safe' => false,
				'hint' => 'Bei unklarem Ausgang denselben idempotency_key erneut senden (liefert bestehenden Auftrag), keinen neuen Key erzeugen.',
			], Http::STATUS_BAD_REQUEST);
		}
	}

	private function isAdmin(): bool {
		$user = $this->session->getUser();
		return $user !== null && $this->groups->isAdmin($user->getUID());
	}
}
