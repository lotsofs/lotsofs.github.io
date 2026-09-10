<?php

// usage: php tests/i18n-coverage.php
// lists untranslated (absent/null/blank) and orphan keys per music locale; exits non-zero only on orphans

if (php_sapi_name() !== 'cli') {
	exit;
}

$langDir = realpath(__DIR__ . '/../app/modules/music/lang');
$parts = ['common', 'accounts', 'artists', 'albums', 'songs'];

$keyFile = [];
$baseOrder = [];
foreach ($parts as $part) {
	foreach (require "{$langDir}/en/{$part}.php" as $key => $value) {
		$keyFile[$key] = "{$part}.php";
		$baseOrder[] = $key;
	}
}

$locales = array_map(
	fn($f) => basename($f, '.php'),
	glob("{$langDir}/*.php")
);
sort($locales);

$translated = fn($v) => $v !== null && $v !== '';

$hasOrphans = 0;

foreach ($locales as $locale) {
	if ($locale === 'en') {
		continue;
	}

	$catalogue = require "{$langDir}/{$locale}.php";

	$missingByFile = [];
	$done = 0;
	foreach ($baseOrder as $key) {
		if (array_key_exists($key, $catalogue) && $translated($catalogue[$key])) {
			$done++;
		}
		else {
			$missingByFile[$keyFile[$key]][] = $key;
		}
	}

	$orphans = array_diff(array_keys($catalogue), $baseOrder);

	$total = count($baseOrder);
	$status = $done === $total && !$orphans ? 'complete' : ($total - $done) . ' missing';
	echo "\n{$locale}   {$done}/{$total} translated   {$status}\n";

	foreach ($parts as $part) {
		$file = "{$part}.php";
		if (empty($missingByFile[$file])) {
			continue;
		}
		echo "  {$file}\n";
		foreach ($missingByFile[$file] as $key) {
			echo "    {$key}\n";
		}
	}

	if ($orphans) {
		echo "  orphan keys (not in en, will never render):\n";
		foreach ($orphans as $key) {
			echo "    {$key}\n";
		}
	}

	if ($orphans) {
		$hasOrphans++;
	}
}

echo "\n";
exit($hasOrphans === 0 ? 0 : 1);
