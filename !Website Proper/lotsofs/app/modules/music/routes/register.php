<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

stringCatalogue("music");

$pageTitle = t("register.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/inviteCode.php';

if (currentAccountId()) {
	header('Location: /music/songs', true, 302);
	exit;
}

$accountCount = (int)$db->query("SELECT COUNT(*) c FROM account")->fetch()['c'];
$globalData['inviteRequired'] = $accountCount > 0;
$globalData['accountName'] = '';
$globalData['inviteCode'] = '';
$globalData['formError'] = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$accountName = trim($_POST['account_name'] ?? '');
	$password = $_POST['password'] ?? '';
	$passwordConfirm = $_POST['password_confirm'] ?? '';
	$inviteCode = normalizeInviteCode($_POST['invite_code'] ?? '');

	$globalData['accountName'] = $accountName;
	$globalData['inviteCode'] = $inviteCode === '' ? '' : formatInviteCode($inviteCode);

	$invite = null;

	if (!checkCsrf($_POST['csrf_token'] ?? null)) {
		$globalData['formError'] = t('register.error.expired');
	}
	else if ($accountName === '' || $password === '') {
		$globalData['formError'] = t('register.error.missing');
	}
	else if (strlen($password) < 8) {
		$globalData['formError'] = t('register.error.passwordShort');
	}
	else if ($password !== $passwordConfirm) {
		$globalData['formError'] = t('register.error.passwordMismatch');
	}
	else if ($db->query("SELECT id FROM account WHERE account_name = ?", [$accountName])->fetch()) {
		$globalData['formError'] = t('register.error.nameTaken');
	}
	else if ($globalData['inviteRequired']) {
		$invite = $db->query("SELECT id FROM invite WHERE code = ? AND used_at IS NULL AND revoked_at IS NULL", [$inviteCode])->fetch();
		if (!$invite) {
			$globalData['formError'] = t('register.error.badInvite');
		}
	}

	if ($globalData['formError'] === null) {
		$db->pdo->beginTransaction();
		try {
			$db->query(
				"INSERT INTO account (account_name, password_hash, is_admin, lang) VALUES (?, ?, ?, ?)",
				[$accountName, password_hash($password, PASSWORD_DEFAULT), $accountCount === 0 ? 1 : 0, activeLocale()]
			);
			$accountId = $db->pdo->lastInsertId();

			if ($invite) {
				$spent = $db->query(
					"UPDATE invite SET used_at = ?, used_by_account_id = ? WHERE id = ? AND used_at IS NULL AND revoked_at IS NULL",
					[date('c'), $accountId, $invite['id']]
				);
				if ($spent->rowCount() !== 1) {
					throw new RuntimeException('invite was already used');
				}
			}

			$db->pdo->commit();
		}
		catch (Throwable $e) {
			$db->pdo->rollBack();
			$globalData['formError'] = t('register.error.badInvite');
		}

		if ($globalData['formError'] === null) {
			logIn($accountId, $accountName);
			header('Location: /music/songs', true, 302);
			exit;
		}
	}
}

require __MODULES__ . "/music/views/register.view.php";
