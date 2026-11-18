<?php

require_once __ROOT__ . '/session.php';
sessionScope('mtasa');

loadStringCatalogue('mtasa');

$db = require __MODULES__ . '/mtasa/db.php';

require_once __MODULES__ . '/mtasa/migrate.php';
runMtasaMigrations($db);

require_once __MODULES__ . '/mtasa/auth.php';
require_once __MODULES__ . '/mtasa/maps.php';
require_once __MODULES__ . '/mtasa/comments.php';
require_once __MODULES__ . '/mtasa/preview.php';
require_once __MODULES__ . '/mtasa/compare.php';
requireMtasaAccount($db, '/mtasa');

$id = ctype_digit($_GET['id'] ?? '') ? (int)$_GET['id'] : 0;
$map = $id > 0 ? mtasaMap($db, $id) : null;
if ($map === null) {
	abort(404, t('map.notFound'));
	exit;
}

$pageTitle = htmlspecialchars(mtasaMapName($map));

$globalData['isAdmin'] = mtasaIsAdmin($db);
$globalData['accountId'] = (int)currentAccountId();
$globalData['map'] = $map;
$globalData['duplicates'] = mtasaDuplicates($db);
$globalData['threads'] = mtasaThreads($db, $id);
$geometry = $db->query("SELECT spawnpoints, checkpoints FROM map_geometry WHERE map_id = ?", [$id])->fetch() ?: null;
/// points.csv has a row for every folder there is: a map without one (the
/// analysis still names a Folder for it) has nothing on GitHub to link or zip.
$globalData['hasFolder'] = $geometry !== null;
$globalData['previewData'] = mtasaPreviewData($geometry);
$globalData['preview'] = mtasaPreviewSvg($globalData['previewData'], $id);
$globalData['map']['route_length_2d'] = mtasaRouteLength2d($globalData['previewData'], (int)($map['checkpoints_used'] ?? 0));
/// Never stored: js/analysis.js works it out on Increase detail.
$globalData['map']['zones_on_route'] = null;

$original = $globalData['duplicates']['root'][$id];
$globalData['group'] = [];
if (isset($globalData['duplicates']['copies'][$original])) {
	$members = array_merge([$original], $globalData['duplicates']['copies'][$original]);
	$rows = $db->query(
		"SELECT m.id, m.custom_name, m.original_name, m.duplicate_of, a.title, a.duplicate_of_closest, a.duplicate_similarity, a.duplicate_confidence, a.duplicate_differences
		FROM map m LEFT JOIN map_analysis a ON a.map_id = m.id
		WHERE m.id IN (" . implode(', ', $members) . ") ORDER BY m.id"
	)->fetchAll();
	/// A copy with a confirmed mark gets its differences from the map marked
	/// worked out here: once the owner has said what is real, the script's
	/// guess is ignored on its row, its differences included (the owner's wish).
	$compared = [];
	foreach ($rows as &$row) {
		$mark = $row['duplicate_of'] === null ? null : (int)$row['duplicate_of'];
		$row['own_differences'] = null;
		if ($mark === null || $mark === (int)$row['id']) {
			continue;
		}
		$compared[$row['id']] ??= mtasaCompareRow($db, (int)$row['id']);
		$compared[$mark] ??= mtasaCompareRow($db, $mark);
		$row['own_differences'] = $compared[$row['id']] !== null && $compared[$mark] !== null
			? mtasaCompareMaps($compared[$row['id']], $compared[$mark])
			: ['summary' => t('compare.unknown'), 'rows' => [], 'unknown' => true];
	}
	unset($row);
	$globalData['group'] = $rows;
}

$neighbour = fn($sql) => ($row = $db->query($sql, [$id])->fetch()) ? (int)$row['id'] : null;
$globalData['previous'] = $neighbour("SELECT id FROM map WHERE id < ? ORDER BY id DESC LIMIT 1");
$globalData['next'] = $neighbour("SELECT id FROM map WHERE id > ? ORDER BY id LIMIT 1");

require __MODULES__ . '/mtasa/views/map.view.php';
