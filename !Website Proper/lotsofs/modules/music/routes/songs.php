<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

stringCatalogue("music");

$pageTitle = t("page.songs.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

$globalData['songs'] = $db->query("
	SELECT
		s.id,
		s.title,
		s.objective_note,
		(SELECT name FROM artist_alias
			WHERE artist_id = s.artist_id
			ORDER BY is_actual DESC LIMIT 1) AS artist,
		(SELECT score FROM account_song
			WHERE song_id = s.id AND account_id = ?) AS score
	FROM song s
	ORDER BY s.id
", [currentAccountId()])->fetchAll();

require __MODULES__ . "/music/views/songs.view.php";
