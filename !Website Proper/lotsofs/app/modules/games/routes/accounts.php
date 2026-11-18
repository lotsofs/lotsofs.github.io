<?php

require_once __ROOT__ . '/session.php';
sessionScope('games');
requireLogin('/games/login');

loadStringCatalogue('games');

$pageTitle = t('accounts.title');

$db = require __MODULES__ . '/games/db.php';

require_once __MODULES__ . '/games/migrate.php';
runGamesMigrations($db);

require_once __MODULES__ . '/games/auth.php';
require_once __MODULES__ . '/games/inviteCode.php';
requireGamesAccount($db);
requireGamesAdmin($db, '/games');

$globalData['isAdmin'] = true;

/// One admin page, so one handler: who can get in, and who is already in.
/// Dispatched on the action rather than on which field turned up, so a post
/// this doesn't recognise does nothing at all.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	$action = $_POST['action'] ?? '';
	$accountId = ctype_digit($_POST['account_id'] ?? '') ? (int)$_POST['account_id'] : 0;
	$inviteId = ctype_digit($_POST['invite_id'] ?? '') ? (int)$_POST['invite_id'] : 0;

	if ($action === 'promote' || $action === 'demote') {
		/// An admin cannot demote themselves, so the last one cannot lock
		/// everybody out of this page.
		if ($accountId !== 0 && $accountId !== (int)currentAccountId()) {
			$db->query("UPDATE account SET is_admin = ? WHERE id = ?", [$action === 'promote' ? 1 : 0, $accountId]);
		}
	}
	else if ($action === 'invite') {
		$db->query(
			"INSERT INTO invite (code, created_at, created_by_account_id) VALUES (?, ?, ?)",
			[gamesGenerateInviteCode(), date('c'), currentAccountId()]
		);
	}
	else if ($action === 'revoke' && $inviteId !== 0) {
		$db->query(
			"UPDATE invite SET revoked_at = ? WHERE id = ? AND used_at IS NULL AND revoked_at IS NULL",
			[date('c'), $inviteId]
		);
	}

	header('Location: /games/accounts', true, 302);
	exit;
}

$globalData['accountId'] = (int)currentAccountId();
$globalData['accounts'] = $db->query("SELECT id, account_name, is_admin FROM account ORDER BY id")->fetchAll();

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

require __MODULES__ . '/games/views/accounts.view.php';
