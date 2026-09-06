<?php

$pageTitle = "Artist Matching";

$dbName = php_sapi_name() === 'cli-server' ? $config['database_test'] : $config['database'];

$db = new Database($dbName);

$db->execSQL('PRAGMA foreign_keys = ON');
$db->execSQL('CREATE TABLE IF NOT EXISTS schema_migrations (filename TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');

$appliedMigrations = array_column($db->selectAllFromTable("schema_migrations"), 'filename');

$sqlFiles = glob(__ROOT__ .'/database/migrations/*.sql');
sort($sqlFiles);

foreach($sqlFiles as $file) {
    $migrationName = basename($file);
    if (in_array($migrationName, $appliedMigrations)) {
        continue;
    }

    $sql = file_get_contents($file);

    $statements = array_filter(explode(";", $sql));

    foreach ($statements as $stmt) {
        $db->execSQL($stmt);
    }

    $db->query("INSERT INTO schema_migrations (filename, applied_at) VALUES (?, ?)", [$migrationName, date('c')]);
}

$globalData['artistNames'] = $db->selectAllFromTable("artist_alias");

require __MAIN__ . "/views/artistMatching.view.php";
