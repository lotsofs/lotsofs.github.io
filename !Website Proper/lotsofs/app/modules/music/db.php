<?php

$dbFile = php_sapi_name() === 'cli-server' ? 'music_test.sqlite' : 'music.sqlite';

if (!is_dir(__DATA__)) {
	@mkdir(__DATA__, 0700, true);
}

$dbPath = __DATA__ . '/' . $dbFile;

$dbIsNew = !file_exists($dbPath);

$db = new Database($dbPath);

if ($dbIsNew) {
	@chmod($dbPath, 0640);
}

return $db;
