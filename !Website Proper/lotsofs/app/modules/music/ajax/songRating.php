<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/stats.php';
require_once __MODULES__ . '/music/ratings.php';

requireMusicAccountJson($db, t('ajax.notLoggedIn'));

$accountId = (int)currentAccountId();

$fields = [
	'score' => 'score',
	'note' => 'subjective_note',
];

$songId = ajaxInt($data['id'] ?? null);
$field = ajaxText($data['field'] ?? null);
$value = ajaxNumericText($data['value'] ?? null);

if (!isset($fields[$field])) {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown field']);
	exit;
}

requireSongJson($db, $songId, ['value' => '']);

$existing = $db->query("SELECT score, subjective_note FROM account_song WHERE account_id = ? AND song_id = ?", [$accountId, $songId])->fetch();

if ($field === 'score' && $value !== '') {
	$value = str_replace(',', '.', $value);

	if (!is_numeric($value) || (float)$value < 0 || (float)$value > 10) {
		echo json_encode([
			'status' => 'error',
			'value' => $existing && $existing['score'] !== null ? (float)$existing['score'] : '',
			'message' => t('song.result.badScore'),
		]);
		exit;
	}
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

/// A rewrite of the same value writes nothing, so updated_at stays when the rating last changed.
if ($newValue !== $previousValue) {
	$db->pdo->beginTransaction();
	try {
		$db->query("
			INSERT INTO account_song (account_id, song_id, {$fields[$field]}, updated_at)
			VALUES (?, ?, ?, ?)
			ON CONFLICT (account_id, song_id)
			DO UPDATE SET {$fields[$field]} = excluded.{$fields[$field]},
				updated_at = excluded.updated_at
		", [$accountId, $songId, $stored, time()]);

		$db->query("
			INSERT INTO rating_audit (account_id, song_id, field, value, previous_value, created_at)
			VALUES (?, ?, ?, ?, ?, ?)
		", [$accountId, $songId, $field, $newValue, $previousValue, time()]);

		$db->pdo->commit();
	}
	catch (PDOException $e) {
		$db->pdo->rollBack();
		throw $e;
	}
}

musicRatingView($db);

/* What this write changed besides its own cell: this account's ratings on the
   songs it lends to, and on this one, which borrows again once cleared; and the
   statistics of the songs it lends to. */
$versionIds = musicVersionIds($db, [$songId]);

echo json_encode([
	'status' => 'ok',
	'value' => $stored === null ? '' : $stored,
	'message' => '',
	'stats' => musicSongStatsRow($db, $songId),
	'ratings' => musicEffectiveRatings($db, array_merge([$songId], $versionIds), [$accountId]),
	'relatedStats' => array_map(fn($versionId) => musicSongStatsRow($db, $versionId), $versionIds),
]);
