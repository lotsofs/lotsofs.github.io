<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

stringCatalogue("music");

$pageTitle = t("page.login.title");

$db = require __MODULES__ . '/music/db.php';

require_once __MODULES__ . '/music/migrate.php';
runMusicMigrations($db);

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

	if (!checkCsrf($_POST['csrf_token'] ?? null)) {
		$globalData['formError'] = t('login.error.expired');
	}
	else {
		$account = $db->query("SELECT id, account_name, password_hash FROM account WHERE account_name = ?", [$accountName])->fetch();

		// one message for both failures, so the form can't be used to find out who has an account
		if ($account && password_verify($password, $account['password_hash'])) {
			logIn($account['id'], $account['account_name']);
			header('Location: /music/songs', true, 302);
			exit;
		}

		$globalData['formError'] = t('login.error.rejected');
	}
}

require __MODULES__ . "/music/views/login.view.php";
