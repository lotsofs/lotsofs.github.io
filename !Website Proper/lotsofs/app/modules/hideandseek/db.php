<?php

/// The built-in server gets its own database, so local dev never touches
/// production-shaped data.
$dbFile = php_sapi_name() === 'cli-server' ? 'hideandseek_test.sqlite' : 'hideandseek.sqlite';

if (!is_dir(__DATA__)) {
	@mkdir(__DATA__, 0700, true);
}

$dbPath = __DATA__ . '/' . $dbFile;

$dbIsNew = !file_exists($dbPath);

/// A fresh test database starts as a copy of the live one where there is one.
if ($dbFile === 'hideandseek_test.sqlite' && $dbIsNew) {
	$liveDbPath = __DATA__ . '/hideandseek.sqlite';
	if (file_exists($liveDbPath)) {
		copy($liveDbPath, $dbPath);
	}
}

$db = new Database($dbPath);

if ($dbIsNew) {
	@chmod($dbPath, 0640);
}

return $db;
