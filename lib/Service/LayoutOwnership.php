<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

/**
 * Reine Eigentumslogik für Layouts (ohne Nextcloud-Laufzeit testbar).
 */
final class LayoutOwnership {
	public static function canView(string $ownerUserId, string $actorUserId, bool $actorIsAdmin): bool {
		return $actorIsAdmin || ($ownerUserId !== '' && $ownerUserId === $actorUserId);
	}

	public static function canEdit(string $ownerUserId, string $actorUserId, bool $actorIsAdmin): bool {
		// Auch Admins dürfen fremde privaten Familienlayouts nicht umschreiben,
		// außer sie sind der Eigentümer. Lesen für Support: canView.
		return $ownerUserId !== '' && $ownerUserId === $actorUserId;
	}

	public static function canAssignToLetter(string $layoutOwnerId, string $letterOwnerId): bool {
		return $layoutOwnerId !== '' && $layoutOwnerId === $letterOwnerId;
	}
}
