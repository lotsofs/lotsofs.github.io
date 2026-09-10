<?php

function langDir() {
	return realpath(__DIR__ . '/../../app/modules/music/lang');
}

function switchLanguage($ctx, $lang, $return = '/music/songs', $csrfFrom = '/music/songs') {
	return $ctx->postForm('/music/language', [
		'csrf_token' => $ctx->csrfTokenFrom($csrfFrom),
		'lang' => $lang,
		'return' => $return,
	]);
}

function rawKeyLeaked($body) {
	foreach (['artist.', 'album.', 'song.', 'nav.', 'status.', 'ajax.', 'accounts.', 'invites.', 'register.', 'login.'] as $prefix) {
		if (preg_match('/>\s*' . preg_quote($prefix, '/') . '\w/', $body)) {
			return $prefix;
		}
	}
	return null;
}

return [

	'the locale files carry every english key, even the untranslated ones' => function ($ctx) {
		$en = array_keys(require langDir() . '/en.php');
		sort($en);

		foreach (['de', 'fy'] as $locale) {
			$keys = array_keys(require langDir() . "/{$locale}.php");
			sort($keys);
			assertSame($en, $keys, "{$locale}.php should list every en key (null where not yet translated) so gaps show in the file");
		}
	},

	'the locale files define no key twice and all survive the merge' => function ($ctx) {
		foreach (['de', 'fy'] as $locale) {
			$seen = [];
			$clashes = [];

			foreach (glob(langDir() . "/{$locale}/*.php") as $file) {
				foreach (array_keys(require $file) as $key) {
					if (isset($seen[$key])) {
						$clashes[] = $key . ' in ' . $seen[$key] . ' and ' . basename($file);
					}
					$seen[$key] = basename($file);
				}
			}

			assertSame([], $clashes, "{$locale}: array_merge would silently keep only the last one");
			assertSame(count($seen), count(require langDir() . "/{$locale}.php"), "{$locale}: every defined key survives the merge");
		}
	},

	'every locale key resolves to usable text after the merge' => function ($ctx) {
		$en = require langDir() . '/en.php';

		foreach (['de', 'fy'] as $locale) {
			$overlay = array_filter(
				require langDir() . "/{$locale}.php",
				fn($value) => $value !== null && $value !== ''
			);
			$merged = array_merge($en, $overlay);

			assertSame(count($en), count($merged), "{$locale}: the merge never drops or adds a key");
			foreach ($en as $key => $_) {
				assertTrue(
					isset($merged[$key]) && $merged[$key] !== null && $merged[$key] !== '',
					"{$locale}: {$key} has no usable text after the merge"
				);
			}
		}
	},

	'a null or blank overlay value is replaced by the english base' => function ($ctx) {
		$en = require langDir() . '/en.php';
		$probe = array_key_first($en);

		$overlay = array_filter(
			[$probe => null, 'x.blank' => '', 'x.real' => 'kept'],
			fn($value) => $value !== null && $value !== ''
		);
		$merged = array_merge($en, $overlay);

		assertSame($en[$probe], $merged[$probe], 'a null overlay entry leaves the english text in place');
		assertTrue(!isset($merged['x.blank']), 'a blank overlay entry is dropped, not merged');
		assertSame('kept', $merged['x.real'], 'a real overlay entry still wins');
	},

	'switching to german renders the pages in german' => function ($ctx) {
		$ctx->ensureLoggedIn('language_switcher');

		$before = $ctx->get('/music/songs')['body'];
		assertContains('All Songs', $before, 'starts in english');

		assertSame(303, switchLanguage($ctx, 'de')['status'], 'the switch redirects');

		$after = $ctx->get('/music/songs')['body'];
		assertContains('Alle Songs', $after, 'the heading is german');
		assertContains('<html lang="de">', $after, 'the html lang attribute follows');
		assertSame(null, rawKeyLeaked($after), 'no raw catalogue keys are shown');
	},

	'the language choice is saved on the account and restored at login' => function ($ctx) {
		$ctx->ensureLoggedIn('language_keeper');

		switchLanguage($ctx, 'de');

		$stored = $ctx->db()->query("SELECT lang FROM account WHERE account_name = 'language_keeper'")->fetch()['lang'];
		assertSame('de', $stored, 'the account remembers german');

		$ctx->newSession();
		$ctx->ensureLoggedIn('language_keeper');

		assertContains('<html lang="de">', $ctx->get('/music/songs')['body'], 'a fresh session picks it back up from the account');
	},

	'switching back to english reverts everything' => function ($ctx) {
		$ctx->ensureLoggedIn('language_flipper');

		switchLanguage($ctx, 'de');
		assertContains('Alle Songs', $ctx->get('/music/songs')['body'], 'german took');

		switchLanguage($ctx, 'en');
		assertContains('All Songs', $ctx->get('/music/songs')['body'], 'back to english');
		assertSame('en', $ctx->db()->query("SELECT lang FROM account WHERE account_name = 'language_flipper'")->fetch()['lang'], 'and the account too');
	},

	'the browser Accept-Language header is ignored' => function ($ctx) {
		$ctx->newSession();

		$body = $ctx->get('/music/login', false, ['Accept-Language: de-DE,de;q=0.9,en;q=0.8'])['body'];
		assertContains('<html lang="en">', $body, 'a german browser gets the site default, not german');
		assertContains('Log In', $body, 'and the default renders');
	},

	'a guest who picks a language keeps it through registration' => function ($ctx) {
		$ctx->newSession();
		logInAs($ctx, 'first_owner', 'correct horse');
		$ctx->postForm('/music/invites', ['csrf_token' => $ctx->csrfTokenFrom('/music/invites')]);
		$code = $ctx->db()->query("SELECT code FROM invite WHERE used_at IS NULL AND revoked_at IS NULL ORDER BY id DESC")->fetch()['code'];

		$ctx->newSession();
		switchLanguage($ctx, 'de', '/music/login', '/music/login');

		registerAccount($ctx, [
			'invite_code' => $code,
			'account_name' => 'neuer_benutzer',
			'password' => 'correct horse',
			'password_confirm' => 'correct horse',
		]);

		assertSame('de', $ctx->db()->query("SELECT lang FROM account WHERE account_name = 'neuer_benutzer'")->fetch()['lang'], 'the new account keeps the chosen language');
	},

	'the nav switcher lists the languages in the active language' => function ($ctx) {
		$ctx->ensureLoggedIn('switcher_viewer');
		switchLanguage($ctx, 'en');

		$body = $ctx->get('/music/songs')['body'];
		assertContains('action="/music/language"', $body, 'the switcher form is present');
		assertContains('<option value="en" selected>English</option>', $body, 'english UI: english is "English", active');
		assertContains('<option value="de">German</option>', $body, 'english UI: german is "German"');

		switchLanguage($ctx, 'de');
		$german = $ctx->get('/music/songs')['body'];
		assertContains('<option value="de" selected>Deutsch</option>', $german, 'german UI: german is "Deutsch", active');
		assertContains('<option value="en">Englisch</option>', $german, 'german UI: english is "Englisch"');
	},

	'every locale names every available language' => function ($ctx) {
		$codes = array_map(fn($f) => basename($f, '.php'), glob(langDir() . '/*.php'));

		foreach ($codes as $file) {
			$catalogue = require langDir() . '/' . $file . '.php';
			foreach ($codes as $named) {
				assertTrue(!empty($catalogue['language.' . $named]), "{$file}.php has no 'language.{$named}' for the switcher");
			}
		}
	},

];
