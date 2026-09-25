<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$id = ajaxInt($data['id'] ?? null);
$field = ajaxText($data['field'] ?? null);
$value = ajaxTrimmed($data['value'] ?? null);

if ($field !== 'title') {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown field']);
	exit;
}

$song = $db->query("
	SELECT (SELECT artist_id FROM song_artist WHERE song_id = s.id ORDER BY id LIMIT 1) AS artist_id, st.name AS title
	FROM song s
	LEFT JOIN song_alias st ON st.song_id = s.id AND st.is_actual = 1
	WHERE s.id = ?
", [$id])->fetch();

if (!$song) {
	echo json_encode(['status' => 'error', 'value' => '', 'message' => t('song.result.notFound')]);
	exit;
}

if ($value === '') {
	echo json_encode(['status' => 'error', 'value' => $song['title'] ?? '', 'message' => t('song.result.required')]);
	exit;
}

$clash = $db->query("
	SELECT s.id
	FROM song s
	JOIN song_alias sa ON sa.song_id = s.id
	JOIN song_artist art ON art.song_id = s.id
	WHERE art.artist_id = ? AND sa.name = ? AND s.id != ?
", [$song['artist_id'], $value, $id])->fetch();

if ($clash) {
	echo json_encode(['status' => 'error', 'value' => $song['title'] ?? '', 'message' => t('song.result.renameClash')]);
	exit;
}

/// The clash check above deliberately ignores this song's own names, so a
/// rename onto one of its existing aliases reaches here - and renaming the
/// actual row onto that name would collide with idx_song_alias_unique and come
/// back as a 500 carrying the raw SQL. Promote the alias it already has
/// instead, the way albumEdit.php and artistAlias.php both do. Clearing has to
/// land before setting: idx_song_alias_one_actual allows one per song.
$existing = $db->query("SELECT id FROM song_alias WHERE song_id = ? AND name = ?", [$id, $value])->fetch();

if ($existing) {
	$db->pdo->beginTransaction();
	try {
		$db->query("UPDATE song_alias SET is_actual = 0 WHERE song_id = ?", [$id]);
		$db->query("UPDATE song_alias SET is_actual = 1 WHERE id = ?", [$existing['id']]);
		$db->pdo->commit();
	}
	catch (PDOException $e) {
		$db->pdo->rollBack();
		throw $e;
	}
}
else if ($song['title'] === null) {
	$db->query("INSERT INTO song_alias (song_id, name, is_actual) VALUES (?, ?, 1)", [$id, $value]);
}
else {
	$db->query("UPDATE song_alias SET name = ? WHERE song_id = ? AND is_actual = 1", [$value, $id]);
}

echo json_encode(['status' => 'ok', 'value' => $value, 'message' => '']);
