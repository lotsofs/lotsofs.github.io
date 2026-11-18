<?php

require_once __ROOT__ . '/session.php';
sessionScope('mtasa');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	logOut();
}

/// Back to the list, which anyone may read.
header('Location: /mtasa', true, 302);
exit;
