<?php

const HNS_LOGIN_ATTEMPT_WINDOW = 900;
const HNS_LOGIN_ATTEMPT_LIMIT = 5;

function hnsLoginClientIp() {
	return $_SERVER['REMOTE_ADDR'] ?? '';
}

function hnsLoginIsBlocked($db, $ip) {
	$since = time() - HNS_LOGIN_ATTEMPT_WINDOW;
	$count = $db->query("SELECT COUNT(*) c FROM login_attempt WHERE ip = ? AND attempted_at > ?", [$ip, $since])->fetch()['c'];

	return (int)$count >= HNS_LOGIN_ATTEMPT_LIMIT;
}

function hnsRecordLoginFailure($db, $ip) {
	$db->query("INSERT INTO login_attempt (ip, attempted_at) VALUES (?, ?)", [$ip, time()]);
	$db->query("DELETE FROM login_attempt WHERE attempted_at <= ?", [time() - HNS_LOGIN_ATTEMPT_WINDOW]);
}

function hnsClearLoginFailures($db, $ip) {
	if (!$db->query("SELECT 1 FROM login_attempt WHERE ip = ? LIMIT 1", [$ip])->fetch()) {
		return;
	}

	$db->query("DELETE FROM login_attempt WHERE ip = ?", [$ip]);
}
