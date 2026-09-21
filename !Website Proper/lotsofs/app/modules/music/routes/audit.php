<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

loadStringCatalogue('music');

$pageTitle = t("audit.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);

$globalData['isAdmin'] = musicIsAdmin($db);

$pageSize = 100;

$before = filter_input(INPUT_GET, 'before', FILTER_VALIDATE_INT);
$params = [];

if ($before) {
	$params[] = $before;
}

$params[] = $pageSize + 1;

$rows = $db->query("
	SELECT
		ra.id,
		ra.song_id,
		ra.field,
		ra.value,
		ra.previous_value,
		ra.created_at,
		a.account_name,
		(SELECT name FROM song_alias WHERE song_id = ra.song_id
			ORDER BY is_actual DESC, id LIMIT 1) AS song_title,
		(SELECT group_concat(artist_name, ', ') FROM (
			SELECT (SELECT name FROM artist_alias WHERE artist_id = sa.artist_id
				ORDER BY is_actual DESC, id LIMIT 1) AS artist_name
			FROM song_artist sa WHERE sa.song_id = ra.song_id ORDER BY sa.id
		)) AS artists
	FROM rating_audit ra
	JOIN account a ON a.id = ra.account_id
	" . ($before ? "WHERE ra.id < ?" : "") . "
	ORDER BY ra.id DESC
	LIMIT ?
", $params)->fetchAll();

$globalData['auditHasOlder'] = count($rows) > $pageSize;
$globalData['auditEntries'] = array_slice($rows, 0, $pageSize);
$globalData['auditOldestId'] = $globalData['auditEntries'] ? (int)end($globalData['auditEntries'])['id'] : 0;
$globalData['auditIsFirstPage'] = !$before;

require __MODULES__ . "/music/views/audit.view.php";
