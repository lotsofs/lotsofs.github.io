<?php

require_once __ROOT__ . '/session.php';
sessionScope('mtasa');

loadStringCatalogue('mtasa');

$pageTitle = t('comments.title');

$db = require __MODULES__ . '/mtasa/db.php';

require_once __MODULES__ . '/mtasa/migrate.php';
runMtasaMigrations($db);

require_once __MODULES__ . '/mtasa/auth.php';
require_once __MODULES__ . '/mtasa/comments.php';
requireMtasaAccount($db, '/mtasa');

$globalData['isAdmin'] = mtasaIsAdmin($db);
$globalData['includeResolved'] = ($_GET['resolved'] ?? '') === '1';
$globalData['recent'] = mtasaRecentThreads($db, $globalData['includeResolved']);

require __MODULES__ . '/mtasa/views/comments.view.php';
