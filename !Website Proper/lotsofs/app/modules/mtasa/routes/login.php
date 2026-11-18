<?php

require_once __ROOT__ . '/session.php';
sessionScope('mtasa');

loadStringCatalogue('mtasa');

$pageTitle = t('login.title');

$db = require __MODULES__ . '/mtasa/db.php';

require_once __MODULES__ . '/mtasa/migrate.php';
runMtasaMigrations($db);

require_once __MODULES__ . '/mtasa/rateLimit.php';

if (currentAccountId()) {
	header('Location: /mtasa', true, 302);
	exit;
}

$globalData['accountName'] = '';
$globalData['formError'] = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$accountName = trim($_POST['account_name'] ?? '');
	$password = $_POST['password'] ?? '';

	$globalData['accountName'] = $accountName;

	$ip = mtasaLoginClientIp();

	if (!checkCsrf($_POST['csrf_token'] ?? null)) {
		$globalData['formError'] = t('login.error.expired');
	}
	else if (mtasaLoginIsBlocked($db, $ip)) {
		$globalData['formError'] = t('login.error.tooMany');
	}
	else {
		$account = $db->query("SELECT id, account_name, password_hash, lang FROM account WHERE account_name = ?", [$accountName])->fetch();

		if ($account && password_verify($password, $account['password_hash'])) {
			mtasaClearLoginFailures($db, $ip);
			logIn($account['id'], $account['account_name']);

			if (in_array($account['lang'], moduleLocales('mtasa'), true)) {
				rememberLocale('mtasa', $account['lang']);
			}

			header('Location: /mtasa', true, 302);
			exit;
		}

		mtasaRecordLoginFailure($db, $ip);
		$globalData['formError'] = t('login.error.rejected');
	}
}

require __MODULES__ . '/mtasa/views/login.view.php';
