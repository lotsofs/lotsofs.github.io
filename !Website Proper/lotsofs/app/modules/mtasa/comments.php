<?php

require_once __MODULES__ . '/mtasa/fields.php';

const MTASA_COMMENT_LIMIT = 2000;

/// The thread key for a field, '' standing for the map in general.
function mtasaThreadField($field) {
	return $field === null ? '' : $field;
}

/// A map's threads, grouped by field ('' for the map in general): each is its
/// first comment with `replies` in time order and the author's name. A field
/// the registry no longer has keeps its threads under its own key.
function mtasaThreads($db, $mapId, $field = false) {
	$sql = "
		SELECT c.*, acc.account_name AS author_name, res.account_name AS resolver_name
		FROM map_comment c
		LEFT JOIN account acc ON acc.id = c.account_id
		LEFT JOIN account res ON res.id = c.resolved_by
		WHERE c.map_id = ?";
	$params = [$mapId];
	if ($field !== false) {
		$sql .= $field === null ? " AND c.field IS NULL" : " AND c.field = ?";
		if ($field !== null) {
			$params[] = $field;
		}
	}
	$rows = $db->query($sql . " ORDER BY c.created_at, c.id", $params)->fetchAll();

	$threads = [];
	$byId = [];
	foreach ($rows as $row) {
		if ($row['parent_id'] === null) {
			$row['replies'] = [];
			$byId[(int)$row['id']] = count($threads);
			$threads[] = $row;
		}
	}
	foreach ($rows as $row) {
		if ($row['parent_id'] !== null && isset($byId[(int)$row['parent_id']])) {
			$threads[$byId[(int)$row['parent_id']]]['replies'][] = $row;
		}
	}

	$grouped = [];
	foreach ($threads as $thread) {
		$grouped[mtasaThreadField($thread['field'])][] = $thread;
	}

	return $grouped;
}

/// A comment row by id, or null.
function mtasaComment($db, $id) {
	return $db->query("SELECT * FROM map_comment WHERE id = ?", [$id])->fetch() ?: null;
}

/// Trimmed text with its line breaks as \n, or an error key.
function mtasaCommentBody($raw) {
	$body = str_replace(["\r\n", "\r"], "\n", trim(is_string($raw) ? $raw : ''));
	if ($body === '') {
		return [null, 'comments.error.empty'];
	}
	if (strlen($body) > MTASA_COMMENT_LIMIT) {
		return [null, 'comments.error.tooLong'];
	}

	return [$body, null];
}

/// Deletes one comment. A thread's first comment that has replies stays, as
/// "comment deleted", so the replies keep their context; anything else goes,
/// and a deleted first comment whose last reply goes goes with it.
function mtasaDeleteComment($db, $comment) {
	$id = (int)$comment['id'];

	if ($comment['parent_id'] === null) {
		$replies = (int)$db->query("SELECT COUNT(*) n FROM map_comment WHERE parent_id = ?", [$id])->fetch()['n'];
		if ($replies > 0) {
			$db->query("UPDATE map_comment SET body = '', deleted_at = ? WHERE id = ?", [time(), $id]);
			return;
		}
		$db->query("DELETE FROM map_comment WHERE id = ?", [$id]);
		return;
	}

	$db->query("DELETE FROM map_comment WHERE id = ?", [$id]);

	$parent = mtasaComment($db, (int)$comment['parent_id']);
	if ($parent && $parent['deleted_at'] !== null
		&& !$db->query("SELECT 1 FROM map_comment WHERE parent_id = ? LIMIT 1", [(int)$parent['id']])->fetch()) {
		$db->query("DELETE FROM map_comment WHERE id = ?", [(int)$parent['id']]);
	}
}

/// Open threads per field on one map, '' for the map in general.
function mtasaOpenThreadsByField($threads) {
	$counts = [];
	foreach ($threads as $field => $list) {
		$counts[$field] = count(array_filter($list, fn($thread) => $thread['resolved_at'] === null));
	}

	return $counts;
}

/// The latest threads across the catalogue, newest activity first.
function mtasaRecentThreads($db, $includeResolved, $limit = 200) {
	return $db->query("
		SELECT c.*, acc.account_name AS author_name,
			(SELECT COUNT(*) FROM map_comment r WHERE r.parent_id = c.id) AS reply_count,
			MAX(c.created_at, COALESCE((SELECT MAX(r.created_at) FROM map_comment r WHERE r.parent_id = c.id), 0)) AS active_at,
			COALESCE(NULLIF(m.custom_name, ''), NULLIF(m.original_name, ''), NULLIF(a.title, '')) AS map_name
		FROM map_comment c
		LEFT JOIN account acc ON acc.id = c.account_id
		LEFT JOIN map m ON m.id = c.map_id
		LEFT JOIN map_analysis a ON a.map_id = c.map_id
		WHERE c.parent_id IS NULL" . ($includeResolved ? '' : ' AND c.resolved_at IS NULL') . "
		ORDER BY active_at DESC, c.id DESC
		LIMIT " . (int)$limit
	)->fetchAll();
}

/// The name a thread's field goes by on the page.
function mtasaThreadFieldLabel($field) {
	return $field === null || $field === '' ? t('comments.general') : t('field.' . $field);
}

/// Whether this account may edit (only its author), delete (its author or an
/// admin) or resolve (a thread's author or an admin) a comment.
function mtasaCanEditComment($comment, $accountId) {
	return $comment['deleted_at'] === null && (int)$comment['account_id'] === (int)$accountId;
}

function mtasaCanDeleteComment($comment, $accountId, $isAdmin) {
	return $comment['deleted_at'] === null && ($isAdmin || (int)$comment['account_id'] === (int)$accountId);
}

function mtasaCanResolveThread($thread, $accountId, $isAdmin) {
	return $thread['parent_id'] === null && ($isAdmin || (int)$thread['account_id'] === (int)$accountId);
}
