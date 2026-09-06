<?php


$routes = [];

include __MODULES__ . '/main/routes.php';
include __MODULES__ . '/swat4/routes.php';
include __MODULES__ . '/ss2/routes.php';
include __MODULES__ . '/music/routes.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// redirect anything that isn't the lowercase, no trailing slash url
$canonicalUri = rtrim(strtolower($path), '/');
if ($canonicalUri === '') {
	$canonicalUri = '/';
}

if ($path !== $canonicalUri) {
	$query = parse_url($_SERVER['REQUEST_URI'], PHP_URL_QUERY);
	header('Location: ' . $canonicalUri . ($query ? '?' . $query : ''), true, 301);
	exit;
}

route($canonicalUri, $routes, $globalData, $config);

// function serveDirectFile($uri) {
// 	var_dump($uri);
// 	if (php_sapi_name() === 'cli-server') {
// 		$file = __DIR__ . $uri;
// 		if (is_file($file)) {
// 			dd($uri);
// 			return false;
// 		}
// 	}
// }

function route($uri, $routes, $globalData, $config) {
	if (array_key_exists($uri, $routes)) {
		require $routes[$uri];
	}
	else {
		abort(404, "Not Found");
	}
}

function abort($code = 404, $message = "") {
	http_response_code($code);
	$responseCode = $code;
	$errorMessage = $message;
	require __MAIN__ . "/routes/error.php";
}