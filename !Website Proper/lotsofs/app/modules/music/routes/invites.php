<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');
requireLogin();

stringCatalogue("music");

$pageTitle = t("invites.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/auth.php';
require_once __MODULES__ . '/music/inviteCode.php';
requireMusicAccount($db);
requireMusicAdmin($db, '/music/songs');

$globalData['isAdmin'] = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	if (($_POST['action'] ?? '') === 'revoke') {
		$targetId = ctype_digit($_POST['invite_id'] ?? '') ? (int)$_POST['invite_id'] : 0;

		if ($targetId !== 0) {
			$db->query(
				"UPDATE invite SET revoked_at = ? WHERE id = ? AND used_at IS NULL AND revoked_at IS NULL",
				[date('c'), $targetId]
			);
		}
	}
	else {
		$db->query(
			"INSERT INTO invite (code, created_at, created_by_account_id) VALUES (?, ?, ?)",
			[generateInviteCode(), date('c'), currentAccountId()]
		);
	}

	header('Location: /music/invites', true, 302);
	exit;
}

$globalData['invites'] = $db->query("
	SELECT
		i.id,
		i.code,
		i.created_at,
		i.used_at,
		i.revoked_at,
		(SELECT account_name FROM account WHERE id = i.used_by_account_id) AS used_by
	FROM invite i
	ORDER BY i.id DESC
")->fetchAll();

require __MODULES__ . "/music/views/invites.view.php";
