<?php

// usage: php tests/run.php
// copies the project to a temp dir, serves it, runs tests/cases/*.php against it

if (php_sapi_name() !== 'cli') {
	exit;
}

const SKIP_DIRS = ['modules/ss2/img', 'modules/ss2/fonts', 'raw', 'tests'];

$projectRoot = realpath(__DIR__ . '/..');
$tempRoot = sys_get_temp_dir() . '/lotsofs_tests_' . getmypid();
$serverLog = $tempRoot . '.log';
$port = findFreePort();
$server = null;

register_shutdown_function(function () use (&$server, $tempRoot, $serverLog) {
	if (is_resource($server)) {
		proc_terminate($server);
		proc_close($server);
	}
	removeDir($tempRoot);

	// the log stays locked for a moment after the server goes away
	for ($i = 0; $i < 20 && file_exists($serverLog); $i++) {
		@unlink($serverLog);
		usleep(50000);
	}
});

echo "copying project to a scratch copy\n";
copyProject($projectRoot, $tempRoot);

echo "serving scratch copy on port {$port}\n";
// bypass_shell so the handle is the server itself and not a cmd.exe wrapper
$server = proc_open(
	escapeshellarg(PHP_BINARY) . " -S localhost:{$port} -t " . escapeshellarg($tempRoot),
	[1 => ['file', $serverLog, 'a'], 2 => ['file', $serverLog, 'a']],
	$pipes,
	null,
	null,
	['bypass_shell' => true]
);

if (!waitForServer($port)) {
	exit("server did not start, see {$serverLog}\n");
}

$ctx = new TestContext($port, $tempRoot);

// the app applies migrations when this page is served
$ctx->get('/music/add-songs');

$passed = 0;
$failed = 0;

foreach (glob(__DIR__ . '/cases/*.php') as $caseFile) {
	echo "\n" . basename($caseFile, '.php') . "\n";

	foreach (require $caseFile as $name => $test) {
		try {
			$test($ctx);
			echo "  PASS  {$name}\n";
			$passed++;
		}
		catch (Throwable $e) {
			echo "  FAIL  {$name}\n";
			echo "          " . $e->getMessage() . "\n";
			$failed++;
		}
	}
}

echo "\n{$passed} passed, {$failed} failed\n";
exit($failed === 0 ? 0 : 1);


class TestContext {
	private $port;
	private $tempRoot;

	public function __construct($port, $tempRoot) {
		$this->port = $port;
		$this->tempRoot = $tempRoot;
	}

	public function get($path, $followRedirects = false) {
		return $this->request('GET', $path, null, $followRedirects);
	}

	public function post($path, $payload) {
		return $this->request('POST', $path, is_string($payload) ? $payload : json_encode($payload), false);
	}

	private function request($method, $path, $body, $followRedirects) {
		$ch = curl_init("http://localhost:{$this->port}{$path}");
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER => true,
			CURLOPT_FOLLOWLOCATION => $followRedirects,
			CURLOPT_CUSTOMREQUEST => $method,
		]);
		if ($body !== null) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
			curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		}
		$raw = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
		curl_close($ch);

		$headers = substr($raw, 0, $headerSize);
		$content = substr($raw, $headerSize);
		$location = null;
		if (preg_match('/^location:\s*(.+)$/mi', $headers, $m)) {
			$location = trim($m[1]);
		}

		return [
			'status' => $status,
			'body' => $content,
			'location' => $location,
			'json' => json_decode($content, true),
		];
	}

	public function db() {
		$pdo = new PDO('sqlite:' . $this->tempRoot . '/modules/music/database/music_test.sqlite');
		$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		return $pdo;
	}

	// an artist plus its actual name, ready to attach songs or aliases to
	public function makeArtist($name) {
		$response = $this->post('/modules/music/ajax/artistAlias.php', [[
			'artist_id' => 'new',
			'group' => $name,
			'og_name' => $name,
			'provided_name' => $name,
			'is_actual' => true,
		]]);
		return $response['json'][0]['artist_id'];
	}
}


function assertSame($expected, $actual, $what) {
	if ($expected !== $actual) {
		throw new Exception("{$what}: expected " . var_export($expected, true) . ", got " . var_export($actual, true));
	}
}

function assertTrue($condition, $what) {
	if (!$condition) {
		throw new Exception($what);
	}
}

function assertContains($needle, $haystack, $what) {
	if (strpos((string)$haystack, $needle) === false) {
		throw new Exception("{$what}: " . var_export($needle, true) . " not found in " . var_export(substr((string)$haystack, 0, 200), true));
	}
}


function findFreePort() {
	$socket = stream_socket_server('tcp://localhost:0', $errno, $errstr);
	$name = stream_socket_get_name($socket, false);
	fclose($socket);
	return (int)substr($name, strrpos($name, ':') + 1);
}

function waitForServer($port) {
	for ($i = 0; $i < 100; $i++) {
		$socket = @stream_socket_client("tcp://localhost:{$port}", $errno, $errstr, 0.1);
		if ($socket) {
			fclose($socket);
			return true;
		}
		usleep(100000);
	}
	return false;
}

function copyProject($from, $to) {
	$skip = array_map(function ($dir) use ($from) {
		return str_replace('\\', '/', $from . '/' . $dir);
	}, SKIP_DIRS);

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($from, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::SELF_FIRST
	);

	mkdir($to, 0777, true);

	foreach ($items as $item) {
		$path = str_replace('\\', '/', $item->getPathname());

		foreach ($skip as $skipPath) {
			if (strpos($path, $skipPath) === 0) {
				continue 2;
			}
		}
		if (!$item->isDir() && preg_match('/\.(sqlite|db)$/', $path)) {
			continue;
		}

		$target = $to . '/' . substr($path, strlen(str_replace('\\', '/', $from)) + 1);
		if ($item->isDir()) {
			@mkdir($target, 0777, true);
		}
		else {
			@mkdir(dirname($target), 0777, true);
			copy($item->getPathname(), $target);
		}
	}
}

function removeDir($dir) {
	if (!is_dir($dir)) {
		return;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ($items as $item) {
		$item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
	}
	@rmdir($dir);
}
