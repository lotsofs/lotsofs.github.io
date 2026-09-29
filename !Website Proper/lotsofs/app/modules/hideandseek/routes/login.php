<?php

require_once __ROOT__ . '/session.php';
sessionScope('hideandseek');

loadStringCatalogue('hideandseek');

$pageTitle = t('login.title');

$db = require __MODULES__ . '/hideandseek/db.php';

require_once __MODULES__ . '/hideandseek/migrate.php';
runHideAndSeekMigrations($db);

require_once __MODULES__ . '/hideandseek/rateLimit.php';

if (currentAccountId()) {
	header('Location: /hideandseek', true, 302);
	exit;
}

$globalData['accountName'] = '';
$globalData['formError'] = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$accountName = trim($_POST['account_name'] ?? '');
	$password = $_POST['password'] ?? '';

	$globalData['accountName'] = $accountName;

	$ip = hnsLoginClientIp();

	if (!checkCsrf($_POST['csrf_token'] ?? null)) {
		$globalData['formError'] = t('login.error.expired');
	}
	else if (hnsLoginIsBlocked($db, $ip)) {
		$globalData['formError'] = t('login.error.tooMany');
	}
	else {
		$account = $db->query("SELECT id, account_name, password_hash, lang FROM account WHERE account_name = ?", [$accountName])->fetch();

		if ($account && password_verify($password, $account['password_hash'])) {
			hnsClearLoginFailures($db, $ip);
			logIn($account['id'], $account['account_name']);

			if (in_array($account['lang'], moduleLocales('hideandseek'), true)) {
				rememberLocale('hideandseek', $account['lang']);
			}

			header('Location: /hideandseek', true, 302);
			exit;
		}

		hnsRecordLoginFailure($db, $ip);
		$globalData['formError'] = t('login.error.rejected');
	}
}

require __MODULES__ . '/hideandseek/views/login.view.php';
