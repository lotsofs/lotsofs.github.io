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

require_once __MODULES__ . '/music/stats.php';

$raterCount = (int)$db->query("SELECT COUNT(*) AS raters FROM account")->fetch()['raters'];

$trackTotals = [];
foreach ($db->query("
	SELECT at.album_id, COUNT(*) AS tracks, SUM(s.duration) AS duration
	FROM album_track at
	JOIN song s ON s.id = at.song_id
	GROUP BY at.album_id
")->fetchAll() as $row) {
	$trackTotals[(int)$row['album_id']] = ['tracks' => (int)$row['tracks'], 'duration' => $row['duration']];
}

$scoresByAlbum = [];
foreach ($db->query("
	SELECT at.album_id, acs.score
	FROM album_track at
	JOIN account_song acs ON acs.song_id = at.song_id
	WHERE acs.score IS NOT NULL
")->fetchAll() as $row) {
	$scoresByAlbum[(int)$row['album_id']][] = (float)$row['score'];
}

foreach ($globalData['albums'] as $index => $album) {
	$albumId = (int)$album['id'];
	$tracks = $trackTotals[$albumId]['tracks'] ?? 0;

	$globalData['albums'][$index] = array_merge(
		$album,
		musicScoreStats($scoresByAlbum[$albumId] ?? []),
		[
			'tracks' => $tracks,
			'duration' => $trackTotals[$albumId]['duration'] ?? null,
			'possible' => $tracks * $raterCount,
		]
	);
}

require_once __MODULES__ . '/music/listSort.php';

$columns = array_merge([
	'id' => [
		'label' => t('album.column.id'),
		'class' => 'listIdCell',
		'value' => fn($album) => (int)$album['id'],
	],
	'name' => [
		'label' => t('album.column.name'),
		'class' => 'listNameCell',
		'value' => fn($album) => $album['name'],
		'text' => true,
	],
	'aliases' => [
		'label' => t('album.column.aliases'),
		'class' => 'listAliasCell',
		'value' => fn($album) => $album['aliases'],
		'text' => true,
	],
	'artist' => [
		'label' => t('album.column.artist'),
		'class' => 'listArtistCell',
		'value' => fn($album) => $album['artist'],
		'text' => true,
	],
	'year' => [
		'label' => t('album.column.year'),
		'class' => 'listYearCell',
		'value' => fn($album) => $album['release_year'],
	],
	'tracks' => [
		'label' => t('album.column.tracks'),
		'class' => 'listCountCell',
		'value' => fn($album) => (int)$album['tracks'],
	],
	'duration' => [
		'label' => t('song.column.duration'),
		'class' => 'listDurationCell',
		'value' => fn($album) => $album['duration'],
	],
], musicListStatColumns());

$sort = musicListSort($columns, $_GET['sort'] ?? null, 'name');
$dir = musicListDir($_GET['dir'] ?? null);

$globalData['albums'] = musicListSorted($globalData['albums'], $columns[$sort], $dir);
$globalData['columns'] = musicListHeaders($columns, $sort, $dir);

require __MODULES__ . "/music/views/albums.view.php";


