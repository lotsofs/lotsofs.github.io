<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

stringCatalogue("music");

$pageTitle = t("accounts.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);
requireMusicAdmin($db, '/music/songs');

$globalData['isAdmin'] = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	$targetId = ctype_digit($_POST['account_id'] ?? '') ? (int)$_POST['account_id'] : 0;
	$makeAdmin = ($_POST['action'] ?? '') === 'promote' ? 1 : 0;

	if ($targetId !== 0 && $targetId !== (int)currentAccountId()) {
		$db->query("UPDATE account SET is_admin = ? WHERE id = ?", [$makeAdmin, $targetId]);
	}

	header('Location: /music/accounts', true, 302);
	exit;
}

$globalData['accountId'] = (int)currentAccountId();
$globalData['accounts'] = $db->query("SELECT id, account_name, is_admin FROM account ORDER BY id")->fetchAll();

require __MODULES__ . "/music/views/accounts.view.php";
