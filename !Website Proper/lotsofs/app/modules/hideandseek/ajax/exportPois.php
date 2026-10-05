<?php

require_once __MODULES__ . '/hideandseek/ajaxGuard.php';
require_once __MODULES__ . '/hideandseek/poiImport.php';

requireHnsAccountJson($db, t('ajax.notLoggedIn'));

$mapId = ajaxInt($data['mapId'] ?? null);
$map = $mapId !== 0 ? $db->query("SELECT id, name FROM game_map WHERE id = ?", [$mapId])->fetch() : false;

if (!$map) {
	http_response_code(400);
	echo json_encode(['error' => t('poiImport.error.mapGone')]);
	exit;
}

echo json_encode(['status' => 'ok', 'export' => hnsExportPois($db, $map)]);
