<?php

require_once __MODULES__ . '/hideandseek/ajaxGuard.php';
require_once __MODULES__ . '/hideandseek/gameMap.php';
require_once __MODULES__ . '/hideandseek/geo.php';

requireHnsAccountJson($db, t('ajax.notLoggedIn'));

$loaded = hnsLoadedGameMap($db);
$geo = $loaded && $loaded['gemeente_code'] !== null ? hnsGeoDb() : null;

/// The array is spliced in as text, so this cannot go through json_encode.
echo '{"status":"ok","buurten":' . ($geo ? hnsGeoBuurtenJson($geo, $loaded['gemeente_code']) : '[]') . '}';
