<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

stringCatalogue("music");

$pageTitle = t("song.list.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);

$globalData['isAdmin'] = musicIsAdmin($db);

$accountId = (int)currentAccountId();

$raters = $db->query("
	SELECT a.id, a.account_name
	FROM account a
	ORDER BY a.id = ? DESC, a.account_name COLLATE NOCASE
", [$accountId])->fetchAll();

$globalData['showSharedNote'] = false;

$sortable = [
	'id' => 's.id',
	'artist' => 'artist COLLATE NOCASE',
	'title' => 'title COLLATE NOCASE',
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

$rawArtist = filter_input(INPUT_GET, 'artist', FILTER_VALIDATE_INT);
$rawAlbum = filter_input(INPUT_GET, 'album', FILTER_VALIDATE_INT);

$artistOptions = $db->query("
	SELECT a.id,
		(SELECT name FROM artist_alias WHERE artist_id = a.id ORDER BY is_actual DESC LIMIT 1) AS name
	FROM artist a
	ORDER BY name COLLATE NOCASE, a.id
")->fetchAll();

$filterArtist = null;
foreach ($artistOptions as $option) {
	if ((int)$option['id'] === $rawArtist) {
		$filterArtist = $rawArtist;
	}
}

$albumOptions = $db->query("
	SELECT al.id, al.artist_id,
		(SELECT name FROM album_alias WHERE album_id = al.id ORDER BY is_actual DESC LIMIT 1) AS name
	FROM album al
	ORDER BY name COLLATE NOCASE, al.id
")->fetchAll();

$filterAlbum = null;
foreach ($albumOptions as $option) {
	$inScope = $filterArtist !== null
		? (int)$option['artist_id'] === $filterArtist
		: $option['artist_id'] === null;
	if ((int)$option['id'] === $rawAlbum && $inScope) {
		$filterAlbum = $rawAlbum;
	}
}

$globalData['artistOptions'] = $artistOptions;
$globalData['albumOptions'] = $albumOptions;
$globalData['filterArtist'] = $filterArtist;
$globalData['filterAlbum'] = $filterAlbum;

$globalData['songs'] = $db->query("
	SELECT
		s.id,
		s.artist_id,
		(SELECT group_concat(album_id) FROM album_track WHERE song_id = s.id) AS album_ids,
		st.name AS title,
		s.objective_note,
		(SELECT name FROM artist_alias
			WHERE artist_id = s.artist_id
			ORDER BY is_actual DESC LIMIT 1) AS artist
		{$selects}
	FROM song s
	LEFT JOIN song_alias st ON st.song_id = s.id AND st.is_actual = 1
	{$joins}
	ORDER BY {$sortable[$sort]} {$dir}, s.id
")->fetchAll();

$columns = [
	['key' => 'id', 'type' => 'number', 'class' => 'songIdCell', 'label' => t('song.column.id')],
	['key' => 'artist', 'type' => 'text', 'class' => 'songArtistCell', 'label' => t('song.column.artist')],
	['key' => 'title', 'type' => 'text', 'class' => 'songTitleCell', 'label' => t('song.column.title')],
];

if ($globalData['showSharedNote']) {
	$columns[] = ['key' => 'note', 'type' => 'text', 'class' => 'songNoteCell', 'label' => t('song.column.note')];
}

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
		'label' => t('song.column.ratingScore'),
		'group' => "rater_{$id}",
		'groupStart' => true,
		'groupLabel' => $rater['account_name'],
		'groupClass' => 'songRaterGroup' . ($isMine ? ' songMineCell songMineGroup' : ''),
	];
	$columns[] = [
		'key' => "note_{$id}",
		'type' => 'text',
		'class' => $raters[$index]['noteClass'],
		'label' => t('song.column.ratingNote'),
		'group' => "rater_{$id}",
	];
}

$globalData['raters'] = $raters;
$globalData['accountId'] = $accountId;

$filterQuery = ($filterArtist !== null ? '&artist=' . $filterArtist : '')
	. ($filterAlbum !== null ? '&album=' . $filterAlbum : '');

$globalData['columns'] = [];
foreach ($columns as $index => $column) {
	$column['index'] = $index;

	$isActive = $column['key'] === $sort;
	$nextDir = $isActive && $globalData['dir'] === 'asc' ? 'desc' : 'asc';

	$column['link'] = '?sort=' . $column['key'] . '&dir=' . $nextDir . $filterQuery;
	$column['indicator'] = $isActive ? ($globalData['dir'] === 'asc' ? ' ▲' : ' ▼') : '';
	$column['title'] = $nextDir === 'asc' ? t('song.list.sortAscending') : t('song.list.sortDescending');

	$globalData['columns'][] = $column;
}

require __MODULES__ . "/music/views/songs.view.php";
