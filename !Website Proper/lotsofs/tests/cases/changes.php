<?php

require_once __DIR__ . '/../../app/modules/music/changes.php';

/// The first entry the page shows, in the order it shows them.
function changesFirstEntry() {
	foreach (musicChangelog() as $release) {
		foreach (MUSIC_CHANGE_GROUPS as $group) {
			if (!empty($release['changes'][$group])) {
				return $release['changes'][$group][0];
			}
		}
	}
	return null;
}

return [

	'every changelog entry has english and only the locales music offers' => function ($ctx) {
		$locales = (require __DIR__ . '/../../app/modules/music/config.php')['locales'];

		foreach (musicChangelog() as $i => $release) {
			foreach ($release['changes'] as $group => $entries) {
				assertTrue(in_array($group, MUSIC_CHANGE_GROUPS, true), "release {$i}: '{$group}' is not a kind of change the page shows");

				foreach ($entries as $j => $entry) {
					assertTrue(trim($entry['en'] ?? '') !== '', "release {$i}, {$group} {$j}: english is required");
					assertSame([], array_values(array_diff(array_keys($entry), $locales)), "release {$i}, {$group} {$j}: a locale music does not offer would never render");
				}
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

		if (musicChangelog()[0]['date'] === null) {
			$fy = require __DIR__ . '/../../app/modules/music/lang/fy.php';
			assertContains('<h2>' . $fy['changes.latest'] . '</h2>', $body, 'the undated release is headed as the latest');
		}
	},

];
