<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/aliases.php';
require_once __MODULES__ . '/music/editAudit.php';

requireMusicAdminJson($db, t('ajax.notAdmin'));

$kind = ajaxText($data['kind'] ?? null);
$id = ajaxInt($data['id'] ?? null);
$name = ajaxTrimmed($data['name'] ?? null);

requireAliasOwnerJson($db, $kind, $id);

$spec = MUSIC_ALIAS_KINDS[$kind];

if ($name === '') {
	echo json_encode(['status' => 'error', 'message' => t('alias.result.required')]);
	exit;
}

if ($db->query("SELECT id FROM {$spec['table']} WHERE {$spec['owner']} = ? AND name = ?", [$id, $name])->fetch()) {
	echo json_encode(['status' => 'duplicate', 'names' => musicAliasNames($db, $kind, $id), 'message' => t('alias.result.duplicate')]);
	exit;
}

/// As a rename refuses it: an import of that name could no longer tell the two songs apart.
if ($kind === 'song') {
	$clash = $db->query("
		SELECT s.id
		FROM song s
		JOIN song_alias sa ON sa.song_id = s.id
		JOIN song_artist art ON art.song_id = s.id
		WHERE art.artist_id IN (SELECT artist_id FROM song_artist WHERE song_id = ?) AND sa.name = ? AND s.id != ?
	", [$id, $name, $id])->fetch();

	if ($clash) {
		echo json_encode(['status' => 'error', 'message' => t('song.result.renameClash')]);
		exit;
	}
}

/// Another name, not the current one, unless it is the only name there is.
$isActual = musicCurrentAliasId($db, $kind, $id) === null ? 1 : 0;

$db->pdo->beginTransaction();
try {
	$db->query("INSERT INTO {$spec['table']} ({$spec['owner']}, name, is_actual) VALUES (?, ?, ?)", [$id, $name, $isActual]);
	musicLogEdit($db, $kind, $id, 'alias', null, $name);
	$db->pdo->commit();
}
catch (PDOException $e) {
	$db->pdo->rollBack();
	throw $e;
}

echo json_encode(['status' => 'ok', 'names' => musicAliasNames($db, $kind, $id), 'message' => '']);
