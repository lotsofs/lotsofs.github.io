<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

loadStringCatalogue('music');

$pageTitle = t("artist.list.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);

$globalData['isAdmin'] = musicIsAdmin($db);

$globalData['artists'] = $db->query("
	SELECT
		a.id,
		(SELECT name FROM artist_alias WHERE artist_id = a.id ORDER BY is_actual DESC, id LIMIT 1) AS name,
		(SELECT group_concat(name, ', ') FROM (
			SELECT name FROM artist_alias
			WHERE artist_id = a.id
				AND id != (SELECT id FROM artist_alias WHERE artist_id = a.id ORDER BY is_actual DESC, id LIMIT 1)
			ORDER BY name COLLATE NOCASE
		)) AS aliases
	FROM artist a
	ORDER BY name COLLATE NOCASE, a.id
")->fetchAll();

require_once __MODULES__ . '/music/stats.php';

$raterCount = (int)$db->query("SELECT COUNT(*) AS raters FROM account")->fetch()['raters'];

$songCounts = [];
foreach ($db->query("SELECT artist_id, COUNT(DISTINCT song_id) AS songs FROM song_artist GROUP BY artist_id")->fetchAll() as $row) {
	$songCounts[(int)$row['artist_id']] = (int)$row['songs'];
}

$albumCounts = [];
foreach ($db->query("SELECT artist_id, COUNT(*) AS albums FROM album WHERE artist_id IS NOT NULL GROUP BY artist_id")->fetchAll() as $row) {
	$albumCounts[(int)$row['artist_id']] = (int)$row['albums'];
}

$scoresByArtist = [];
foreach ($db->query("
	SELECT sa.artist_id, acs.score
	FROM song_artist sa
	JOIN account_song acs ON acs.song_id = sa.song_id
	WHERE acs.score IS NOT NULL
")->fetchAll() as $row) {
	$scoresByArtist[(int)$row['artist_id']][] = (float)$row['score'];
}

foreach ($globalData['artists'] as $index => $artist) {
	$artistId = (int)$artist['id'];
	$songs = $songCounts[$artistId] ?? 0;

	$globalData['artists'][$index] = array_merge(
		$artist,
		musicScoreStats($scoresByArtist[$artistId] ?? []),
		[
			'songs' => $songs,
			'albums' => $albumCounts[$artistId] ?? 0,
			'possible' => $songs * $raterCount,
		]
	);
}

require_once __MODULES__ . '/music/listSort.php';

$columns = array_merge([
	'id' => [
		'label' => t('artist.column.id'),
		'class' => 'listIdCell',
		'value' => fn($artist) => (int)$artist['id'],
	],
	'name' => [
		'label' => t('artist.column.name'),
		'class' => 'listNameCell',
		'value' => fn($artist) => $artist['name'],
		'text' => true,
	],
	'aliases' => [
		'label' => t('artist.column.aliases'),
		'class' => 'listAliasCell',
		'value' => fn($artist) => $artist['aliases'],
		'text' => true,
	],
	'songs' => [
		'label' => t('artist.card.songCount'),
		'class' => 'listCountCell',
		'value' => fn($artist) => (int)$artist['songs'],
	],
	'albums' => [
		'label' => t('artist.card.albums'),
		'class' => 'listCountCell',
		'value' => fn($artist) => (int)$artist['albums'],
	],
], musicListStatColumns());

$sort = musicListSort($columns, $_GET['sort'] ?? null, 'name');
$dir = musicListDir($_GET['dir'] ?? null);

$globalData['artists'] = musicListSorted($globalData['artists'], $columns[$sort], $dir);
$globalData['columns'] = musicListHeaders($columns, $sort, $dir);

require __MODULES__ . "/music/views/artists.view.php";


