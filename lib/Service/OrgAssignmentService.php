<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

use OCA\BriefManager\AppInfo\Application;
use OCP\IConfig;
use OCP\IGroupManager;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * Zuordnung Benutzer ↔ Vereine und aktive Vereinswahl pro Benutzer.
 */
class OrgAssignmentService {
	public const CONFIG_MAP = 'user_organisations_json';

	public function __construct(
		private IConfig $config,
		private IUserManager $users,
		private IUserSession $session,
		private IGroupManager $groups,
		private OrganisationService $organisations,
	) {
	}

	/** @return array<string, list<string>> uid => orgIds */
	public function allAssignments(): array {
		$raw = $this->config->getAppValue(Application::APP_ID, self::CONFIG_MAP, '{}');
		$decoded = json_decode($raw, true);
		if (!is_array($decoded)) {
			return [];
		}
		$out = [];
		foreach ($decoded as $uid => $ids) {
			if (!is_string($uid) || !is_array($ids)) {
				continue;
			}
			$clean = [];
			foreach ($ids as $id) {
				$id = trim((string)$id);
				if ($id !== '') {
					$clean[] = $id;
				}
			}
			$out[$uid] = array_values(array_unique($clean));
		}
		return $out;
	}

	/** @param list<string> $orgIds */
	public function setForUser(string $uid, array $orgIds): array {
		$uid = trim($uid);
		if ($uid === '' || $this->users->get($uid) === null) {
			throw new \InvalidArgumentException('Benutzer nicht gefunden.');
		}
		$valid = [];
		$known = [];
		foreach ($this->organisations->listAll() as $org) {
			$known[(string)$org['id']] = true;
		}
		foreach ($orgIds as $id) {
			$id = trim((string)$id);
			if ($id !== '' && isset($known[$id])) {
				$valid[] = $id;
			}
		}
		$valid = array_values(array_unique($valid));
		$map = $this->allAssignments();
		if ($valid === []) {
			unset($map[$uid]);
		} else {
			$map[$uid] = $valid;
		}
		$this->persist($map);

		$active = $this->getUserActiveId($uid);
		if ($active !== '' && !in_array($active, $valid, true)) {
			$this->setUserActiveId($uid, $valid[0] ?? '');
		} elseif ($active === '' && $valid !== []) {
			$this->setUserActiveId($uid, $valid[0]);
		}

		return $valid;
	}

	/** @return list<string> */
	public function forUser(string $uid): array {
		return $this->allAssignments()[$uid] ?? [];
	}

	public function getUserActiveId(string $uid): string {
		return $this->config->getUserValue($uid, Application::APP_ID, 'active_organisation_id', '');
	}

	public function setUserActiveId(string $uid, string $orgId): void {
		$orgId = trim($orgId);
		if ($orgId === '') {
			$this->config->deleteUserValue($uid, Application::APP_ID, 'active_organisation_id');
			return;
		}
		$this->config->setUserValue($uid, Application::APP_ID, 'active_organisation_id', $orgId);
	}

	/**
	 * Vereine, die der aktuelle Nutzer nutzen darf.
	 * Admin ohne Zuordnung: alle. Sonst nur zugewiesene.
	 *
	 * @return list<array<string, mixed>>
	 */
	public function availableOrgsForCurrentUser(): array {
		$uid = $this->session->getUser()?->getUID();
		if ($uid === null) {
			return [];
		}
		$all = $this->organisations->listAll();
		$assigned = $this->forUser($uid);
		$isAdmin = $this->groups->isAdmin($uid);
		if ($assigned === []) {
			return $isAdmin ? $all : [];
		}
		$want = array_flip($assigned);
		return array_values(array_filter(
			$all,
			static fn (array $o) => isset($want[(string)($o['id'] ?? '')])
		));
	}

	/** Aktiver Verein für den aktuellen Nutzer (Briefe/PDF). */
	public function activeOrgForCurrentUser(): array {
		$uid = $this->session->getUser()?->getUID();
		$available = $this->availableOrgsForCurrentUser();
		if ($available === []) {
			// Fallback: globaler Admin-Aktivverein, sonst erster
			return $this->organisations->get();
		}
		$preferred = $uid !== null ? $this->getUserActiveId($uid) : '';
		foreach ($available as $org) {
			if (($org['id'] ?? '') === $preferred) {
				return $org;
			}
		}
		$first = $available[0];
		if ($uid !== null) {
			$this->setUserActiveId($uid, (string)$first['id']);
		}
		return $first;
	}

	public function selectForCurrentUser(string $orgId): array {
		$uid = $this->session->getUser()?->getUID();
		if ($uid === null) {
			throw new \RuntimeException('Nicht angemeldet');
		}
		$available = $this->availableOrgsForCurrentUser();
		foreach ($available as $org) {
			if (($org['id'] ?? '') === $orgId) {
				$this->setUserActiveId($uid, $orgId);
				return $org;
			}
		}
		throw new \RuntimeException('Dieser Verein ist Ihnen nicht zugeordnet.');
	}

	/**
	 * @return list<array{uid: string, displayname: string, organisation_ids: list<string>, active_id: string}>
	 */
	public function usersWithAssignments(int $limit = 200): array {
		$out = [];
		$map = $this->allAssignments();
		foreach ($this->users->search('', $limit) as $user) {
			$uid = $user->getUID();
			$out[] = [
				'uid' => $uid,
				'displayname' => $user->getDisplayName(),
				'organisation_ids' => $map[$uid] ?? [],
				'active_id' => $this->getUserActiveId($uid),
			];
		}
		usort($out, static fn ($a, $b) => strcasecmp($a['displayname'], $b['displayname']));
		return $out;
	}

	/** @param array<string, list<string>> $map */
	private function persist(array $map): void {
		$json = json_encode($map, JSON_UNESCAPED_UNICODE);
		$this->config->setAppValue(Application::APP_ID, self::CONFIG_MAP, $json ?: '{}');
	}
}
