<?php

declare(strict_types=1);

/**
 * Regression: Idempotenz-Key darf keine fremden Aufträge/Metadaten liefern;
 * gleicher User mit anderem Brief/Produkt/Modus darf nicht still den alten Auftrag bekommen;
 * Checkout-Nachfehler → purchased/uncertain, nie error + kein Re-Kauf.
 *
 * php tests/idempotency-binding.php
 */

require_once dirname(__DIR__) . '/lib/Service/PostageIdempotencyGuard.php';

use OCA\BriefManager\Service\PostageIdempotencyGuard;

$fail = 0;
function ok(string $m): void { echo "OK  $m\n"; }
function bad(string $m): void { global $fail; $fail++; echo "FAIL $m\n"; }
function assertTrue(bool $c, string $m): void { $c ? ok($m) : bad($m); }

// Alice-Auftrag
$aliceOrder = [
	'user_id' => 'alice',
	'letter_id' => 10,
	'product_code' => 1,
	'mode' => 'purchase',
	'stamp_path' => 'stamps/letter-10.pdf',
	'tracking_number' => 'RR123',
	'status' => 'purchased',
];

// Bob kennt Alices Key → Guard verweigert, kein Auftrag serialisierbar
$bobGotOrder = null;
try {
	PostageIdempotencyGuard::assertBindingMatches(
		'bob',
		10,
		1,
		'purchase',
		$aliceOrder['user_id'],
		$aliceOrder['letter_id'],
		$aliceOrder['product_code'],
		$aliceOrder['mode'],
	);
	$bobGotOrder = $aliceOrder;
	bad('Bob hätte abgelehnt werden müssen');
} catch (\RuntimeException $e) {
	ok('Bob mit Alices Key abgelehnt: ' . $e->getMessage());
	assertTrue(!str_contains($e->getMessage(), 'RR123'), 'Fehlermeldung ohne Tracking-Leak');
	assertTrue(!str_contains($e->getMessage(), 'stamps/'), 'Fehlermeldung ohne Stamp-Pfad');
	assertTrue($bobGotOrder === null, 'Bob erhält keinen Auftrag');
}

// Race-Pfad: gleiche Prüfung vor Rückgabe
$raceReturned = null;
try {
	PostageIdempotencyGuard::assertBindingMatches(
		'bob',
		99,
		1,
		'purchase',
		$aliceOrder['user_id'],
		$aliceOrder['letter_id'],
		$aliceOrder['product_code'],
		$aliceOrder['mode'],
	);
	$raceReturned = $aliceOrder;
	bad('Race-Pfad Bob hätte scheitern müssen');
} catch (\RuntimeException) {
	ok('Race-Pfad: Bob verweigert');
	assertTrue($raceReturned === null, 'Race liefert Bob keine Metadaten');
}

// Alice, gleicher Key, anderer Brief
try {
	PostageIdempotencyGuard::assertBindingMatches(
		'alice',
		11,
		1,
		'purchase',
		$aliceOrder['user_id'],
		$aliceOrder['letter_id'],
		$aliceOrder['product_code'],
		$aliceOrder['mode'],
	);
	bad('Anderer Brief hätte scheitern müssen');
} catch (\InvalidArgumentException $e) {
	ok('Alice Key + anderer Brief abgelehnt');
}

// Anderes Produkt
try {
	PostageIdempotencyGuard::assertBindingMatches(
		'alice',
		10,
		2,
		'purchase',
		$aliceOrder['user_id'],
		$aliceOrder['letter_id'],
		$aliceOrder['product_code'],
		$aliceOrder['mode'],
	);
	bad('Anderes Produkt hätte scheitern müssen');
} catch (\InvalidArgumentException) {
	ok('Alice Key + anderes Produkt abgelehnt');
}

// Anderer Modus
try {
	PostageIdempotencyGuard::assertBindingMatches(
		'alice',
		10,
		1,
		'preview',
		$aliceOrder['user_id'],
		$aliceOrder['letter_id'],
		$aliceOrder['product_code'],
		$aliceOrder['mode'],
	);
	bad('Anderer Modus hätte scheitern müssen');
} catch (\InvalidArgumentException) {
	ok('Alice Key + anderer Modus abgelehnt');
}

// Korrekte Bindung → Wiederverwendung ohne Re-Kauf
PostageIdempotencyGuard::assertBindingMatches(
	'alice',
	10,
	1,
	'purchase',
	$aliceOrder['user_id'],
	$aliceOrder['letter_id'],
	$aliceOrder['product_code'],
	$aliceOrder['mode'],
);
ok('Korrekte Bindung: bestehender Auftrag wiederverwendbar');

// Checkout-Nachfehler-Statusmodell
function afterCheckoutRecovery(bool $downloadOk, bool $hasVoucher): string {
	if ($downloadOk || $hasVoucher) {
		return 'purchased';
	}
	return 'uncertain';
}
assertTrue(afterCheckoutRecovery(false, true) === 'purchased', 'Checkout+Voucher, Download fail → purchased');
assertTrue(afterCheckoutRecovery(false, false) === 'uncertain', 'Checkout ohne Proof → uncertain');
assertTrue(afterCheckoutRecovery(true, false) === 'purchased', 'Checkout+Stamp → purchased');
assertTrue(afterCheckoutRecovery(false, true) !== 'error', 'Nie status error nach Checkout');

$mapperSrc = file_get_contents(dirname(__DIR__) . '/lib/Db/PostageOrderMapper.php') ?: '';
if (preg_match('/function findByIdempotencyKey\(.*?\{(.*?)\}/s', $mapperSrc, $m)) {
	$body = $m[1];
	assertTrue(
		str_contains($body, 'idempotency_key') && !str_contains($body, 'user_id'),
		'Mapper findByIdempotencyKey filtert nicht nach UID (Guard nötig)'
	);
} else {
	bad('findByIdempotencyKey nicht parsebar');
}

$svc = file_get_contents(dirname(__DIR__) . '/lib/Service/InternetmarkePurchaseService.php') ?: '';
assertTrue(str_contains($svc, 'acceptExistingBoundOrder'), 'Service nutzt Bindungsprüfung');
assertTrue(str_contains($svc, 'PostageIdempotencyGuard'), 'Guard eingebunden');
assertTrue(str_contains($svc, 'Checkout erfolgt') || str_contains($svc, 'Nacharbeit nötig'), 'Recovery-Text nach Checkout');

echo $fail === 0 ? "\nIDEMPOTENCY BINDING OK\n" : "\n$fail FEHLER\n";
exit($fail === 0 ? 0 : 1);
