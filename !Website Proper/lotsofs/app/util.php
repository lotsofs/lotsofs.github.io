<?php

define('__ROOT__', __DIR__);
define('__MODULES__', __ROOT__ . '/modules');
define('__MAIN__', __MODULES__ . '/main');
define('__DATA__', is_dir('/home/lotsofs') ? '/home/lotsofs/data' : dirname(__DIR__) . '/data');

$globalData = [];

$activeStrings = [];
$activeLocaleCode = null;

/// A module's settings from app/modules/<module>/config.php. Modules without
/// one get an empty array.
function moduleConfig($module) {
	static $configs = [];

	if (!isset($configs[$module])) {
		$path = __MODULES__ . '/' . $module . '/config.php';
		$configs[$module] = is_file($path) ? require $path : [];
	}

	return $configs[$module];
}

/// The locale codes a module ships catalogues for, from its config. Empty for
/// a module with no translations.
function moduleLocales($module) {
	return moduleConfig($module)['locales'] ?? [];
}

/// The locale a module renders in: $_SESSION['lang'] where the module offers
/// it, else the LOTSOFS_LOCALE env var where one is set and offered,
/// else the module's own defaultLocale.
function resolveLocale($module) {
	$locales = moduleLocales($module);

	if (isset($_SESSION['lang']) && in_array($_SESSION['lang'], $locales, true)) {
		return $_SESSION['lang'];
	}

	$override = getenv('LOTSOFS_LOCALE');
	if (in_array($override, $locales, true)) {
		return $override;
	}

	return moduleConfig($module)['defaultLocale'] ?? 'en';
}

/// Loads a module's strings and makes them the ones stringCatalogue(), t() and
/// activeLocale() answer with: lang/<locale>.php merged over lang/en.php, with
/// blank entries falling back to English. Called once per request, after the
/// session starts.
function loadStringCatalogue($module) {
	global $activeStrings, $activeLocaleCode;

	$activeLocaleCode = resolveLocale($module);
	$base = require __MODULES__ . '/' . $module . '/lang/en.php';

	if ($activeLocaleCode === 'en') {
		$activeStrings = $base;
		return;
	}

	$overlay = array_filter(
		require __MODULES__ . '/' . $module . '/lang/' . $activeLocaleCode . '.php',
		fn($value) => $value !== null && $value !== ''
	);

	$activeStrings = array_merge($base, $overlay);
}

/// The loaded catalogue as an array, empty before loadStringCatalogue() runs.
/// head.php ships it to the browser for the JS-side t().
function stringCatalogue() {
	global $activeStrings;

	return $activeStrings;
}

/// The locale the loaded catalogue resolved to, for <html lang> and for
/// recording a new account's language.
function activeLocale() {
	global $activeLocaleCode;

	return $activeLocaleCode ?? 'en';
}

/// Translates a key, substituting {placeholder} tokens:
/// t('some.key', ['name' => $name]). An unknown key returns itself. Omitting
/// $params returns the raw template.
function t($key, $params = []) {
	$text = stringCatalogue()[$key] ?? $key;
	foreach ($params as $name => $value) {
		$text = str_replace('{' . $name . '}', $value, $text);
	}
	return $text;
}

/// Dumps $value to the page and the error log, then halts the request. Debug
/// scaffolding, not for committed code.
function dd($value) {
	echo "<pre>";
	var_dump($value);
	error_log(print_r($value, true));
	echo "</pre>";

	die();
}

/// Cache-busting URL for a public asset. Belongs on every <link> and <script>:
/// asset('/css/app.css') -> '/css/app.css?v=1789900700'
///
/// The stamp is the file's mtime. A file that cannot be found returns the bare
/// path.
function asset($path) {
	$root = $_SERVER['DOCUMENT_ROOT'] ?? '';
	if ($root === '' && isset($_SERVER['SCRIPT_FILENAME'])) {
		$root = dirname($_SERVER['SCRIPT_FILENAME']);
	}

	$stamp = @filemtime($root . $path);

	return $stamp ? $path . '?v=' . $stamp : $path;
}

/// True when the request path is exactly $value. The query string is stripped
/// first: '/section/page?x=1' matches '/section/page'.
function urlIs($value) {
	return parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === $value;
}

/// True when the request starts with $value, matching a whole section rather
/// than one page. Tests the raw REQUEST_URI, query string included, unlike
/// urlIs().
function urlStartsWith($value) {
	return str_starts_with($_SERVER['REQUEST_URI'], $value);
}

/// Last path segment of the request: '/section/14' gives '14'. Trailing
/// slashes and the query string are ignored.
function getLastUrlPart() {
	$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
	$parts = explode('/', trim($path, '/'));
	return end($parts);
}
