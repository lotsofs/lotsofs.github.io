<?php

$routes = [];

include 'routes.php';
include 'swat4/routes.php';

$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// serveDirectFile($uri);
route($uri, $routes);

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

function route($uri, $routes) {
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
	require "routes/error.php";
}