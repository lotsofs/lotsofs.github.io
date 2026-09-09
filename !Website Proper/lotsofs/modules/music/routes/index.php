<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

stringCatalogue("music");

$pageTitle = t("page.music.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);

$globalData['isAdmin'] = musicIsAdmin($db);

require __MODULES__ . "/music/views/index.view.php";
