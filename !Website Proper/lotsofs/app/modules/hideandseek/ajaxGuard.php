<?php

/// Every ajax endpoint requires this first: json headers, a 500-as-json handler,
/// the session, POST-only, csrf, and the decoded body in $data.

header('Content-Type: application/json');

set_exception_handler(function ($e) {
	http_response_code(500);
	error_log((string)$e);
	echo json_encode(['error' => $e->getMessage()]);
});

require_once __ROOT__ . '/session.php';
sessionScope('hideandseek');

loadStringCatalogue('hideandseek');
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

$db = require __MODULES__ . '/hideandseek/db.php';

require_once __MODULES__ . '/hideandseek/migrate.php';
runHideAndSeekMigrations($db);

require_once __MODULES__ . '/hideandseek/auth.php';

/// One route or endpoint runs per request, so these share their names with the
/// music module's without ever meeting.
function ajaxInt($raw) {
	return is_int($raw) || (is_string($raw) && ctype_digit($raw)) ? (int)$raw : 0;
}

function ajaxText($raw) {
	return is_string($raw) ? $raw : '';
}

function ajaxTrimmed($raw) {
	return is_string($raw) ? trim($raw) : '';
}

/// A number sent as a number, for fields where empty means "clear this".
function ajaxNumericText($raw) {
	return is_int($raw) || is_float($raw) ? (string)$raw : ajaxTrimmed($raw);
}
