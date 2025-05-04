<?php

$uri = parse_url($_SERVER['REQUEST_URI'])['path'];

$routes = [
	"/" => "routes/index.php",
	"/exchangeRates" => "routes/exchangeRates.php",
	"/contact" => "routes/contact.php",
];

// route();
if (array_key_exists($uri, $routes)) {
	require $routes[$uri];
}
else {
	// abort(404);
	http_response_code(404);
	$pageTitle = "404";
	require "views/404.view.php";
}
