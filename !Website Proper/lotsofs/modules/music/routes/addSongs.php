<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

stringCatalogue("music");

$pageTitle = t("page.addSongs.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);
requireMusicAdmin($db, '/music/songs');

$globalData['isAdmin'] = true;
$globalData['artistNames'] = $db->selectAllFromTable("artist_alias");

require __MODULES__ . "/music/views/addSongs.view.php";
