<?php

require_once __MODULES__ . '/music/editAudit.php';

$targetId = ajaxInt($data[$join['idKey']] ?? null);
$action = ajaxText($data['action'] ?? null);

requireSongJson($db, $songId);

/// An album is logged as its track line, an artist on the song by name.
function songJoinWrite($db, $join, $songId, $targetId, $sql) {
	if ($join['entity'] === 'album') {
		musicWriteTrack($db, $targetId, $songId, $sql, [$songId, $targetId]);
		return;
	}

	$linked = fn() => $db->query("SELECT id FROM {$join['table']} WHERE song_id = ? AND {$join['column']} = ?", [$songId, $targetId])->fetch()
		? musicAuditName($db, $join['entity'], $targetId)
		: null;
	$before = $linked();

	$db->pdo->beginTransaction();
	try {
		$db->query($sql, [$songId, $targetId]);
		musicLogEdit($db, 'song', $songId, $join['entity'], $before, $linked());
		$db->pdo->commit();
	}
	catch (PDOException $e) {
		$db->pdo->rollBack();
		throw $e;
	}
}

if ($action === 'remove') {
	songJoinWrite($db, $join, $songId, $targetId, "DELETE FROM {$join['table']} WHERE song_id = ? AND {$join['column']} = ?");
	echo json_encode(['status' => 'ok', 'message' => '']);
	exit;
}

if ($action !== 'add') {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown action']);
	exit;
}

if (!$db->query("SELECT id FROM {$join['entity']} WHERE id = ?", [$targetId])->fetch()) {
	echo json_encode(['status' => 'error', 'message' => $join['notFound']]);
	exit;
}

if ($db->query("SELECT id FROM {$join['table']} WHERE song_id = ? AND {$join['column']} = ?", [$songId, $targetId])->fetch()) {
	echo json_encode(['status' => 'duplicate', 'message' => $join['duplicate']]);
	exit;
}

songJoinWrite($db, $join, $songId, $targetId, $join['insert']);

echo json_encode(['status' => 'ok', 'message' => '']);
