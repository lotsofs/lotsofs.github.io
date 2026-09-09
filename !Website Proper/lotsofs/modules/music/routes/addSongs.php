<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

stringCatalogue("music");

$pageTitle = t("addSongs.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);
requireMusicAdmin($db, '/music/songs');

$globalData['isAdmin'] = true;
$globalData['artistNames'] = $db->selectAllFromTable("artist_alias");

$globalData['albumNames'] = $db->query("
	SELECT
		al.album_id,
		al.name,
		al.is_actual,
		(SELECT name FROM artist_alias
			WHERE artist_id = a.artist_id
			ORDER BY is_actual DESC LIMIT 1) AS artist_name
	FROM album_alias al
	JOIN album a ON a.id = al.album_id
")->fetchAll();

require __MODULES__ . "/music/views/addSongs.view.php";
