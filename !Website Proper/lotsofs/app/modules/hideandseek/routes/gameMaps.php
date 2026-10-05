<?php

require_once __ROOT__ . '/session.php';
sessionScope('hideandseek');
requireLogin('/hideandseek/login');

loadStringCatalogue('hideandseek');

$pageTitle = t('gameMap.title');

$db = require __MODULES__ . '/hideandseek/db.php';

require_once __MODULES__ . '/hideandseek/migrate.php';
runHideAndSeekMigrations($db);

require_once __MODULES__ . '/hideandseek/auth.php';
require_once __MODULES__ . '/hideandseek/gameMap.php';
requireHnsAccount($db);

$globalData['isAdmin'] = hnsIsAdmin($db);
$globalData['formError'] = '';

/// Dispatched on the action rather than on which field turned up, so a post this
/// doesn't recognise does nothing at all.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	$action = $_POST['action'] ?? '';
	$mapId = ctype_digit($_POST['map_id'] ?? '') ? (int)$_POST['map_id'] : 0;
	$name = trim($_POST['name'] ?? '');

	$redirect = '/hideandseek/game-maps';

	if ($action === 'create') {
		$globalData['formError'] = hnsGameMapNameError($db, $name);

		if ($globalData['formError'] === '') {
			$db->query("INSERT INTO game_map (name) VALUES (?)", [$name]);

			/// Opened as soon as it exists: making one and then having to load it
			/// is a step with no decision in it.
			$newId = (int)$db->pdo->lastInsertId();
			hnsLoadGameMap($newId);
			$redirect .= '?map=' . $newId;
		}
	}
	else if ($action === 'rename' && $mapId !== 0) {
		$globalData['formError'] = hnsGameMapNameError($db, $name, $mapId);

		if ($globalData['formError'] === '') {
			$db->query("UPDATE game_map SET name = ? WHERE id = ?", [$name, $mapId]);
		}
	}
	else if ($action === 'delete' && $mapId !== 0) {
		/// No session clearing here - hnsLoadedGameMap() drops an id whose row is
		/// gone, so the unloading lives in one place rather than two.
		$db->query("DELETE FROM game_map WHERE id = ?", [$mapId]);
	}
	else if ($action === 'load' && $mapId !== 0) {
		hnsLoadGameMap($mapId);
		$redirect .= '?map=' . $mapId;
	}
	else if ($action === 'close') {
		hnsCloseGameMap();
	}

	/// Only a clean write redirects. A validation message has to survive to the
	/// render, and there is no flash mechanism here to carry it through a 302.
	if ($globalData['formError'] === '') {
		header('Location: ' . $redirect, true, 302);
		exit;
	}
}

$globalData['loadedGameMap'] = hnsLoadedGameMap($db);
$globalData['gameMaps'] = $db->query("SELECT id, name FROM game_map ORDER BY name COLLATE NOCASE")->fetchAll();

require __MODULES__ . '/hideandseek/views/gameMaps.view.php';
