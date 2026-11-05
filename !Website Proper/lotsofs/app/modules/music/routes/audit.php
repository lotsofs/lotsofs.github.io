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

require_once __MODULES__ . '/music/format.php';

$globalData['isAdmin'] = musicIsAdmin($db);

$pageSize = 100;

/// Ratings and admin edits are two logs with two id sequences, so a page ends at an entry's time, log and id.
$before = null;
if (preg_match('/^(\d+)-(rating|edit)-(\d+)$/', (string)filter_input(INPUT_GET, 'before'), $match)) {
	$before = ['at' => (int)$match[1], 'source' => $match[2], 'id' => (int)$match[3]];
}

$where = '';
$params = [];

if ($before) {
	$where = "WHERE created_at < ? OR (created_at = ? AND (source < ? OR (source = ? AND id < ?)))";
	$params = [$before['at'], $before['at'], $before['source'], $before['source'], $before['id']];
}

$params[] = $pageSize + 1;

$rows = $db->query("
	SELECT
		page.*,
		a.account_name,
		CASE page.kind
			WHEN 'song' THEN (SELECT name FROM song_alias WHERE song_id = page.entity_id ORDER BY is_actual DESC, id LIMIT 1)
			WHEN 'album' THEN (SELECT name FROM album_alias WHERE album_id = page.entity_id ORDER BY is_actual DESC, id LIMIT 1)
			WHEN 'artist' THEN (SELECT name FROM artist_alias WHERE artist_id = page.entity_id ORDER BY is_actual DESC, id LIMIT 1)
		END AS subject_name,
		CASE page.kind
			WHEN 'song' THEN (SELECT group_concat(artist_name, ', ') FROM (
				SELECT (SELECT name FROM artist_alias WHERE artist_id = sa.artist_id
					ORDER BY is_actual DESC, id LIMIT 1) AS artist_name
				FROM song_artist sa WHERE sa.song_id = page.entity_id ORDER BY sa.id
			))
			WHEN 'album' THEN (SELECT (SELECT name FROM artist_alias WHERE artist_id = al.artist_id
				ORDER BY is_actual DESC, id LIMIT 1) FROM album al WHERE al.id = page.entity_id)
		END AS subject_artists
	FROM (
		SELECT * FROM (
			SELECT 'rating' AS source, id, account_id, 'song' AS kind, song_id AS entity_id, field, value, previous_value, created_at FROM rating_audit
			UNION ALL
			SELECT 'edit', id, account_id, kind, entity_id, field, value, previous_value, created_at FROM edit_audit
		)
		{$where}
		ORDER BY created_at DESC, source DESC, id DESC
		LIMIT ?
	) page
	LEFT JOIN account a ON a.id = page.account_id
	ORDER BY page.created_at DESC, page.source DESC, page.id DESC
", $params)->fetchAll();

$entries = array_slice($rows, 0, $pageSize);

foreach ($entries as &$entry) {
	if ($entry['kind'] === 'album') {
		$entry['subject'] = t('audit.subject.album', ['name' => musicSongLabel($entry['subject_artists'], $entry['subject_name'] ?? t('album.list.noName'))]);
	}
	elseif ($entry['kind'] === 'artist') {
		$entry['subject'] = t('audit.subject.artist', ['name' => $entry['subject_name'] ?? t('artist.list.noName')]);
	}
	else {
		$entry['subject'] = musicSongLabel($entry['subject_artists'], $entry['subject_name']);
	}
}
unset($entry);

$oldest = $entries ? end($entries) : null;

$globalData['auditHasOlder'] = count($rows) > $pageSize;
$globalData['auditEntries'] = $entries;
$globalData['auditOlderCursor'] = $oldest ? "{$oldest['created_at']}-{$oldest['source']}-{$oldest['id']}" : '';
$globalData['auditIsFirstPage'] = !$before;

require __MODULES__ . "/music/views/audit.view.php";
