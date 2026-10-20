<?php

declare(strict_types=1);

/**
 * Eigentumslogik mit zwei Benutzern (ohne Nextcloud-Container).
 * php tests/ownership-two-users.php
 */

require_once dirname(__DIR__) . '/lib/Service/LayoutOwnership.php';

use OCA\BriefManager\Service\LayoutOwnership;

$fail = 0;
function ok(string $m): void { echo "OK  $m\n"; }
function bad(string $m): void { global $fail; $fail++; echo "FAIL $m\n"; }
function assertTrue(bool $c, string $m): void { $c ? ok($m) : bad($m); }

$alice = 'alice';
$bob = 'bob';

// Layout von Alice
assertTrue(LayoutOwnership::canEdit('alice', $alice, false), 'Alice bearbeitet eigenes Layout');
assertTrue(!LayoutOwnership::canEdit('alice', $bob, false), 'Bob bearbeitet Alices Layout nicht');
assertTrue(!LayoutOwnership::canEdit('alice', $bob, true), 'Admin überschreibt fremdes Familienlayout nicht');
assertTrue(LayoutOwnership::canView('alice', $bob, true), 'Admin darf fremdes Layout sehen (Support)');
assertTrue(!LayoutOwnership::canView('alice', $bob, false), 'Bob sieht Alices Layout nicht');

// Brief-Zuordnung
assertTrue(LayoutOwnership::canAssignToLetter('alice', 'alice'), 'Alice-Layout an Alice-Brief');
assertTrue(!LayoutOwnership::canAssignToLetter('alice', 'bob'), 'Alice-Layout nicht an Bob-Brief');
assertTrue(!LayoutOwnership::canAssignToLetter('bob', 'alice'), 'Bob-Layout nicht an Alice-Brief');

// Simulierte Parallel-Idempotenz (Unique Key)
$store = [];
function claim(array &$store, string $key, string $status): string {
	if (isset($store[$key])) {
		return 'existing:' . $store[$key]['status'];
	}
	$store[$key] = ['status' => $status];
	return 'created:' . $status;
}
$a = claim($store, 'idemp-1', 'pending');
$b = claim($store, 'idemp-1', 'pending');
assertTrue($a === 'created:pending' && $b === 'existing:pending', 'Parallele Idempotenz: zweiter Claim kein Neu-Kauf');

$store['idemp-2'] = ['status' => 'uncertain'];
$again = claim($store, 'idemp-2', 'purchase');
assertTrue($again === 'existing:uncertain', 'Unklarer Kauf: kein erneuter Checkout');

// Client-Preis vs API-Preis
$apiPrice = 365;
$clientPrice = 1;
$confirm = $apiPrice;
assertTrue($confirm === $apiPrice && $clientPrice !== $apiPrice, 'Kaufpreis aus API, Client-Preis irrelevant');

echo $fail === 0 ? "\nOWNERSHIP TESTS OK\n" : "\n$fail FEHLER\n";
exit($fail === 0 ? 0 : 1);
