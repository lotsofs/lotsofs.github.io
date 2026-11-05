<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/aliases.php';
require_once __MODULES__ . '/music/editAudit.php';

requireMusicAdminJson($db, t('ajax.notAdmin'));

$kind = ajaxText($data['kind'] ?? null);
$id = ajaxInt($data['id'] ?? null);
$name = ajaxText($data['name'] ?? null);
$clearListings = ($data['clear_listings'] ?? false) === true;

requireAliasOwnerJson($db, $kind, $id);

$spec = MUSIC_ALIAS_KINDS[$kind];
$alias = $db->query("SELECT id FROM {$spec['table']} WHERE {$spec['owner']} = ? AND name = ?", [$id, $name])->fetch();

if (!$alias) {
	echo json_encode(['status' => 'error', 'message' => t('alias.result.notFound')]);
	exit;
}

/// Also what keeps a thing from losing its last name.
if ((int)$alias['id'] === musicCurrentAliasId($db, $kind, $id)) {
	echo json_encode(['status' => 'error', 'message' => t('alias.result.current')]);
	exit;
}

/* album_track.song_alias_id holds a foreign key into song_alias, so a name an
   album lists the song under cannot go while it does. Asked first, and then
   those albums list the song under its current title instead. */
if ($kind === 'song' && !$clearListings) {
	$albums = array_column($db->query("
		SELECT (SELECT name FROM album_alias WHERE album_id = at.album_id ORDER BY is_actual DESC, id LIMIT 1) AS name
		FROM album_track at
		WHERE at.song_alias_id = ?
		ORDER BY name COLLATE NOCASE
	", [$alias['id']])->fetchAll(), 'name');

	if ($albums) {
		$albums = array_map(fn($album) => $album ?? t('album.list.noName'), $albums);
		echo json_encode(['status' => 'listed', 'message' => t('alias.result.listed', ['albums' => implode(', ', $albums)]), 'albums' => $albums]);
		exit;
	}
}

$db->pdo->beginTransaction();
try {
	$listedOn = [];
	if ($kind === 'song') {
		foreach ($db->query("SELECT album_id FROM album_track WHERE song_alias_id = ?", [$alias['id']])->fetchAll(PDO::FETCH_COLUMN) as $albumId) {
			$listedOn[$albumId] = musicAuditTrackLine($db, $albumId, $id);
		}
		$db->query("UPDATE album_track SET song_alias_id = NULL WHERE song_alias_id = ?", [$alias['id']]);
	}
	$db->query("DELETE FROM {$spec['table']} WHERE id = ?", [$alias['id']]);

	musicLogEdit($db, $kind, $id, 'alias', $name, null);
	foreach ($listedOn as $albumId => $line) {
		musicLogEdit($db, 'album', $albumId, 'track', $line, musicAuditTrackLine($db, $albumId, $id));
	}
	$db->pdo->commit();
}
catch (PDOException $e) {
	$db->pdo->rollBack();
	throw $e;
}

echo json_encode(['status' => 'ok', 'names' => musicAliasNames($db, $kind, $id), 'message' => '']);
