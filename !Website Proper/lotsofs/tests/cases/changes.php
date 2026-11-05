<?php

require_once __DIR__ . '/../../app/modules/music/changes.php';

/// The first entry the page shows for everyone, or under For admins.
function changesFirstEntry($forAdmins = false) {
	foreach (musicChangelog() as $release) {
		foreach ($release['changes'] as $entry) {
			if (!empty($entry['admin']) === $forAdmins) {
				return $entry;
			}
		}
	}
	return null;
}

return [

	'every changelog entry has english and only the locales music offers' => function ($ctx) {
		$locales = (require __DIR__ . '/../../app/modules/music/config.php')['locales'];

		foreach (musicChangelog() as $i => $release) {
			assertTrue(array_is_list($release['changes']), "release {$i}: one list of entries, with no kinds of change");

			foreach ($release['changes'] as $j => $entry) {
				assertTrue(trim($entry['en'] ?? '') !== '', "release {$i}, entry {$j}: english is required");
				assertSame([], array_values(array_diff(array_keys($entry), $locales, ['admin'])), "release {$i}, entry {$j}: a locale music does not offer would never render");
				assertTrue(!isset($entry['admin']) || $entry['admin'] === true, "release {$i}, entry {$j}: admin is true or left out");
			}
		}
	},

	'releases run newest first, with at most one undated and that one on top' => function ($ctx) {
		$dates = array_map(fn($release) => $release['date'], musicChangelog());

		foreach ($dates as $i => $date) {
			if ($date === null) {
				assertSame(0, $i, 'only the first release may be undated');
				continue;
			}

			assertTrue(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m) === 1 && checkdate((int)$m[2], (int)$m[3], (int)$m[1]), "{$date} is a Y-m-d date");

			if ($i > 0 && $dates[$i - 1] !== null) {
				assertTrue(strcmp($dates[$i - 1], $date) > 0, "{$dates[$i - 1]} comes before {$date}");
			}
		}
	},

	'an entry falls back to english, and bold is its only markup' => function ($ctx) {
		$entry = ['en' => 'English', 'fy' => '  ', 'de' => 'Deutsch'];

		assertSame('Deutsch', musicChangeText($entry, 'de'), 'a translation is used');
		assertSame('English', musicChangeText($entry, 'fy'), 'a blank one falls back');
		assertSame('English', musicChangeText(['en' => 'English'], 'fy'), 'and so does a missing one');
		assertSame('<strong>Do this</strong> first &lt;b&gt;', musicChangeHtml('**Do this** first <b>'), 'bold is converted and everything else escaped');
	},

	'what only admins can use is listed apart, after everyone\'s, and everyone sees it' => function ($ctx) {
		$ctx->ensureLoggedIn('changes_reader', 'test password', false);
		$ctx->postForm('/music/language', [
			'csrf_token' => $ctx->csrfTokenFrom('/music/changes'),
			'lang' => 'en',
			'return' => '/music/changes',
		]);

		$body = $ctx->get('/music/changes')['body'];
		$heading = strpos($body, '<h3>For admins</h3>');
		$admin = changesFirstEntry(true);

		if ($admin === null) {
			assertTrue($heading === false, 'with nothing for admins there is no heading for it');
			return;
		}

		/// The release the first admin entry is in, and the first of everyone's entries there.
		foreach (musicChangelog() as $release) {
			if (in_array($admin, $release['changes'], true)) {
				$everyone = array_values(array_filter($release['changes'], fn($entry) => empty($entry['admin'])))[0] ?? null;
				break;
			}
		}

		$at = strpos($body, musicChangeHtml($admin['en']));
		assertTrue($heading !== false, 'a non-admin sees the For admins heading');
		assertTrue($at !== false && $at > $heading, 'with the entry under it');
		assertSame(1, substr_count($body, musicChangeHtml($admin['en'])), 'and only there');
		assertTrue(strpos($body, '<h3>Added</h3>') === false && strpos($body, '<h3>Changed</h3>') === false, 'with no kinds of change anywhere on the page');
		if ($everyone !== null) {
			assertTrue(strpos($body, musicChangeHtml($everyone['en'])) < $heading, 'which comes first in its release');
		}
	},

	'the page lists the changes in the reader\'s language' => function ($ctx) {
		$entry = changesFirstEntry();
		assertTrue($entry !== null, 'the changelog has something to show');

		$ctx->ensureLoggedIn('changes_reader', 'test password', false);

		$response = $ctx->get('/music/changes');
		assertSame(200, $response['status'], 'GET /music/changes');
		assertContains(musicChangeHtml($entry['en']), $response['body'], 'the newest entry in english');
		assertContains('href="/music/changes"', $response['body'], 'the nav links the page');

		$ctx->postForm('/music/language', [
			'csrf_token' => $ctx->csrfTokenFrom('/music/changes'),
			'lang' => 'fy',
			'return' => '/music/changes',
		]);

		$body = $ctx->get('/music/changes')['body'];
		assertContains(musicChangeHtml(musicChangeText($entry, 'fy')), $body, 'and in frisian once that is picked');

		$fy = require __DIR__ . '/../../app/modules/music/lang/fy.php';
		$latest = '<h2>' . $fy['changes.latest'] . '</h2>';
		$newest = musicChangelog()[0];

		if ($newest['date'] === null && $newest['changes']) {
			assertContains($latest, $body, 'the undated release is headed as the latest');
		} else {
			assertTrue(strpos($body, $latest) === false, 'an undated release with nothing in it is not shown');
		}
	},

];
