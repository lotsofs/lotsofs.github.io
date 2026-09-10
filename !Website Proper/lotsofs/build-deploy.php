<?php

// Rebuilds _deploy/ : a drag-and-drop copy of exactly what belongs on the
// production web server. Its contents map onto the server's document root.
//
//   php build-deploy.php
//
// _deploy/ and this script never ship. See the plan / TODO for deploy notes.

$root = __DIR__;
$out = $root . '/_deploy';

$excludePath = [
	'_deploy',
	'build-deploy.php',
	'.gitignore',
	'TODO.md',
	'tests',
	'modules/music/notes.txt',
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

function skip($rel, $name) {
	global $excludePath, $excludeName, $excludeExt;

	if (in_array($rel, $excludePath, true)) {
		return true;
	}
	if (in_array($name, $excludeName, true)) {
		return true;
	}
	return in_array(strtolower(pathinfo($name, PATHINFO_EXTENSION)), $excludeExt, true);
}

$copied = 0;
$skipped = [];

function copyTree($srcDir, $dstDir, $rel) {
	global $copied, $skipped;

	mkdir($dstDir, 0755, true);

	foreach (scandir($srcDir) as $entry) {
		if ($entry === '.' || $entry === '..') {
			continue;
		}

		$childRel = $rel === '' ? $entry : $rel . '/' . $entry;
		$src = $srcDir . '/' . $entry;

		if (skip($childRel, $entry)) {
			$skipped[] = $childRel;
			continue;
		}

		if (is_dir($src)) {
			copyTree($src, $dstDir . '/' . $entry, $childRel);
		}
		else {
			copy($src, $dstDir . '/' . $entry);
			$copied++;
		}
	}
}

rrmdir($out);
copyTree($root, $out, '');

echo "Copied {$copied} files into _deploy/\n";
sort($skipped);
echo 'Skipped: ' . implode(', ', $skipped) . "\n";

$mustNotExist = [
	'tests',
	'TODO.md',
	'.gitignore',
	'build-deploy.php',
	'_deploy',
	'modules/music/notes.txt',
	'modules/music/database/music_test.sqlite',
	'database/test_db.sqlite',
];

$mustExist = [
	'index.php',
	'config.php',
	'session.php',
	'router.php',
	'util.php',
	'favicon.ico',
	'modules/music/database/.htaccess',
	'modules/music/database/migrations/001_create.sql',
	'modules/music/ajax/songRatingPoll.php',
	'modules/music/inviteCode.php',
	'secure/cacert.pem',
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

echo "Sanity checks passed. Drag the contents of _deploy/ onto the web root.\n";
