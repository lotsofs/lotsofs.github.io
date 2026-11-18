<?php

require_once __ROOT__ . '/session.php';
sessionScope('mtasa');

loadStringCatalogue('mtasa');

$pageTitle = t('maps.title');

$db = require __MODULES__ . '/mtasa/db.php';

require_once __MODULES__ . '/mtasa/migrate.php';
runMtasaMigrations($db);

require_once __MODULES__ . '/mtasa/auth.php';
require_once __MODULES__ . '/mtasa/maps.php';
requireMtasaAccount($db, '/mtasa');

$globalData['isAdmin'] = mtasaIsAdmin($db);
$globalData['mapCount'] = (int)$db->query("SELECT COUNT(*) n FROM map")->fetch()['n'];

/// What the import just did, carried in the address by its redirect.
$globalData['imported'] = isset($_GET['imported'], $_GET['analysed'], $_GET['drawn']) && ctype_digit($_GET['imported']) && ctype_digit($_GET['analysed']) && ctype_digit($_GET['drawn'])
	? ['maps' => (int)$_GET['imported'], 'analysed' => (int)$_GET['analysed'], 'drawn' => (int)$_GET['drawn']]
	: null;
$globalData['importError'] = $globalData['importError'] ?? null;
$globalData['reloaded'] = isset($_GET['reloaded'], $_GET['added'], $_GET['drawn']) && ctype_digit($_GET['reloaded']) && ctype_digit($_GET['added']) && ctype_digit($_GET['drawn'])
	? ['analysed' => (int)$_GET['reloaded'], 'added' => (int)$_GET['added'], 'drawn' => (int)$_GET['drawn']]
	: null;

$globalData['filters'] = mtasaFilters($db);
$globalData['chosen'] = mtasaReadFilters($globalData['filters'], $_GET);
$page = ctype_digit($_GET['page'] ?? '') ? (int)$_GET['page'] : 1;
/// The page size: any whole number up to the maximum, so an address can ask for one the pager does not offer.
$per = ctype_digit($_GET['per'] ?? '') && (int)$_GET['per'] >= 1 && (int)$_GET['per'] <= MTASA_PAGE_SIZE_MAX ? (int)$_GET['per'] : MTASA_PAGE_SIZE;
$globalData['list'] = mtasaListQuery(
	$db,
	$globalData['filters'],
	$globalData['chosen'],
	is_string($_GET['sort'] ?? null) ? $_GET['sort'] : 'id',
	is_string($_GET['dir'] ?? null) ? $_GET['dir'] : 'asc',
	$page,
	$per
);
$globalData['duplicates'] = mtasaDuplicates($db);

require __MODULES__ . '/mtasa/views/index.view.php';
