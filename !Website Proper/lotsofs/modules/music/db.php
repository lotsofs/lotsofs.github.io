<?php

$dbFile = php_sapi_name() === 'cli-server' ? 'music_test.sqlite' : 'music.sqlite';
$dbPath = __MODULES__ . '/music/database/' . $dbFile;

$dbIsNew = !file_exists($dbPath);

$db = new Database($dbPath);

// restrict a newly created database file
if ($dbIsNew) {
	@chmod($dbPath, 0640);
}

return $db;
