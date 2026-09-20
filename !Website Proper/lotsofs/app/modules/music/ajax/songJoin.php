<?php

$targetId = ajaxInt($data[$join['idKey']] ?? null);
$action = ajaxText($data['action'] ?? null);

requireSongJson($db, $songId);

if ($action === 'remove') {
	$db->query("DELETE FROM {$join['table']} WHERE song_id = ? AND {$join['column']} = ?", [$songId, $targetId]);
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

$db->query($join['insert'], [$songId, $targetId]);

echo json_encode(['status' => 'ok', 'message' => '']);
