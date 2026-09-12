<?php

define('__ROOT__', __DIR__);
define('__MODULES__', __ROOT__ . '/modules');
define('__MAIN__', __MODULES__ . '/main');
define('__DATA__', is_dir('/home/lotsofs') ? '/home/lotsofs/data' : dirname(__DIR__) . '/data');

$globalData = [];

$config = require __DIR__ . '/config.php';

const AVAILABLE_LOCALES = ['en', 'de', 'fy'];

function activeLocale() {
	static $locale = null;

	if ($locale !== null) {
		return $locale;
	}

	if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], AVAILABLE_LOCALES, true)) {
		return $locale = $_SESSION['lang'];
	}

	$default = getenv('LOTSOFS_DEFAULT_LOCALE');

	return $locale = in_array($default, AVAILABLE_LOCALES, true) ? $default : 'fy';
}

function stringCatalogue($module = null) {
	static $catalogues = [];
	static $active = null;

	if ($module !== null) {
		$active = $module;
		if (!isset($catalogues[$module])) {
			$base = require __MODULES__ . '/' . $module . '/lang/en.php';
			$locale = activeLocale();

			if ($locale === 'en') {
				$catalogues[$module] = $base;
			}
			else {
				$overlay = array_filter(
					require __MODULES__ . '/' . $module . '/lang/' . $locale . '.php',
					fn($value) => $value !== null && $value !== ''
				);
				$catalogues[$module] = array_merge($base, $overlay);
			}
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
	error_log(print_r($value, true));
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