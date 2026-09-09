<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

stringCatalogue("music");

$pageTitle = t("page.invites.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
requireMusicAccount($db);
requireMusicAdmin($db, '/music/songs');

$globalData['isAdmin'] = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	$db->query(
		"INSERT INTO invite (code, created_at, created_by_account_id) VALUES (?, ?, ?)",
		[bin2hex(random_bytes(8)), date('c'), currentAccountId()]
	);

	header('Location: /music/invites', true, 302);
	exit;
}

$globalData['invites'] = $db->query("
	SELECT
		i.code,
		i.created_at,
		i.used_at,
		(SELECT account_name FROM account WHERE id = i.used_by_account_id) AS used_by
	FROM invite i
	ORDER BY i.id DESC
")->fetchAll();

require __MODULES__ . "/music/views/invites.view.php";
