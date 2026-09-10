<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	logOut();
}

header('Location: /music/songs', true, 302);
exit;
