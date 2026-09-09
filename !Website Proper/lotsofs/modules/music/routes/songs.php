<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

stringCatalogue("music");

$pageTitle = t("page.songs.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);

$globalData['isAdmin'] = musicIsAdmin($db);

// a column name cannot be a bound parameter, so only these are ever used
$sortable = [
	'id' => 's.id',
	'artist' => 'artist COLLATE NOCASE',
	'title' => 's.title COLLATE NOCASE',
	'note' => 's.objective_note COLLATE NOCASE',
	'score' => 'score',
];

$requested = is_string($_GET['sort'] ?? null) ? $_GET['sort'] : '';
$sort = isset($sortable[$requested]) ? $requested : 'id';
$dir = ($_GET['dir'] ?? '') === 'desc' ? 'DESC' : 'ASC';

$globalData['sort'] = $sort;
$globalData['dir'] = strtolower($dir);

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
	ORDER BY {$sortable[$sort]} {$dir}, s.id
", [currentAccountId()])->fetchAll();

$columns = [
	['key' => 'id', 'type' => 'number', 'class' => 'songIdCell', 'label' => t('songs.column.id')],
	['key' => 'artist', 'type' => 'text', 'class' => 'songArtistCell', 'label' => t('songs.column.artist')],
	['key' => 'title', 'type' => 'text', 'class' => 'songTitleCell', 'label' => t('songs.column.title')],
	['key' => 'note', 'type' => 'text', 'class' => 'songNoteCell', 'label' => t('songs.column.note')],
	['key' => 'score', 'type' => 'number', 'class' => 'songScoreCell', 'label' => t('songs.column.score')],
];

$globalData['columns'] = [];
foreach ($columns as $column) {
	$isActive = $column['key'] === $sort;
	$nextDir = $isActive && $globalData['dir'] === 'asc' ? 'desc' : 'asc';

	$column['link'] = '?sort=' . $column['key'] . '&dir=' . $nextDir;
	$column['indicator'] = $isActive ? ($globalData['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
	$column['title'] = $nextDir === 'asc' ? t('songs.sortAscending') : t('songs.sortDescending');

	$globalData['columns'][] = $column;
}

require __MODULES__ . "/music/views/songs.view.php";
