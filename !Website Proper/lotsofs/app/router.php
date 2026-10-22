<?php


$routes = [];

// a module left off the host has no folder there, so it has no routes
foreach (glob(__MODULES__ . '/*/routes.php') as $moduleRoutes) {
	include $moduleRoutes;
}

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

route($canonicalUri, $routes, $globalData);

function route($uri, $routes, $globalData) {
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