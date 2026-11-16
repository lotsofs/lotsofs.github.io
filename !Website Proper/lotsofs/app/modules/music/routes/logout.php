<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && checkCsrf($_POST['csrf_token'] ?? null)) {
	forgetModulePreference('hue', 'music');
	forgetModulePreference('blind', 'music');
	forgetModulePreference('toast', 'music');
	forgetModulePreference('embeds', 'music');
	logOut();
}

header('Location: /music/songs', true, 302);
exit;
