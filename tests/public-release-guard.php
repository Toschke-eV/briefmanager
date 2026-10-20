<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$fail = 0;

function ok(string $m): void {
	echo "OK  $m\n";
}

function bad(string $m): void {
	global $fail;
	$fail++;
	echo "FAIL $m\n";
}

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
	$rel = substr($f->getPathname(), strlen($root) + 1);
	if (str_starts_with($rel, 'vendor/') || str_starts_with($rel, '.git/') || str_starts_with($rel, 'dist/')) {
		continue;
	}
	$name = $f->getFilename();
	if (preg_match('/\.(key|csr|crt|pem)$/i', $name)) {
		bad("Secret-Datei: $rel");
	}
	if (strcasecmp($name, '.env') === 0) {
		bad(".env: $rel");
	}
}

$designs = glob($root . '/img/designs/*') ?: [];
foreach ($designs as $d) {
	$bn = basename($d);
	if ($bn !== 'muster.png') {
		bad("Nicht-generisches Design-Asset: $bn");
	}
}
if ($designs === []) {
	bad('muster.png fehlt');
} else {
	ok('nur muster.png in img/designs');
}

$reg = file_get_contents($root . '/lib/Design/DesignRegistry.php') ?: '';
foreach (['Familie Toschke', 'Toschke Network', 'ADHS', 'aab.png', 'te.png'] as $needle) {
	if (str_contains($reg, $needle)) {
		bad("DesignRegistry enthält '$needle'");
	}
}
ok('DesignRegistry generisch');

$info = file_get_contents($root . '/appinfo/info.xml') ?: '';
if (!str_contains($info, '<id>briefmanager</id>')) {
	bad('App-ID');
} else {
	ok('App-ID briefmanager');
}
if (!str_contains($info, '<licence>agpl</licence>')) {
	bad('AGPL in info.xml');
} else {
	ok('AGPL in info.xml');
}
if (!is_readable($root . '/LICENSE')) {
	bad('LICENSE fehlt');
} else {
	ok('LICENSE vorhanden');
}

$tpl = file_get_contents($root . '/templates/index.php') ?: '';
if (str_contains($tpl, '45711')) {
	bad('private PLZ-Placeholder');
} else {
	ok('keine private PLZ');
}

$doc = file_get_contents($root . '/docs/INTERNETMARKE.md') ?: '';
if (str_contains($doc, 'wolke.toschke.de') || str_contains($doc, '192.168.') || str_contains($doc, 'pct exec')) {
	bad('Infrastruktur in Doku');
} else {
	ok('Doku ohne private Infra');
}

exit($fail > 0 ? 1 : 0);
