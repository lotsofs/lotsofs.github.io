<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/relationships.php';
require_once __MODULES__ . '/music/ratings.php';
require_once __MODULES__ . '/music/editAudit.php';
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

/// A row in musicLogRelationEdit()'s shape, or null.
function relationRow($db, $id) {
	$row = $db->query("SELECT variant_song_id, target_song_id, relationship_type FROM song_relationship WHERE id = ?", [$id])->fetch();

	return $row ? ['variant' => (int)$row['variant_song_id'], 'target' => (int)$row['target_song_id'], 'type' => $row['relationship_type']] : null;
}

/// A write and its edit log row, in one transaction; $write answers the status and the row as it now stands.
function writeRelation($db, $before, $write) {
	$db->pdo->beginTransaction();
	try {
		[$status, $after] = $write();
		if ($status === 'ok') {
			musicLogRelationEdit($db, $before, $after);
		}
		$db->pdo->commit();
	}
	catch (PDOException $e) {
		$db->pdo->rollBack();
		throw $e;
	}

	return $status;
}

if ($action === 'remove') {
	$id = ajaxInt($data['id'] ?? null);
	$row = relationRow($db, $id);

	if (!$row) {
		refuseRelation(t('relation.result.notFound'));
	}

	writeRelation($db, $row, function () use ($db, $id) {
		$db->query("DELETE FROM song_relationship WHERE id = ?", [$id]);
		return ['ok', null];
	});
	relationAnswer($db, 'ok', [$row['variant'], $row['target']], '');
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
$former = null;
if ($action === 'update') {
	$id = ajaxInt($data['id'] ?? null);
	$former = relationRow($db, $id);
	$ends = $former ? [$former['variant'], $former['target']] : [];

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

$after = ['variant' => $variantId, 'target' => $targetId, 'type' => $type];

if ($action === 'add') {
	$status = writeRelation($db, null, fn() => [musicAddSongRelation($db, $variantId, $targetId, $type), $after]);
}
elseif (musicSongRelationExists($db, $variantId, $targetId, $type, $id)) {
	$status = 'duplicate';
}
else {
	$status = writeRelation($db, $former, function () use ($db, $variantId, $targetId, $type, $id, $after) {
		$db->query("UPDATE song_relationship SET variant_song_id = ?, target_song_id = ?, relationship_type = ? WHERE id = ?", [$variantId, $targetId, $type, $id]);
		return ['ok', $after];
	});
}

$touched = $formerOtherId === null ? [$songId, $otherId] : [$songId, $otherId, $formerOtherId];

relationAnswer($db, $status, $touched, $status === 'duplicate' ? t('relation.result.duplicate') : '');
