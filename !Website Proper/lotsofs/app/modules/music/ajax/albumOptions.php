<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

/// Everything the album card's edit dropdowns need to offer, which is the whole
/// catalogue and nothing to do with any one album. Fetched once, on the first
/// Edit click of a page session, rather than riding along with every card.
echo json_encode([
	'status' => 'ok',
	'artists' => $db->query("
		SELECT a.id,
			(SELECT name FROM artist_alias WHERE artist_id = a.id ORDER BY is_actual DESC, id LIMIT 1) AS name
		FROM artist a
		ORDER BY name COLLATE NOCASE, a.id
	")->fetchAll(),
	'songs' => $db->query("
		SELECT s.id,
			(SELECT name FROM song_alias WHERE song_id = s.id ORDER BY is_actual DESC, id LIMIT 1) AS name,
			(SELECT group_concat(artist_name, ', ') FROM (
				SELECT (SELECT name FROM artist_alias
					WHERE artist_id = sa.artist_id
					ORDER BY is_actual DESC, id LIMIT 1) AS artist_name
				FROM song_artist sa
				WHERE sa.song_id = s.id
				ORDER BY sa.id
			)) AS artist
		FROM song s
		ORDER BY name COLLATE NOCASE, s.id
	")->fetchAll(),
]);
