<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

stringCatalogue("music");

$pageTitle = t("login.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

require_once __MODULES__ . '/music/rateLimit.php';

if (currentAccountId()) {
	header('Location: /music/songs', true, 302);
	exit;
}

$globalData['accountName'] = '';
$globalData['formError'] = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$accountName = trim($_POST['account_name'] ?? '');
	$password = $_POST['password'] ?? '';

	$globalData['accountName'] = $accountName;

	$ip = loginClientIp();

	if (!checkCsrf($_POST['csrf_token'] ?? null)) {
		$globalData['formError'] = t('login.error.expired');
	}
	else if (loginIsBlocked($db, $ip)) {
		$globalData['formError'] = t('login.error.tooMany');
	}
	else {
		$account = $db->query("SELECT id, account_name, password_hash, lang FROM account WHERE account_name = ?", [$accountName])->fetch();

		if ($account && password_verify($password, $account['password_hash'])) {
			clearLoginFailures($db, $ip);
			logIn($account['id'], $account['account_name']);
			if (in_array($account['lang'], AVAILABLE_LOCALES, true)) {
				$_SESSION['lang'] = $account['lang'];
			}
			header('Location: /music/songs', true, 302);
			exit;
		}

		recordLoginFailure($db, $ip);
		$globalData['formError'] = t('login.error.rejected');
	}
}

require __MODULES__ . "/music/views/login.view.php";
