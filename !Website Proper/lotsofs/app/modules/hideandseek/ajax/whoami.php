<?php

/// A worked example of the ajax shape: the guard has already taken the session,
/// the method, the csrf token and the json body by the time this runs. Copy it
/// for the real endpoints.

require_once __MODULES__ . '/hideandseek/ajaxGuard.php';

requireHnsAccountJson($db, t('ajax.notLoggedIn'));

$account = hnsAccount($db);

echo json_encode([
	'status' => 'ok',
	'id' => (int)$account['id'],
	'name' => $account['account_name'],
	'isAdmin' => hnsIsAdmin($db),
	'message' => '',
]);
