<?php

require_once __MODULES__ . '/mtasa/ajaxGuard.php';
requireMtasaAccountJson($db, t('ajax.notLoggedIn'));

require_once __MODULES__ . '/mtasa/fields.php';
require_once __MODULES__ . '/mtasa/comments.php';

/// Every change to a comment thread, by anyone signed in: `add` a thread or a
/// reply, `edit` one's own comment, `delete` one's own (an admin any), and
/// `resolve` or `reopen` a thread (its author or an admin). Answers with the
/// threads of the field it touched, drawn as the page draws them.
$action = $data['action'] ?? null;
if (!in_array($action, ['add', 'edit', 'delete', 'resolve', 'reopen'], true)) {
	http_response_code(400);
	echo json_encode(['error' => t('ajax.badBody')]);
	exit;
}

$accountId = (int)currentAccountId();
$isAdmin = mtasaIsAdmin($db);

$refuse = function ($key) {
	echo json_encode(['status' => 'error', 'message' => t($key)]);
	exit;
};

if ($action === 'add') {
	$mapId = ajaxInt($data['mapId'] ?? null);
	if ($mapId === 0 || !$db->query("SELECT 1 FROM map WHERE id = ?", [$mapId])->fetch()) {
		$refuse('map.unknownMap');
	}

	$field = $data['field'] ?? null;
	if ($field === '') {
		$field = null;
	}
	if ($field !== null && (!is_string($field) || !isset(mtasaFields()[$field]))) {
		$refuse('map.unknownField');
	}

	$parentId = null;
	if (($data['parent'] ?? null) !== null) {
		$parent = mtasaComment($db, ajaxInt($data['parent']));
		if (!$parent || $parent['parent_id'] !== null || (int)$parent['map_id'] !== $mapId || $parent['field'] !== $field) {
			$refuse('comments.error.badParent');
		}
		$parentId = (int)$parent['id'];
	}

	[$body, $error] = mtasaCommentBody($data['body'] ?? null);
	if ($error !== null) {
		$refuse($error);
	}

	$db->query(
		"INSERT INTO map_comment (map_id, field, parent_id, account_id, body, created_at) VALUES (?, ?, ?, ?, ?, ?)",
		[$mapId, $field, $parentId, $accountId, $body, time()]
	);
}
else {
	$comment = mtasaComment($db, ajaxInt($data['id'] ?? null));
	if (!$comment) {
		$refuse('comments.error.unknown');
	}
	$mapId = (int)$comment['map_id'];
	$field = $comment['field'];

	if ($action === 'edit') {
		if (!mtasaCanEditComment($comment, $accountId)) {
			$refuse('comments.error.notYours');
		}
		[$body, $error] = mtasaCommentBody($data['body'] ?? null);
		if ($error !== null) {
			$refuse($error);
		}
		if ($body !== $comment['body']) {
			$db->query("UPDATE map_comment SET body = ?, edited_at = ? WHERE id = ?", [$body, time(), (int)$comment['id']]);
		}
	}
	else if ($action === 'delete') {
		if (!mtasaCanDeleteComment($comment, $accountId, $isAdmin)) {
			$refuse('comments.error.notYours');
		}
		$db->pdo->beginTransaction();
		mtasaDeleteComment($db, $comment);
		$db->pdo->commit();
	}
	else {
		if (!mtasaCanResolveThread($comment, $accountId, $isAdmin)) {
			$refuse('comments.error.notYours');
		}
		if ($action === 'resolve') {
			$db->query("UPDATE map_comment SET resolved_at = ?, resolved_by = ? WHERE id = ? AND resolved_at IS NULL", [time(), $accountId, (int)$comment['id']]);
		}
		else {
			$db->query("UPDATE map_comment SET resolved_at = NULL, resolved_by = NULL WHERE id = ?", [(int)$comment['id']]);
		}
	}
}

$threads = mtasaThreads($db, $mapId, $field)[mtasaThreadField($field)] ?? [];
$threadField = $field;
$threadMapId = $mapId;
$threadAccountId = $accountId;
$threadIsAdmin = $isAdmin;
$threadSignedIn = true;

ob_start();
require __MODULES__ . '/mtasa/views/partials/threads.php';
echo json_encode([
	'status' => 'ok',
	'field' => mtasaThreadField($field),
	'open' => count(array_filter($threads, fn($thread) => $thread['resolved_at'] === null)),
	'html' => ob_get_clean(),
]);
