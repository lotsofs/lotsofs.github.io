<?php

// usage: php tests/run.php [case ...]
// copies the project to a temp dir, serves it, runs tests/cases/*.php against it
// naming cases (basstabs pages) runs only those files; they share one database, so a filtered
// run of cases that read what an earlier file wrote can fail where the full suite passes
// every music account a case file makes, but test_runner, is deleted when that file ends

if (php_sapi_name() !== 'cli') {
	exit;
}

$caseFiles = glob(__DIR__ . '/cases/*.php');
$only = array_slice($argv, 1);
if ($only) {
	$caseNames = array_map(fn($file) => basename($file, '.php'), $caseFiles);
	$unknown = array_diff($only, $caseNames);
	if ($unknown) {
		fwrite(STDERR, "no case file named " . implode(', ', $unknown) . "; there are " . implode(', ', $caseNames) . "\n");
		exit(1);
	}
	$caseFiles = array_values(array_filter($caseFiles, fn($file) => in_array(basename($file, '.php'), $only, true)));
}

const SKIP_DIRS = ['public/modules/ss2/img', 'public/modules/ss2/fonts', 'public/raw', 'tests', '_deploy'];

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
	@unlink($tempRoot . '.cookies');

	for ($i = 0; $i < 20 && file_exists($serverLog); $i++) {
		@unlink($serverLog);
		usleep(50000);
	}
});

echo "copying project to a scratch copy\n";
copyProject($projectRoot, $tempRoot);

echo "serving scratch copy on port {$port}\n";
putenv('LOTSOFS_LOCALE=en');
$server = proc_open(
	escapeshellarg(PHP_BINARY) . " -S localhost:{$port} -t " . escapeshellarg($tempRoot . '/public'),
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

$ctx->get('/music/login');

$passed = 0;
$failed = 0;

require __DIR__ . '/helpers.php';

foreach ($caseFiles as $caseFile) {
	echo "\n" . basename($caseFile, '.php') . "\n";
	$lastAccount = $ctx->lastAccountId();

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

	$ctx->dropAccountsAfter($lastAccount);
}

echo "\n{$passed} passed, {$failed} failed" . ($only ? ' (only ' . implode(', ', $only) . ')' : '') . "\n";
exit($failed === 0 ? 0 : 1);

class TestContext {
	private $port;
	private $tempRoot;
	private $cookieJar;
	private $csrfToken;

	public function __construct($port, $tempRoot) {
		$this->port = $port;
		$this->tempRoot = $tempRoot;
		$this->cookieJar = $tempRoot . '.cookies';
	}

	public function newSession() {
		@unlink($this->cookieJar);
		$this->csrfToken = null;
	}

	public function get($path, $followRedirects = false, $extraHeaders = []) {
		return $this->request('GET', $path, null, $followRedirects, 'application/json', true, $extraHeaders);
	}

	public function post($path, $payload) {
		return $this->request('POST', $path, is_string($payload) ? $payload : json_encode($payload), false);
	}

	public function postWithoutCsrf($path, $payload) {
		return $this->request('POST', $path, json_encode($payload), false, 'application/json', false);
	}

	public function postForm($path, $fields, $followRedirects = false, $extraHeaders = []) {
		return $this->request('POST', $path, http_build_query($fields), $followRedirects, 'application/x-www-form-urlencoded', true, $extraHeaders);
	}

	public function csrfTokenFrom($path) {
		$body = $this->get($path)['body'];
		if (preg_match('/name="csrf_token" value="([^"]+)"/', $body, $m)) {
			return $m[1];
		}
		return null;
	}

	private function csrfHeaderToken() {
		if ($this->csrfToken === null) {
			/// The home page signs a visitor out to login, and login sends a signed-in one home.
			$body = $this->request('GET', '/music', null, false)['body'];
			if (!preg_match('/name="csrfToken" content="([^"]+)"/', $body, $m)) {
				$body = $this->request('GET', '/music/login', null, false)['body'];
			}
			if (!preg_match('/name="csrfToken" content="([^"]+)"/', $body, $m)) {
				throw new RuntimeException('no csrf token meta on /music or /music/login');
			}
			$this->csrfToken = $m[1];
		}
		return $this->csrfToken;
	}

	private function request($method, $path, $body, $followRedirects, $contentType = 'application/json', $withCsrf = true, $extraHeaders = []) {
		$ch = curl_init("http://localhost:{$this->port}{$path}");
		/// Headers apart from the body, which a split afterwards would hold twice.
		$responseHeaders = '';
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADERFUNCTION => function ($ch, $line) use (&$responseHeaders) {
				$responseHeaders .= $line;
				return strlen($line);
			},
			CURLOPT_FOLLOWLOCATION => $followRedirects,
			CURLOPT_CUSTOMREQUEST => $method,
			CURLOPT_COOKIEJAR => $this->cookieJar,
			CURLOPT_COOKIEFILE => $this->cookieJar,
		]);
		$headers = $extraHeaders;
		if ($body !== null) {
			$headers[] = 'Content-Type: ' . $contentType;
			if ($withCsrf && $contentType === 'application/json') {
				$headers[] = 'X-CSRF-Token: ' . $this->csrfHeaderToken();
			}
			curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
		}
		if ($headers) {
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		}
		$content = curl_exec($ch);
		$status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
		curl_close($ch);

		if ($content === false) {
			$content = '';
		}
		$location = null;
		if (preg_match('/^location:\s*(.+)$/mi', $responseHeaders, $m)) {
			$location = trim($m[1]);
		}

		return [
			'status' => $status,
			'body' => $content,
			'headers' => $responseHeaders,
			'location' => $location,
			'json' => json_decode($content, true),
		];
	}

	public function db() {
		return $this->dbFor('music_test.sqlite');
	}

	public function lastAccountId() {
		return (int)$this->db()->query("SELECT COALESCE(MAX(id), 0) FROM account")->fetchColumn();
	}

	/* The music accounts made after $id, but test_runner, and everything they wrote.
	   Every account is a rater on the song list, so keeping them all made that page
	   grow as accounts × songs: 104 accounts and 42.6 MB by the end of a run, which
	   ran the runner out of memory (2026-11-12). No case file uses another's
	   accounts. The session goes too, as it may be one of them. */
	public function dropAccountsAfter($id) {
		$db = $this->db();
		$dropped = "SELECT id FROM account WHERE id > " . (int)$id . " AND account_name != 'test_runner'";
		if (!$db->query($dropped)->fetch()) {
			return;
		}

		$db->beginTransaction();
		foreach (['account_song', 'rating_audit', 'account_song_column', 'edit_audit'] as $table) {
			$db->exec("DELETE FROM {$table} WHERE account_id IN ({$dropped})");
		}
		$db->exec("DELETE FROM invite WHERE created_by_account_id IN ({$dropped}) OR used_by_account_id IN ({$dropped})");
		$db->exec("DELETE FROM account WHERE id IN ({$dropped})");
		$db->commit();

		$this->newSession();
	}

	/// One module, one database. db() is music's because that is what nearly
	/// every case file wants; another module names its own file.
	/// A path inside the scratch copy, for tests that run a tool against it.
	public function path($relative) {
		return $this->tempRoot . '/' . $relative;
	}

	public function dbFor($file) {
		$pdo = new PDO('sqlite:' . $this->tempRoot . '/data/' . $file);
		$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
		$pdo->exec('PRAGMA busy_timeout = 5000');
		return $pdo;
	}

	public function ensureLoggedIn($name = 'test_runner', $password = 'test password', $isAdmin = true) {
		$this->newSession();

		$db = $this->db();

		$stmt = $db->prepare("SELECT id FROM account WHERE account_name = ?");
		$stmt->execute([$name]);

		if (!$stmt->fetch()) {
			$insert = $db->prepare("INSERT INTO account (account_name, password_hash, is_admin, lang) VALUES (?, ?, ?, 'en')");
			$insert->execute([$name, password_hash($password, PASSWORD_BCRYPT, ['cost' => 5]), $isAdmin ? 1 : 0]);
		}

		return $this->postForm('/music/login', [
			'csrf_token' => $this->csrfTokenFrom('/music/login'),
			'account_name' => $name,
			'password' => $password,
		]);
	}

	public function songId($title) {
		$stmt = $this->db()->prepare("SELECT s.id FROM song s JOIN song_alias sa ON sa.song_id = s.id WHERE sa.name = ?");
		$stmt->execute([$title]);
		$row = $stmt->fetch();

		return $row ? (int)$row['id'] : 0;
	}

	public function songTitle($songId) {
		$stmt = $this->db()->prepare("SELECT name FROM song_alias WHERE song_id = ? AND is_actual = 1");
		$stmt->execute([$songId]);
		$row = $stmt->fetch();

		return $row ? $row['name'] : null;
	}

	public function songArtistId($songId) {
		$stmt = $this->db()->prepare("SELECT artist_id FROM song_artist WHERE song_id = ?");
		$stmt->execute([$songId]);
		$row = $stmt->fetch();

		return $row ? (int)$row['artist_id'] : 0;
	}

	public function songCount($title) {
		$stmt = $this->db()->prepare("SELECT COUNT(*) c FROM song s JOIN song_alias sa ON sa.song_id = s.id WHERE sa.name = ?");
		$stmt->execute([$title]);

		return (int)$stmt->fetch()['c'];
	}

	public function makeArtist($name) {
		$response = $this->post('/music/ajax/artist-alias', [[
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
	@mkdir($to . '/data', 0777, true);

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
