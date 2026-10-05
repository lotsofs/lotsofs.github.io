<?php

require_once __ROOT__ . '/session.php';

/// The loaded game map, or null. ?map= wins and is remembered; without it the
/// session's own answer stands.
function hnsLoadedGameMap($db) {
	$scope = sessionScope();

	if (isset($_GET['map']) && ctype_digit((string)$_GET['map'])) {
		$_SESSION[$scope]['gameMapId'] = (int)$_GET['map'];
	}

	$id = (int)($_SESSION[$scope]['gameMapId'] ?? 0);

	if ($id === 0) {
		return null;
	}

	$map = $db->query("SELECT id, name FROM game_map WHERE id = ?", [$id])->fetch();

	/// Looked up every time rather than trusted: a map deleted in another tab
	/// would otherwise stay loaded for the rest of the session, with every page
	/// naming it and every later join coming back empty.
	if (!$map) {
		hnsCloseGameMap();

		return null;
	}

	return $map;
}

/// Kept inside the scoped session, so logIn() replacing it and logOut() dropping
/// it both unload the map - a map never follows a change of account.
function hnsLoadGameMap($id) {
	$_SESSION[sessionScope()]['gameMapId'] = (int)$id;
}

function hnsCloseGameMap() {
	unset($_SESSION[sessionScope()]['gameMapId']);
}

/// '' when the name is usable. Collates the way idx_game_map_name does, so a
/// clash is a message rather than a 500 from the unique index.
function hnsGameMapNameError($db, $name, $exceptId = 0) {
	if ($name === '') {
		return t('gameMap.error.noName');
	}

	$clash = $db->query(
		"SELECT id FROM game_map WHERE name = ? COLLATE NOCASE AND id <> ?",
		[$name, $exceptId]
	)->fetch();

	return $clash ? t('gameMap.error.nameTaken') : '';
}
