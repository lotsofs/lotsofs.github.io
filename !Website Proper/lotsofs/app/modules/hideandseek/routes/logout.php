<?php

require_once __ROOT__ . '/session.php';
sessionScope('hideandseek');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	logOut();
}

header('Location: /hideandseek', true, 302);
exit;
