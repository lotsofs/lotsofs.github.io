<?php

require_once __MODULES__ . '/games/ajaxGuard.php';
requireGamesAccountJson($db, t('ajax.notLoggedIn'));

require_once __MODULES__ . '/games/sims2.php';

/// The written-up vehicles, which only admins change: `add` a name, `rename`
/// one, set its `occasions` (the whole list, so an edit, an addition and a
/// removal are one write) or `delete` it. Answers with the vehicle's row as the
/// page draws it, which the script puts in place of the old one.
if (!gamesIsAdmin($db)) {
	http_response_code(403);
	echo json_encode(['status' => 'error', 'message' => t('sims2.adminsOnly')]);
	exit;
}

$action = $data['action'] ?? null;
if (!in_array($action, ['add', 'rename', 'occasions', 'delete'], true)) {
	http_response_code(400);
	echo json_encode(['error' => t('ajax.badBody')]);
	exit;
}

$vehicle = null;
if ($action !== 'add') {
	$vehicle = gamesSims2Vehicles($db, ajaxInt($data['id'] ?? null));
	if ($vehicle === null) {
		echo json_encode(['status' => 'error', 'message' => t('sims2.unknownVehicle')]);
		exit;
	}
}

$refuse = function ($message) {
	echo json_encode(['status' => 'error', 'message' => $message]);
	exit;
};

if ($action === 'add' || $action === 'rename') {
	[$name, $error] = gamesSims2VehicleName($db, ajaxText($data['name'] ?? null), $vehicle['id'] ?? 0);
	if ($error !== null) {
		$refuse($error);
	}
	$db->pdo->beginTransaction();
	if ($vehicle === null) {
		$db->query("INSERT INTO sims2_vehicle (name, occasions, updated_at) VALUES (?, '[]', ?)", [$name, time()]);
		$id = (int)$db->pdo->lastInsertId();
	}
	else {
		$db->query("UPDATE sims2_vehicle SET name = ?, updated_at = ? WHERE id = ?", [$name, time(), $vehicle['id']]);
		gamesSims2MoveVehicleTicks($db, $vehicle['name'], $name);
		$id = $vehicle['id'];
	}
	$db->pdo->commit();
}
else if ($action === 'occasions') {
	$read = gamesSims2Occasions($data['occasions'] ?? null);
	if ($read === null) {
		http_response_code(400);
		echo json_encode(['error' => t('ajax.badBody')]);
		exit;
	}
	[$occasions, $error] = $read;
	if ($error !== null) {
		$refuse($error);
	}
	$db->query(
		"UPDATE sims2_vehicle SET occasions = ?, updated_at = ? WHERE id = ?",
		[json_encode($occasions, JSON_UNESCAPED_UNICODE), time(), $vehicle['id']]
	);
	$id = $vehicle['id'];
}
else {
	/// Its ticks stay, and show again if a vehicle of that name comes back.
	$db->query("DELETE FROM sims2_vehicle WHERE id = ?", [$vehicle['id']]);
	echo json_encode(['status' => 'ok']);
	exit;
}

$vehicle = gamesSims2Vehicles($db, $id);
$vehicleChecked = (bool)$db->query(
	"SELECT 1 FROM sims2_check WHERE account_id = ? AND item_key = ?",
	[(int)currentAccountId(), gamesSims2VehicleKey($vehicle['name'])]
)->fetch();
$vehicleEditable = true;

ob_start();
require __MODULES__ . '/games/views/partials/sims2Vehicle.php';
echo json_encode(['status' => 'ok', 'vehicle' => $vehicle, 'html' => ob_get_clean()]);
