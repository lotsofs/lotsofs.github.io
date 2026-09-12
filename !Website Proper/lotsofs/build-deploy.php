<?php

// Rebuilds _deploy/ : the exact folder layout to drop into ~lotsofs/ on the host.
//
//   php build-deploy.php
//
// _deploy/ ends up as { lotsofs.com/, app/ }:
//   lotsofs.com/  = the repo's public/  -> the domain's web root
//   app/          = the repo's app/     -> a sibling of the web root (PHP source, not served)
//
// The SQLite DB and sessions live under /home/lotsofs/ instead, a directory
// only the host's unix user can reach (see app/util.php's __DATA__) — nothing
// to deploy for that, the app creates it on first use.
//
// Drag the contents of _deploy/ into ~lotsofs/; leave app/config.php alone on
// later deploys if it ever holds a real secret.
//
// _deploy/ and this script never ship.

$root = __DIR__;
$out = $root . '/_deploy';

// repo source dir  =>  name in _deploy/
$roots = [
	'public' => 'lotsofs.com',
	'app' => 'app',
];

// paths (relative to each source dir) to leave out
$excludeRel = [
	'public' => [],
	'app' => ['modules/music/notes.txt'],
];

$excludeName = ['.git', '.gitinclude', '.DS_Store', 'Thumbs.db'];
$excludeExt = ['sqlite', 'sqlite-journal', 'sqlite-wal', 'db', 'log', 'bak'];

function rrmdir($dir) {
	if (!is_dir($dir)) {
		return;
	}
	foreach (scandir($dir) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}
		$path = $dir . '/' . $entry;
		is_dir($path) ? rrmdir($path) : unlink($path);
	}
	rmdir($dir);
}

$copied = 0;
$skipped = [];

function copyTree($srcDir, $dstDir, $rel, $excludeRel) {
	global $copied, $skipped, $excludeName, $excludeExt;

	mkdir($dstDir, 0755, true);

	foreach (scandir($srcDir) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		$childRel = $rel === '' ? $entry : $rel . '/' . $entry;

		if (in_array($childRel, $excludeRel, true)
			|| in_array($entry, $excludeName, true)
			|| in_array(strtolower(pathinfo($entry, PATHINFO_EXTENSION)), $excludeExt, true)) {
			$skipped[] = $childRel;
			continue;
		}

		$src = $srcDir . '/' . $entry;

		if (is_dir($src)) {
			copyTree($src, $dstDir . '/' . $entry, $childRel, $excludeRel);
		}
		else {
			copy($src, $dstDir . '/' . $entry);
			$copied++;
		}
	}
}

rrmdir($out);
mkdir($out, 0755, true);

foreach ($roots as $srcName => $dstName) {
	copyTree($root . '/' . $srcName, $out . '/' . $dstName, '', $excludeRel[$srcName]);
}

echo "Copied {$copied} files into _deploy/\n";
sort($skipped);
echo 'Skipped: ' . implode(', ', $skipped) . "\n";

$mustExist = [
	'lotsofs.com/.htaccess',
	'lotsofs.com/index.php',
	'lotsofs.com/favicon.ico',
	'lotsofs.com/js/util.js',
	'lotsofs.com/modules/music/css/styles.css',
	'lotsofs.com/raw/ktane/translated.html',
	'app/.htaccess',
	'app/util.php',
	'app/router.php',
	'app/session.php',
	'app/config.php',
	'app/classes/Database.php',
	'app/modules/music/inviteCode.php',
	'app/modules/music/database/migrations/001_create.sql',
	'app/modules/music/ajaxGuard.php',
	'app/modules/music/ajax/songRatingPoll.php',
];
// app/secure/cacert.pem is gitignored (large CA bundle, referenced only by
// currently-disabled code); copied if present, not required.

$mustNotExist = [
	'lotsofs.com/util.php',
	'lotsofs.com/router.php',
	'lotsofs.com/config.php',
	'lotsofs.com/ajax',
	'lotsofs.com/modules/music/ajax',
	'app/modules/music/notes.txt',
	'data',
	'tests',
	'TODO.md',
	'.gitignore',
	'build-deploy.php',
];

$problems = [];

foreach ($mustNotExist as $rel) {
	if (file_exists($out . '/' . $rel)) {
		$problems[] = "leaked into the build: {$rel}";
	}
}

foreach ($mustExist as $rel) {
	if (!file_exists($out . '/' . $rel)) {
		$problems[] = "missing from the build: {$rel}";
	}
}

foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($out, FilesystemIterator::SKIP_DOTS)) as $file) {
	if (preg_match('/\.(sqlite|sqlite-journal|sqlite-wal|db)$/i', $file->getFilename())) {
		$problems[] = 'database file in the build: ' . $file->getPathname();
	}
}

if ($problems) {
	echo "\nPROBLEMS:\n  " . implode("\n  ", $problems) . "\n";
	exit(1);
}

echo "Sanity checks passed. Drag the contents of _deploy/ into ~lotsofs/ on the host.\n";
