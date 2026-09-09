<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

stringCatalogue("music");

$pageTitle = t("page.artists.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);

$globalData['isAdmin'] = musicIsAdmin($db);

$globalData['artists'] = $db->query("
	SELECT
		a.id,
		(SELECT name FROM artist_alias WHERE artist_id = a.id AND is_actual = 1) AS name,
		(SELECT group_concat(name, ', ') FROM (
			SELECT name FROM artist_alias
			WHERE artist_id = a.id AND is_actual = 0
			ORDER BY name COLLATE NOCASE
		)) AS aliases
	FROM artist a
	ORDER BY name COLLATE NOCASE, a.id
")->fetchAll();

require __MODULES__ . "/music/views/artists.view.php";
