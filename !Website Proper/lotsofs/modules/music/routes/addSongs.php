<?php

stringCatalogue("music");

$pageTitle = t("page.addSongs.title");

$db = require __MODULES__ . '/music/db.php';

$db->execSQL('PRAGMA foreign_keys = ON');
$db->execSQL('CREATE TABLE IF NOT EXISTS schema_migrations (filename TEXT PRIMARY KEY, applied_at TEXT NOT NULL)');

// run migrations this database hasn't had yet
$appliedMigrations = array_column($db->selectAllFromTable("schema_migrations"), 'filename');

$sqlFiles = glob(__MODULES__ . '/music/database/migrations/*.sql');
sort($sqlFiles);

foreach($sqlFiles as $file) {
    $migrationName = basename($file);
    if (in_array($migrationName, $appliedMigrations)) {
        continue;
    }

    // apply each migration in one transaction
    $db->pdo->beginTransaction();
    try {
        $db->execSQL(file_get_contents($file));
        $db->query("INSERT INTO schema_migrations (filename, applied_at) VALUES (?, ?)", [$migrationName, date('c')]);
        $db->pdo->commit();
    }
    catch (PDOException $e) {
        $db->pdo->rollBack();
        throw $e;
    }
}

$globalData['artistNames'] = $db->selectAllFromTable("artist_alias");

require __MODULES__ . "/music/views/addSongs.view.php";
