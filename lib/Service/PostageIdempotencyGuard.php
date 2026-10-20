<?php

declare(strict_types=1);

namespace OCA\BriefManager\Service;

/**
 * Bindet Idempotenzschlüssel an Benutzer + Brief + Produkt + Modus.
 * Fremde Keys liefern keinen Auftrag und keine Metadaten.
 */
final class PostageIdempotencyGuard {
	/**
	 * @throws \RuntimeException fremder Benutzer (keine Auftragsdaten preisgeben)
	 * @throws \InvalidArgumentException gleicher Benutzer, aber andere Bindung
	 */
	public static function assertBindingMatches(
		string $actorUserId,
		int $requestLetterId,
		int $requestProductCode,
		string $requestMode,
		string $orderUserId,
		int $orderLetterId,
		int $orderProductCode,
		string $orderMode,
	): void {
		if ($actorUserId === '' || $orderUserId === '' || $actorUserId !== $orderUserId) {
			throw new \RuntimeException('Idempotenzschlüssel ungültig oder nicht autorisiert.');
		}
		if ($orderLetterId !== $requestLetterId
			|| $orderProductCode !== $requestProductCode
			|| $orderMode !== $requestMode
		) {
			throw new \InvalidArgumentException(
				'Idempotenzschlüssel ist bereits an einen anderen Brief, ein anderes Produkt oder einen anderen Modus gebunden. Bitte neuen Schlüssel verwenden.'
			);
		}
	}
}
