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

$accountId = (int)currentAccountId();

// every account gets a column, your own first
$raters = $db->query("
	SELECT a.id, a.account_name
	FROM account a
	ORDER BY a.id = ? DESC, a.account_name COLLATE NOCASE
", [$accountId])->fetchAll();

// flip to true to bring the shared per song note back
$globalData['showSharedNote'] = false;

// a column name cannot be a bound parameter, so only these are ever used
$sortable = [
	'id' => 's.id',
	'artist' => 'artist COLLATE NOCASE',
	'title' => 's.title COLLATE NOCASE',
];

if ($globalData['showSharedNote']) {
	$sortable['note'] = 's.objective_note COLLATE NOCASE';
}

$joins = '';
$selects = '';

foreach ($raters as $rater) {
	$id = (int)$rater['id'];

	$joins .= " LEFT JOIN account_song r{$id} ON r{$id}.song_id = s.id AND r{$id}.account_id = {$id}";
	$selects .= ", r{$id}.score AS score_{$id}, r{$id}.subjective_note AS note_{$id}";

	$sortable["score_{$id}"] = "score_{$id}";
	$sortable["note_{$id}"] = "note_{$id} COLLATE NOCASE";
}

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
			ORDER BY is_actual DESC LIMIT 1) AS artist
		{$selects}
	FROM song s
	{$joins}
	ORDER BY {$sortable[$sort]} {$dir}, s.id
")->fetchAll();

$columns = [
	['key' => 'id', 'type' => 'number', 'class' => 'songIdCell', 'label' => t('songs.column.id')],
	['key' => 'artist', 'type' => 'text', 'class' => 'songArtistCell', 'label' => t('songs.column.artist')],
	['key' => 'title', 'type' => 'text', 'class' => 'songTitleCell', 'label' => t('songs.column.title')],
];

if ($globalData['showSharedNote']) {
	$columns[] = ['key' => 'note', 'type' => 'text', 'class' => 'songNoteCell', 'label' => t('songs.column.note')];
}

// every rater gets a score and a note column under one grouped header
foreach ($raters as $index => $rater) {
	$id = (int)$rater['id'];
	$isMine = $id === $accountId;

	$raters[$index]['isMine'] = $isMine;
	$raters[$index]['scoreClass'] = 'songRatingCell songRatingScoreCell' . ($isMine ? ' songMineCell songMyScoreCell' : '');
	$raters[$index]['noteClass'] = 'songRatingCell songRatingNoteCell' . ($isMine ? ' songMineCell songMyNoteCell' : '');

	$columns[] = [
		'key' => "score_{$id}",
		'type' => 'number',
		'class' => $raters[$index]['scoreClass'],
		'label' => t('songs.column.ratingScore'),
		'group' => "rater_{$id}",
		'groupStart' => true,
		'groupLabel' => $rater['account_name'],
		'groupClass' => 'songRaterGroup' . ($isMine ? ' songMineCell songMineGroup' : ''),
	];
	$columns[] = [
		'key' => "note_{$id}",
		'type' => 'text',
		'class' => $raters[$index]['noteClass'],
		'label' => t('songs.column.ratingNote'),
		'group' => "rater_{$id}",
	];
}

$globalData['raters'] = $raters;
$globalData['accountId'] = $accountId;

$globalData['columns'] = [];
foreach ($columns as $index => $column) {
	// the cell position, since a grouped header no longer sits in document order
	$column['index'] = $index;

	$isActive = $column['key'] === $sort;
	$nextDir = $isActive && $globalData['dir'] === 'asc' ? 'desc' : 'asc';

	$column['link'] = '?sort=' . $column['key'] . '&dir=' . $nextDir;
	$column['indicator'] = $isActive ? ($globalData['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
	$column['title'] = $nextDir === 'asc' ? t('songs.sortAscending') : t('songs.sortDescending');

	$globalData['columns'][] = $column;
}

require __MODULES__ . "/music/views/songs.view.php";
