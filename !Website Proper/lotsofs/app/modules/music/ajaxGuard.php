<?php

header('Content-Type: application/json');

set_exception_handler(function ($e) {
	http_response_code(500);
	error_log((string)$e);
	echo json_encode(['error' => $e->getMessage()]);
});

require_once __ROOT__ . '/session.php';
sessionScope('music');

loadStringCatalogue('music');
requireLoginJson(t('ajax.notLoggedIn'));

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	echo json_encode(['error' => t('ajax.badMethod')]);
	exit;
}

requireCsrfJson(t('ajax.badCsrf'));

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
	http_response_code(400);
	echo json_encode(['error' => t('ajax.badBody')]);
	exit;
}

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

/// For the fields where empty means "clear this" and anything else is a
/// number: a caller that sends 3 rather than "3" would otherwise fall through
/// ajaxTrimmed as an empty string and quietly wipe the value instead of
/// setting it.
function ajaxNumericText($raw) {
	return is_int($raw) ? (string)$raw : ajaxTrimmed($raw);
}

function requireSongJson($db, $songId, $extra = []) {
	if ($db->query("SELECT id FROM song WHERE id = ?", [$songId])->fetch()) {
		return;
	}

	echo json_encode(array_merge(['status' => 'error'], $extra, ['message' => t('song.result.notFound')]));
	exit;
}

function requireAlbumJson($db, $albumId, $extra = []) {
	if ($db->query("SELECT id FROM album WHERE id = ?", [$albumId])->fetch()) {
		return;
	}

	echo json_encode(array_merge(['status' => 'error'], $extra, ['message' => t('album.card.notFound')]));
	exit;
}
