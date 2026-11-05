<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/relationships.php';
require_once __MODULES__ . '/music/ratings.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$action = ajaxText($data['action'] ?? null);

/// Every song whose list changed, since a row shows on both songs' cards. An object even when the ids happen to run 0, 1.
function relationsOf($db, $songIds) {
	return (object)musicSongRelations($db, array_values(array_unique($songIds)));
}

/* A link of a lending kind changes what those songs borrow, so the answer also
   carries every account's ratings on them and their statistics, as the poll does. */
function relationAnswer($db, $status, $songIds, $message) {
	$songIds = array_values(array_unique($songIds));
	musicRatingView($db);

	echo json_encode([
		'status' => $status,
		'relations' => relationsOf($db, $songIds),
		'ratings' => musicEffectiveRatings($db, $songIds),
		'stats' => array_map(fn($songId) => musicSongStatsRow($db, $songId), $songIds),
		'message' => $message,
	]);
}

function refuseRelation($message) {
	echo json_encode(['status' => 'error', 'message' => $message]);
	exit;
}

if ($action === 'remove') {
	$id = ajaxInt($data['id'] ?? null);
	$row = $db->query("SELECT variant_song_id, target_song_id FROM song_relationship WHERE id = ?", [$id])->fetch();

	if (!$row) {
		refuseRelation(t('relation.result.notFound'));
	}

	$db->query("DELETE FROM song_relationship WHERE id = ?", [$id]);
	relationAnswer($db, 'ok', [(int)$row['variant_song_id'], (int)$row['target_song_id']], '');
	exit;
}

if ($action !== 'add' && $action !== 'update') {
	http_response_code(400);
	echo json_encode(['error' => 'Unknown action']);
	exit;
}

$songId = ajaxInt($data['song_id'] ?? null);
$otherId = ajaxInt($data['other_song_id'] ?? null);
$type = ajaxText($data['type'] ?? null);
$role = ajaxText($data['role'] ?? null);

/// An update names its row, which has to have this song at one end; the song it pointed at loses the line.
$id = 0;
$formerOtherId = null;
if ($action === 'update') {
	$id = ajaxInt($data['id'] ?? null);
	$row = $db->query("SELECT variant_song_id, target_song_id FROM song_relationship WHERE id = ?", [$id])->fetch();
	$ends = $row ? [(int)$row['variant_song_id'], (int)$row['target_song_id']] : [];

	if (!in_array($songId, $ends, true)) {
		refuseRelation(t('relation.result.notFound'));
	}
	$formerOtherId = $ends[0] === $songId ? $ends[1] : $ends[0];
}

requireSongJson($db, $songId);
requireSongJson($db, $otherId);

if ($songId === $otherId) {
	refuseRelation(t('relation.result.sameSong'));
}

if (!isset(musicSongRelationTypes()[$type]) || !isset(MUSIC_RELATION_ROLES[$role])) {
	refuseRelation(t('relation.result.unknownType'));
}

/// The role is this song's end of the row.
[$variantId, $targetId] = $role === 'variant' ? [$songId, $otherId] : [$otherId, $songId];

if ($action === 'add') {
	$status = musicAddSongRelation($db, $variantId, $targetId, $type);
}
elseif (musicSongRelationExists($db, $variantId, $targetId, $type, $id)) {
	$status = 'duplicate';
}
else {
	$db->query("UPDATE song_relationship SET variant_song_id = ?, target_song_id = ?, relationship_type = ? WHERE id = ?", [$variantId, $targetId, $type, $id]);
	$status = 'ok';
}

$touched = $formerOtherId === null ? [$songId, $otherId] : [$songId, $otherId, $formerOtherId];

relationAnswer($db, $status, $touched, $status === 'duplicate' ? t('relation.result.duplicate') : '');
