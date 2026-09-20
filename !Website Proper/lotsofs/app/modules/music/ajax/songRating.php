<?php

require_once __MODULES__ . '/music/ajaxGuard.php';

requireMusicAccountJson($db, t('ajax.notLoggedIn'));

$accountId = (int)currentAccountId();

$fields = [
	'score' => 'score',
	'note' => 'subjective_note',
];

$songId = ajaxInt($data['id'] ?? null);
$field = ajaxText($data['field'] ?? null);
$value = ajaxTrimmed($data['value'] ?? null);

if (!isset($fields[$field])) {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown field']);
	exit;
}

requireSongJson($db, $songId, ['value' => '']);

$existing = $db->query("SELECT score, subjective_note FROM account_song WHERE account_id = ? AND song_id = ?", [$accountId, $songId])->fetch();

if ($field === 'score' && $value !== '' && !is_numeric($value)) {
	echo json_encode([
		'status' => 'error',
		'value' => $existing && $existing['score'] !== null ? (float)$existing['score'] : '',
		'message' => t('song.result.badScore'),
	]);
	exit;
}

$stored = $value === '' ? null : ($field === 'score' ? (float)$value : $value);

$auditValue = function ($raw) use ($field) {
	if ($raw === null || $raw === '') {
		return null;
	}
	return $field === 'score' ? (string)(float)$raw : (string)$raw;
};

$newValue = $auditValue($stored);
$previousValue = $auditValue($existing ? $existing[$fields[$field]] : null);

$db->pdo->beginTransaction();
try {
	$db->query("
		INSERT INTO account_song (account_id, song_id, {$fields[$field]}, updated_at)
		VALUES (?, ?, ?, ?)
		ON CONFLICT (account_id, song_id)
		DO UPDATE SET {$fields[$field]} = excluded.{$fields[$field]},
			updated_at = excluded.updated_at
	", [$accountId, $songId, $stored, time()]);

	if ($newValue !== $previousValue) {
		$db->query("
			INSERT INTO rating_audit (account_id, song_id, field, value, previous_value, created_at)
			VALUES (?, ?, ?, ?, ?, ?)
		", [$accountId, $songId, $field, $newValue, $previousValue, time()]);
	}

	$db->pdo->commit();
}
catch (PDOException $e) {
	$db->pdo->rollBack();
	throw $e;
}

echo json_encode([
	'status' => 'ok',
	'value' => $stored === null ? '' : $stored,
	'message' => '',
]);
