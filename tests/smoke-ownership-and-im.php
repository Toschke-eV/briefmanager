<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = 0;
function ok(string $msg): void { echo "OK  $msg\n"; }
function bad(string $msg): void { global $fail; $fail++; echo "FAIL $msg\n"; }
function assertTrue(bool $cond, string $msg): void { $cond ? ok($msg) : bad($msg); }

$need = [
	'lib/Service/LayoutService.php',
	'lib/Service/LayoutOwnership.php',
	'lib/Service/InternetmarkeClient.php',
	'lib/Service/InternetmarkeCredentialStore.php',
	'lib/Service/InternetmarkePurchaseService.php',
	'lib/Controller/LayoutController.php',
	'lib/Controller/InternetmarkeController.php',
	'js/briefmanager-main.js',
	'templates/index.php',
	'docs/INTERNETMARKE.md',
];
foreach ($need as $rel) {
	assertTrue(is_readable($root . '/' . $rel), "Datei $rel");
}

foreach ([
	'lib/Service/LayoutOwnership.php',
	'lib/Service/LayoutService.php',
	'lib/Service/InternetmarkePurchaseService.php',
	'lib/Service/LetterService.php',
	'lib/Controller/InternetmarkeController.php',
	'lib/Controller/PageController.php',
	'lib/Db/PostageOrder.php',
] as $rel) {
	$out = [];
	$code = 0;
	exec('php -l ' . escapeshellarg($root . '/' . $rel) . ' 2>&1', $out, $code);
	assertTrue($code === 0, "php -l $rel");
}

$js = file_get_contents($root . '/js/briefmanager-main.js') ?: '';
assertTrue(str_contains($js, 'bm-layout-save'), 'JS Layout speichern');
assertTrue(str_contains($js, 'bm-porto-save'), 'JS Portokasse');
assertTrue(str_contains($js, 'bm-im-purchase'), 'JS Kaufbestätigung');
assertTrue(str_contains($js, 'confirm_price_cents'), 'JS sendet Preisbestätigung');
assertTrue(str_contains($js, 'Passwort wird nie'), 'JS Hinweis kein Secret-Echo');

$tpl = file_get_contents($root . '/templates/index.php') ?: '';
assertTrue(str_contains($tpl, 'Mein Briefpapier'), 'UI Layout-Panel');
assertTrue(str_contains($tpl, 'Meine Portokasse'), 'UI Portokasse');
assertTrue(str_contains($tpl, 'bm-im-load-catalog'), 'UI Katalog');
assertTrue(str_contains($tpl, 'sendungsverfolgung.html'), 'öffentlicher Tracking-Link');
assertTrue(!str_contains($tpl, 'demnächst in den persönlichen'), 'kein API-only-Hinweis mehr');

$purchase = file_get_contents($root . '/lib/Service/InternetmarkePurchaseService.php') ?: '';
assertTrue(str_contains($purchase, 'resolveTrustedProduct'), 'API-Preis vertrauenswürdig');
assertTrue(str_contains($purchase, 'uncertain'), 'unklarer Kaufausgang');
assertTrue(str_contains($purchase, 'Unique') || str_contains($purchase, 'findByIdempotencyKey'), 'Idempotenz parallel');
assertTrue(str_contains($purchase, 'Client-Preise werden ignoriert') || str_contains($purchase, 'vom API-Preis'), 'Client-Preis abgelehnt');

$credCtrl = file_get_contents($root . '/lib/Controller/InternetmarkeController.php') ?: '';
assertTrue(str_contains($credCtrl, 'password_set'), 'Portokasse ohne Secret-Echo');
assertTrue(str_contains($credCtrl, 'retry_safe'), 'kein Blind-Retry');

$info = file_get_contents($root . '/appinfo/info.xml') ?: '';
assertTrue(str_contains($info, 'max-version="35"'), 'NC 35');

$admin = file_get_contents($root . '/templates/admin.php') ?: '';
assertTrue(str_contains($admin, 'bm-im-allow-purchase'), 'Admin Live-Schalter');
assertTrue(str_contains($admin, 'bm-im-client-id'), 'Admin App-Zugänge');

passthru('php ' . escapeshellarg($root . '/tests/ownership-two-users.php'), $ownCode);
assertTrue($ownCode === 0, 'Zwei-User-Eigentumstest');

passthru('php ' . escapeshellarg($root . '/tests/idempotency-binding.php'), $idempCode);
assertTrue($idempCode === 0, 'Idempotenz-Bindung Regression');

$info = file_get_contents($root . '/appinfo/info.xml') ?: '';
assertTrue(str_contains($info, '<version>0.9.2</version>'), 'Version 0.9.2');

echo $fail === 0 ? "\nALLE TESTS OK\n" : "\n$fail FEHLER\n";
exit($fail === 0 ? 0 : 1);
