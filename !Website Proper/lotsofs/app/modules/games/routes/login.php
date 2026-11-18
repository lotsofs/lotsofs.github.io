<?php

require_once __ROOT__ . '/session.php';
sessionScope('games');

loadStringCatalogue('games');

$pageTitle = t('login.title');

$db = require __MODULES__ . '/games/db.php';

require_once __MODULES__ . '/games/migrate.php';
runGamesMigrations($db);

require_once __MODULES__ . '/games/rateLimit.php';

if (currentAccountId()) {
	header('Location: /games', true, 302);
	exit;
}

$globalData['accountName'] = '';
$globalData['formError'] = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$accountName = trim($_POST['account_name'] ?? '');
	$password = $_POST['password'] ?? '';

	$globalData['accountName'] = $accountName;

	$ip = gamesLoginClientIp();

	if (!checkCsrf($_POST['csrf_token'] ?? null)) {
		$globalData['formError'] = t('login.error.expired');
	}
	else if (gamesLoginIsBlocked($db, $ip)) {
		$globalData['formError'] = t('login.error.tooMany');
	}
	else {
		$account = $db->query("SELECT id, account_name, password_hash, lang FROM account WHERE account_name = ?", [$accountName])->fetch();

		if ($account && password_verify($password, $account['password_hash'])) {
			gamesClearLoginFailures($db, $ip);
			logIn($account['id'], $account['account_name']);

			if (in_array($account['lang'], moduleLocales('games'), true)) {
				rememberLocale('games', $account['lang']);
			}

			header('Location: /games', true, 302);
			exit;
		}

		gamesRecordLoginFailure($db, $ip);
		$globalData['formError'] = t('login.error.rejected');
	}
}

require __MODULES__ . '/games/views/login.view.php';
