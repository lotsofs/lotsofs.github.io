<?php

require_once __ROOT__ . '/session.php';
sessionScope('games');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	logOut();
}

/// Straight to the login page: the landing page would only send it there.
header('Location: /games/login', true, 302);
exit;
