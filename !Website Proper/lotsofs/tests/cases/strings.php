<?php

function musicCatalogue() {
	return require __DIR__ . '/../../app/modules/music/lang/en.php';
}

function musicSourceFiles() {
	// server logic lives in app/, browser JS in public/ — the t() calls are in both
	$roots = [
		realpath(__DIR__ . '/../../app/modules/music'),
		realpath(__DIR__ . '/../../public/modules/music'),
	];
	$files = [];

	foreach ($roots as $root) {
		foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
			$path = str_replace('\\', '/', $file->getPathname());
			if (preg_match('/\.(php|js)$/', $path) && strpos($path, '/lang/') === false) {
				$files[] = $path;
			}
		}
	}

	return $files;
}

function musicKeysUsed() {
	$used = [];

	foreach (musicSourceFiles() as $path) {
		if (preg_match_all('/\bt\(\s*[\'"]([a-z][\w.]*)[\'"]/i', file_get_contents($path), $m)) {
			foreach ($m[1] as $key) {
				$used[$key][] = basename($path);
			}
		}
	}

	return $used;
}

return [

	'every key the code asks for exists in the catalogue' => function ($ctx) {
		$catalogue = musicCatalogue();
		$missing = [];

		foreach (musicKeysUsed() as $key => $files) {
			if (!array_key_exists($key, $catalogue)) {
				$missing[] = $key . ' (' . implode(', ', array_unique($files)) . ')';
			}
		}

		assertSame([], $missing, 'keys referenced by code but absent from the catalogue');
	},

	'no key is built by gluing strings together' => function ($ctx) {
		$offenders = [];

		foreach (musicSourceFiles() as $path) {
			if (preg_match_all('/\bt\(\s*[\'"][\w.]*[\'"]\s*\./', file_get_contents($path), $m)) {
				$offenders[] = basename($path) . ': ' . implode(', ', $m[0]);
			}
		}

		assertSame([], $offenders, 'a glued key cannot be grepped and hides typos');
	},

	'the catalogue has no unreachable keys' => function ($ctx) {
		$used = musicKeysUsed();
		$unused = [];

		foreach (array_keys(musicCatalogue()) as $key) {
			if (!isset($used[$key])) {
				$unused[] = $key;
			}
		}

		assertSame([], $unused, 'keys in the catalogue that nothing references');
	},

	'the catalogue files define no key twice' => function ($ctx) {
		$dir = realpath(__DIR__ . '/../../app/modules/music/lang/en');
		$seen = [];
		$clashes = [];

		foreach (glob($dir . '/*.php') as $file) {
			foreach (array_keys(require $file) as $key) {
				if (isset($seen[$key])) {
					$clashes[] = $key . ' in ' . $seen[$key] . ' and ' . basename($file);
				}
				$seen[$key] = basename($file);
			}
		}

		assertSame([], $clashes, 'array_merge would silently keep only the last one');
		assertSame(count($seen), count(musicCatalogue()), 'every defined key survives the merge');
	},

	'no page renders a raw catalogue key' => function ($ctx) {
		$ctx->ensureLoggedIn();

		foreach (['/music', '/music/songs', '/music/artists', '/music/albums', '/music/add-songs', '/music/invites', '/music/accounts'] as $path) {
			$body = $ctx->get($path)['body'];

			foreach (['artist.', 'album.', 'song.', 'nav.', 'status.', 'ajax.'] as $prefix) {
				assertTrue(preg_match('/>\s*' . preg_quote($prefix, '/') . '\w/', $body) !== 1, "{$path} shows a raw {$prefix}* key");
			}
		}
	},

];
