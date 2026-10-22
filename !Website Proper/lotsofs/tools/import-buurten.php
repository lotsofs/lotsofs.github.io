<?php

/// Builds hideandseek_geo.sqlite from CBS Wijken en Buurten, as served by PDOK.
///
///   php tools/import-buurten.php [--db=PATH] [--year=2026] [--page=200] [--file=PATH]
///
/// Without --file it pages through the whole country (about 14,000 buurten, 140MB
/// of geometry, one to three minutes). --file reads a local GeoJSON
/// FeatureCollection instead, for fixtures and offline runs. The file is built
/// next to its destination and moved into place at the end, so a failed run
/// leaves the old one alone. Run it at home and copy the result to the host.

if (php_sapi_name() !== 'cli') {
	exit;
}

ini_set('memory_limit', '1G');
set_time_limit(0);

$root = dirname(__DIR__);

require_once $root . '/app/classes/Database.php';
require_once $root . '/app/modules/hideandseek/geoImport.php';

$options = ['db' => $root . '/data/hideandseek_geo.sqlite', 'year' => '2026', 'page' => '200', 'file' => ''];

foreach (array_slice($argv, 1) as $argument) {
	if (!preg_match('/^--(db|year|page|file)=(.+)$/', $argument, $m)) {
		fwrite(STDERR, "unknown argument: {$argument}\n");
		exit(2);
	}

	$options[$m[1]] = $m[2];
}

$target = $options['db'];
$building = $target . '.building';

if (!is_dir(dirname($target))) {
	mkdir(dirname($target), 0700, true);
}

@unlink($building);

$geo = new Database($building);
$geo->execSQL('PRAGMA journal_mode = OFF');
$geo->execSQL('PRAGMA synchronous = OFF');
hnsGeoCreateSchema($geo);

$state = ['gemeente' => [], 'wijk' => []];
$stored = 0;
$skipped = 0;
$started = microtime(true);

function importFeatures($geo, &$state, $features, &$stored, &$skipped) {
	$geo->pdo->beginTransaction();

	foreach ($features as $feature) {
		if (hnsGeoStoreFeature($geo, $state, $feature)) {
			$stored++;
		}
		else {
			$skipped++;
		}
	}

	$geo->pdo->commit();
}

function fetchPage($url) {
	for ($attempt = 1; $attempt <= 4; $attempt++) {
		$curl = curl_init($url);
		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_ENCODING => '',
			/// This PHP ships no CA bundle, and the network here re-signs TLS with a
			/// root only the system store knows. Verification stays on.
			CURLOPT_SSL_OPTIONS => CURLSSLOPT_NATIVE_CA,
			CURLOPT_TIMEOUT => 300,
			CURLOPT_HTTPHEADER => ['Accept: application/geo+json, application/json'],
		]);
		$body = curl_exec($curl);
		$status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
		curl_close($curl);

		$data = $body === false || $status !== 200 ? null : json_decode($body, true);

		if (is_array($data) && is_array($data['features'] ?? null)) {
			return $data;
		}

		fwrite(STDERR, "page failed (HTTP {$status}), attempt {$attempt} of 4\n");
		sleep(2 * $attempt);
	}

	fwrite(STDERR, "giving up on {$url}\n");
	exit(1);
}

if ($options['file'] !== '') {
	$data = json_decode((string)file_get_contents($options['file']), true);

	if (!is_array($data) || !is_array($data['features'] ?? null)) {
		fwrite(STDERR, "{$options['file']} is not a GeoJSON FeatureCollection\n");
		exit(1);
	}

	importFeatures($geo, $state, $data['features'], $stored, $skipped);
}
else {
	$url = "https://api.pdok.nl/cbs/wijken-en-buurten-{$options['year']}/ogc/v1/collections/buurten/items?f=json&limit={$options['page']}";
	$pages = 0;

	while ($url !== null) {
		$data = fetchPage($url);
		importFeatures($geo, $state, $data['features'], $stored, $skipped);
		$pages++;

		echo "page {$pages}: {$stored} buurten stored, {$skipped} skipped, " . round(microtime(true) - $started) . "s\n";

		$url = null;

		foreach ($data['links'] ?? [] as $link) {
			if (($link['rel'] ?? '') === 'next' && is_string($link['href'] ?? null)) {
				$url = $link['href'];
			}
		}

		unset($data);
	}
}

if ($stored === 0) {
	$geo = null;
	@unlink($building);
	fwrite(STDERR, "nothing was stored, the existing file is left alone\n");
	exit(1);
}

echo "{$stored} buurten stored, {$skipped} skipped\n";
echo "working out which gemeenten touch\n";
hnsGeoComputeNeighbours($geo);

$geo->execSQL('DROP TABLE _vertex');
$geo->execSQL('VACUUM');

$counts = [];

foreach (['gemeente', 'wijk', 'buurt', 'gemeente_neighbour'] as $table) {
	$counts[] = $geo->query("SELECT COUNT(*) c FROM {$table}")->fetch()['c'] . ' ' . $table;
}

$geo = null;

/// Windows will not replace a file another process has open - typically the dev
/// server, which has read it. The finished build stays beside it rather than
/// being thrown away.
if ((file_exists($target) && !@unlink($target)) || !rename($building, $target)) {
	fwrite(STDERR, "could not replace {$target}: something has it open (a dev server?).
");
	fwrite(STDERR, "the finished build is {$building}; close whatever has the old file open, then rename it over.
");
	exit(1);
}

echo implode(', ', $counts) . " in " . round(microtime(true) - $started) . "s\n";
echo "wrote {$target} (" . round(filesize($target) / 1048576, 1) . " MB)\n";
