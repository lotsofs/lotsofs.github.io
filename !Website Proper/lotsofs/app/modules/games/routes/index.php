<?php

require_once __ROOT__ . '/session.php';
sessionScope('games');
requireLogin('/games/login');

loadStringCatalogue('games');

$pageTitle = t('games.title');

$db = require __MODULES__ . '/games/db.php';

require_once __MODULES__ . '/games/migrate.php';
runGamesMigrations($db);

require_once __MODULES__ . '/games/auth.php';
requireGamesAccount($db);

$globalData['isAdmin'] = gamesIsAdmin($db);

require __MODULES__ . '/games/views/index.view.php';
