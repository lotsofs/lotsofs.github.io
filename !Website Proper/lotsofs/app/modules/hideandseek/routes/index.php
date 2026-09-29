<?php

require_once __ROOT__ . '/session.php';
sessionScope('hideandseek');

loadStringCatalogue('hideandseek');

$pageTitle = t('hideandseek.title');

$db = require __MODULES__ . '/hideandseek/db.php';

require_once __MODULES__ . '/hideandseek/migrate.php';
runHideAndSeekMigrations($db);

require_once __MODULES__ . '/hideandseek/auth.php';
requireHnsAccount($db);

$globalData['isAdmin'] = hnsIsAdmin($db);

/// Where the map opens, until there is a game to centre it on. Read off the
/// container by map.js rather than hardcoded in the script.
$globalData['mapPage'] = true;
$globalData['mapCentre'] = ['lat' => 53.2012, 'lng' => 5.7999, 'zoom' => 12];

require __MODULES__ . '/hideandseek/views/index.view.php';
