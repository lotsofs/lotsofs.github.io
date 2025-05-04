<?php

$routes = [
	"/" => "routes/home.php",
	"/exchangeRates" => "routes/exchangeRates.php",
	"/contact" => "routes/contact.php",
	"/ktane" => "routes/ktane.php",
];

$uri = parse_url($_SERVER['REQUEST_URI'])['path'];

// route();
if (array_key_exists($uri, $routes)) {
	require $routes[$uri];
}
else {
	abort(404, "Not Found");
}

function abort($code = 404, $message = "") {
	http_response_code($code);
	$responseCode = $code;
	$errorMessage = $message;
	require "routes/error.php";
}