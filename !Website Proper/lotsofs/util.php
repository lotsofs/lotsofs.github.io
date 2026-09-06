<?php

define('__ROOT__', $_SERVER['DOCUMENT_ROOT']);
define('__MODULES__', __ROOT__ . '/modules');
define('__MAIN__', __MODULES__ . '/main');
define('__MAIN_URL__', '/modules/main');

$globalData = [];

$config = require('config.php');

function stringCatalogue($module = null) {
	static $catalogues = [];
	static $active = null;

	if ($module !== null) {
		$active = $module;
		if (!isset($catalogues[$module])) {
			$catalogues[$module] = require __MODULES__ . '/' . $module . '/lang/en.php';
		}
	}

	return $active === null ? [] : $catalogues[$active];
}

function t($key, $params = []) {
	$text = stringCatalogue()[$key] ?? $key;
	foreach ($params as $name => $value) {
		$text = str_replace('{' . $name . '}', $value, $text);
	}
	return $text;
}

function dd($value) {
	echo "<pre>";
	var_dump($value);
	error_log($value);
	echo "</pre>";

	die();
}

function urlIs($value) {
	return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === $value;
}

function urlStartsWith($value) {
	return str_starts_with($_SERVER['REQUEST_URI'], $value);
}

function getLastUrlPart() {
	$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	$parts = explode('/', trim($path, '/'));
	return end($parts);
}