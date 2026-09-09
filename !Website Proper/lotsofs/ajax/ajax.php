<?php

set_include_path($_SERVER['DOCUMENT_ROOT']);

require 'util.php';
require 'classes/Database.php';

$config = require('config.php');

header('Content-Type: application/json');

set_exception_handler(function ($e) {
	http_response_code(500);
	error_log((string)$e);
	echo json_encode(['error' => $e->getMessage()]);
});

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
	http_response_code(405);
	echo json_encode(['error' => 'Method not allowed']);
	exit;
}

$data = json_decode(file_get_contents("php://input"), true);

if (!is_array($data)) {
	http_response_code(400);
	echo json_encode(['error' => 'Expected a JSON array']);
	exit;
}
