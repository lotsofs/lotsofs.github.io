<?php

header('Content-Type: application/json');

set_exception_handler(function ($e) {
	http_response_code(500);
	error_log((string)$e);
	echo json_encode(['error' => $e->getMessage()]);
});

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
	exit;
}

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
	http_response_code(400);
	echo json_encode(['error' => 'Expected a JSON array']);
	exit;
}

require_once __ROOT__ . '/session.php';
sessionScope('music');

stringCatalogue('music');
requireLoginJson(t('ajax.notLoggedIn'));
requireCsrfJson(t('ajax.badCsrf'));

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/auth.php';

function ajaxInt($raw) {
	return is_int($raw) || (is_string($raw) && ctype_digit($raw)) ? (int)$raw : 0;
}

function ajaxText($raw) {
	return is_string($raw) ? $raw : '';
}

function ajaxTrimmed($raw) {
	return is_string($raw) ? trim($raw) : '';
}

function requireSongJson($db, $songId, $extra = []) {
	if ($db->query("SELECT id FROM song WHERE id = ?", [$songId])->fetch()) {
		return;
	}

	echo json_encode(array_merge(['status' => 'error'], $extra, ['message' => t('song.result.notFound')]));
	exit;
}
