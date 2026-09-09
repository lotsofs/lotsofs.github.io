<?php

const LOGIN_ATTEMPT_WINDOW = 900;
const LOGIN_ATTEMPT_LIMIT = 5;

function loginClientIp() {
	return $_SERVER['REMOTE_ADDR'] ?? '';
}

function loginIsBlocked($db, $ip) {
	$since = time() - LOGIN_ATTEMPT_WINDOW;
	$count = $db->query("SELECT COUNT(*) c FROM login_attempt WHERE ip = ? AND attempted_at > ?", [$ip, $since])->fetch()['c'];

	return (int)$count >= LOGIN_ATTEMPT_LIMIT;
}

function recordLoginFailure($db, $ip) {
	$db->query("INSERT INTO login_attempt (ip, attempted_at) VALUES (?, ?)", [$ip, time()]);
	$db->query("DELETE FROM login_attempt WHERE attempted_at <= ?", [time() - LOGIN_ATTEMPT_WINDOW]);
}

// checked first, so a normal login takes no write lock at all
function clearLoginFailures($db, $ip) {
	if (!$db->query("SELECT 1 FROM login_attempt WHERE ip = ? LIMIT 1", [$ip])->fetch()) {
		return;
	}

	$db->query("DELETE FROM login_attempt WHERE ip = ?", [$ip]);
}
