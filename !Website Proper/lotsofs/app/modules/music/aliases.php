<?php

/// The three kinds of thing with names in an _alias table. SQL only ever takes
/// its table and column names from here.
const MUSIC_ALIAS_KINDS = [
	'song' => ['entity' => 'song', 'table' => 'song_alias', 'owner' => 'song_id', 'notFound' => 'song.result.notFound'],
	'album' => ['entity' => 'album', 'table' => 'album_alias', 'owner' => 'album_id', 'notFound' => 'album.card.notFound'],
	'artist' => ['entity' => 'artist', 'table' => 'artist_alias', 'owner' => 'artist_id', 'notFound' => 'artist.card.notFound'],
];

/// For the alias endpoints: a 400 for a kind there is no table for, an error for a thing that is gone.
function requireAliasOwnerJson($db, $kind, $id) {
	if (!isset(MUSIC_ALIAS_KINDS[$kind])) {
		http_response_code(400);
		echo json_encode(['error' => 'Unknown kind']);
		exit;
	}

	$spec = MUSIC_ALIAS_KINDS[$kind];

	if (!$db->query("SELECT id FROM {$spec['entity']} WHERE id = ?", [$id])->fetch()) {
		echo json_encode(['status' => 'error', 'message' => t($spec['notFound'])]);
		exit;
	}
}

/// The name a thing goes by: its actual one, else its oldest, as the cards show it.
function musicCurrentAliasId($db, $kind, $id) {
	$spec = MUSIC_ALIAS_KINDS[$kind];
	$row = $db->query("SELECT id FROM {$spec['table']} WHERE {$spec['owner']} = ? ORDER BY is_actual DESC, id LIMIT 1", [$id])->fetch();

	return $row ? (int)$row['id'] : null;
}

/// Every name, the current one first and the rest by name.
function musicAliasNames($db, $kind, $id) {
	$spec = MUSIC_ALIAS_KINDS[$kind];
	$names = array_column($db->query("SELECT name FROM {$spec['table']} WHERE {$spec['owner']} = ? ORDER BY is_actual DESC, id", [$id])->fetchAll(), 'name');

	$current = array_shift($names);
	if ($current === null) {
		return [];
	}

	usort($names, 'strcasecmp');

	return array_merge([$current], $names);
}
