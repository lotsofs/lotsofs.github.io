<?php

// usage: php tests/i18n-coverage.php
// lists untranslated (absent/null/blank) and orphan keys per music locale, and untranslated changelog.php entries; exits non-zero only on orphans

if (php_sapi_name() !== 'cli') {
	exit;
}

require __DIR__ . '/../app/modules/music/changes.php';

$langDir = realpath(__DIR__ . '/../app/modules/music/lang');
$parts = array_map(fn($f) => basename($f, '.php'), glob("{$langDir}/en/*.php"));
sort($parts);

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

	$missingChanges = [];
	foreach (musicChangelog() as $release) {
		foreach ($release['changes'] as $group => $entries) {
			foreach ($entries as $entry) {
				if (trim($entry[$locale] ?? '') === '') {
					$missingChanges[] = ($release['date'] ?? 'undated') . " {$group}: " . substr($entry['en'], 0, 60);
				}
			}
		}
	}

	$total = count($baseOrder);
	$status = $done === $total && !$orphans && !$missingChanges
		? 'complete'
		: ($total - $done) . ' missing' . ($missingChanges ? ', ' . count($missingChanges) . ' changelog entries untranslated' : '');
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

	if ($missingChanges) {
		echo "  changelog.php\n";
		foreach ($missingChanges as $line) {
			echo "    {$line}\n";
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
