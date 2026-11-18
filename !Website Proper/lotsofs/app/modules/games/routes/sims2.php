<?php

require_once __ROOT__ . '/session.php';
sessionScope('games');
requireLogin('/games/login');

loadStringCatalogue('games');

$pageTitle = t('sims2.title');

$db = require __MODULES__ . '/games/db.php';

require_once __MODULES__ . '/games/migrate.php';
runGamesMigrations($db);

require_once __MODULES__ . '/games/auth.php';
requireGamesAccount($db);

$globalData['isAdmin'] = gamesIsAdmin($db);

require_once __MODULES__ . '/games/sims2.php';

$keys = $db->query("SELECT item_key FROM sims2_check WHERE account_id = ?", [(int)currentAccountId()])->fetchAll();
$globalData['checked'] = array_fill_keys(array_column($keys, 'item_key'), true);
$globalData['lists'] = gamesSims2WithVehicles(gamesSims2Lists(), gamesSims2Vehicles($db));

require __MODULES__ . '/games/views/sims2.view.php';
