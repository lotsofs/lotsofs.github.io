<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

loadStringCatalogue('music');

$pageTitle = t("album.list.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);

$globalData['isAdmin'] = musicIsAdmin($db);

require_once __MODULES__ . '/music/links.php';

$globalData['albums'] = $db->query("
	SELECT
		al.id,
		al.artist_id,
		al.release_year,
		(SELECT name FROM album_alias WHERE album_id = al.id ORDER BY is_actual DESC, id LIMIT 1) AS name,
		(SELECT group_concat(name, ', ') FROM (
			SELECT name FROM album_alias
			WHERE album_id = al.id
				AND id != (SELECT id FROM album_alias WHERE album_id = al.id ORDER BY is_actual DESC, id LIMIT 1)
			ORDER BY name COLLATE NOCASE
		)) AS aliases,
		(SELECT name FROM artist_alias
			WHERE artist_id = al.artist_id
			ORDER BY is_actual DESC, id LIMIT 1) AS artist
	FROM album al
	ORDER BY name COLLATE NOCASE, al.id
")->fetchAll();

require __MODULES__ . "/music/views/albums.view.php";
