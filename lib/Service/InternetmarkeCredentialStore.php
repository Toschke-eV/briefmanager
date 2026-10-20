<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCA\BriefManager\AppInfo\Application;
use OCP\IConfig;
use OCP\Security\ICrypto;
use OCP\IUserSession;

/**
 * Trennung: App-API-Schlüssel (Admin) vs. persönliche Portokasse (Benutzer).
 * Secrets werden mit Nextcloud ICrypto verschlüsselt gespeichert.
 */
class InternetmarkeCredentialStore {
	public const APP_CLIENT_ID = 'im_client_id';
	public const APP_CLIENT_SECRET = 'im_client_secret_enc';
	public const APP_ALLOW_PURCHASE = 'im_allow_live_purchase';
	public const USER_USERNAME = 'im_portokasse_user';
	public const USER_PASSWORD = 'im_portokasse_pass_enc';

	public function __construct(
		private IConfig $config,
		private ICrypto $crypto,
		private IUserSession $session,
	) {
	}

	public function setAppCredentials(?string $clientId, ?string $clientSecret): void {
		if ($clientId !== null) {
			$this->config->setAppValue(Application::APP_ID, self::APP_CLIENT_ID, trim($clientId));
		}
		if ($clientSecret !== null && trim($clientSecret) !== '') {
			$enc = $this->crypto->encrypt(trim($clientSecret));
			$this->config->setAppValue(Application::APP_ID, self::APP_CLIENT_SECRET, $enc);
		}
	}

	public function clearAppSecret(): void {
		$this->config->deleteAppValue(Application::APP_ID, self::APP_CLIENT_SECRET);
	}

	/** @return array{client_id:string, client_secret:string, configured:bool} */
	public function getAppCredentials(): array {
		$id = trim($this->config->getAppValue(Application::APP_ID, self::APP_CLIENT_ID, ''));
		$secretEnc = $this->config->getAppValue(Application::APP_ID, self::APP_CLIENT_SECRET, '');
		$secret = '';
		if ($secretEnc !== '') {
			try {
				$secret = $this->crypto->decrypt($secretEnc);
			} catch (\Throwable) {
				$secret = '';
			}
		}
		return [
			'client_id' => $id,
			'client_secret' => $secret,
			'configured' => $id !== '' && $secret !== '',
		];
	}

	public function setAllowLivePurchase(bool $allow): void {
		$this->config->setAppValue(Application::APP_ID, self::APP_ALLOW_PURCHASE, $allow ? '1' : '0');
	}

	public function allowLivePurchase(): bool {
		return $this->config->getAppValue(Application::APP_ID, self::APP_ALLOW_PURCHASE, '0') === '1';
	}

	public function setUserPortokasse(string $username, ?string $password): void {
		$user = $this->session->getUser();
		if ($user === null) {
			throw new \RuntimeException('Nicht angemeldet');
		}
		$uid = $user->getUID();
		$this->config->setUserValue($uid, Application::APP_ID, self::USER_USERNAME, trim($username));
		if ($password !== null && $password !== '') {
			$this->config->setUserValue($uid, Application::APP_ID, self::USER_PASSWORD, $this->crypto->encrypt($password));
		}
	}

	public function clearUserPortokasse(): void {
		$user = $this->session->getUser();
		if ($user === null) {
			return;
		}
		$uid = $user->getUID();
		$this->config->deleteUserValue($uid, Application::APP_ID, self::USER_USERNAME);
		$this->config->deleteUserValue($uid, Application::APP_ID, self::USER_PASSWORD);
	}

	/** @return array{username:string, password:string, configured:bool} */
	public function getUserPortokasse(?string $uid = null): array {
		$uid = $uid ?? $this->session->getUser()?->getUID();
		if ($uid === null || $uid === '') {
			return ['username' => '', 'password' => '', 'configured' => false];
		}
		$userName = trim($this->config->getUserValue($uid, Application::APP_ID, self::USER_USERNAME, ''));
		$passEnc = $this->config->getUserValue($uid, Application::APP_ID, self::USER_PASSWORD, '');
		$password = '';
		if ($passEnc !== '') {
			try {
				$password = $this->crypto->decrypt($passEnc);
			} catch (\Throwable) {
				$password = '';
			}
		}
		return [
			'username' => $userName,
			'password' => $password,
			'configured' => $userName !== '' && $password !== '',
		];
	}

	/** @return list<string> */
	public function missingRequirements(): array {
		$missing = [];
		$app = $this->getAppCredentials();
		if (!$app['configured']) {
			$missing[] = 'DHL Developer Portal: INTERNETMARKE API client_id und client_secret (App-Einstellungen Briefmanager)';
		}
		$user = $this->getUserPortokasse();
		if (!$user['configured']) {
			$missing[] = 'Persönliche Portokasse (Benutzername/Passwort) unter Briefmanager → Internetmarke';
		}
		$missing[] = 'Portokasse muss die Geschäftsanwendung freigeben (meine Daten → Geschäftsanwendungen)';
		$missing[] = 'Optional Entwickler-Portokasse: Umstellung per Mail an it-csp@deutschepost.de (Muster-Marken)';
		$missing[] = 'Sendungsverfolgung Einschreiben: öffentliche Suche nutzbar; erweiterte APIs ggf. separater Vertrag';
		return $missing;
	}
}
