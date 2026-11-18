<?php

require_once __ROOT__ . '/session.php';
sessionScope('mtasa');

loadStringCatalogue('mtasa');

$db = require __MODULES__ . '/mtasa/db.php';

require_once __MODULES__ . '/mtasa/migrate.php';
runMtasaMigrations($db);

require_once __MODULES__ . '/mtasa/auth.php';
require_once __MODULES__ . '/mtasa/import.php';
requireMtasaAccount($db, '/mtasa');

/// Admin only, posted with the form's token, and refused by mtasaImport()
/// itself once anything is in the catalogue.
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !mtasaIsAdmin($db) || !checkCsrf($_POST['csrf_token'] ?? null)) {
	header('Location: /mtasa', true, 303);
	exit;
}

/// The same page reloads the analysis from classics.csv once there are maps.
if (($_POST['action'] ?? '') === 'reload') {
	$result = mtasaReloadAnalysis($db);
	if (!isset($result['error'])) {
		header('Location: /mtasa?reloaded=' . $result['analysed'] . '&added=' . $result['added'] . '&drawn=' . $result['drawn'], true, 303);
		exit;
	}
}
else {
	$result = mtasaImport($db);
}

if (isset($result['error'])) {
	/// No flash messages on this site, so the list renders here with the refusal.
	$globalData['importError'] = $result['error'];
	$_GET = [];
	require __MODULES__ . '/mtasa/routes/index.php';
	exit;
}

header('Location: /mtasa?imported=' . $result['maps'] . '&analysed=' . $result['analysed'] . '&drawn=' . $result['drawn'], true, 303);
exit;
