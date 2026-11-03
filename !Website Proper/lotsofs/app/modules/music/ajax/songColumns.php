<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/songColumns.php';

requireMusicAccountJson($db, t('ajax.notLoggedIn'));

$column = musicSongColumnKey($data['column'] ?? null);
$shown = $data['shown'] ?? null;

if ($column === null || !is_bool($shown)) {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown column']);
	exit;
}

$db->query("
	INSERT INTO account_song_column (account_id, column_key, shown)
	VALUES (?, ?, ?)
	ON CONFLICT (account_id, column_key)
	DO UPDATE SET shown = excluded.shown
", [(int)currentAccountId(), $column, $shown ? 1 : 0]);

echo json_encode(['status' => 'ok']);
