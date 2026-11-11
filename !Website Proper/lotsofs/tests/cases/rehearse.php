<?php

/// Plain GETs and no login, so this file stands alone: `php tests/run.php rehearse`.
return [

	'the rehearsal page serves, unindexed and without warnings' => function ($ctx) {
		$response = $ctx->get('/rehearse');
		assertSame(200, $response['status'], 'GET /rehearse');

		$body = $response['body'];
		assertContains('<meta name="robots" content="noindex">', $body, 'kept out of search results');
		foreach (['Warning:', 'Notice:', 'Fatal error', 'Undefined variable'] as $sign) {
			assertTrue(strpos($body, $sign) === false, "/rehearse contains '{$sign}'");
		}
		assertSame(200, $ctx->get('/modules/main/js/rehearse.js')['status'], 'the script serves');
		assertSame(200, $ctx->get('/modules/main/css/rehearse.css')['status'], 'the stylesheet serves');
	},

	'every counterpart line is listed, directions apart from speech' => function ($ctx) {
		$scene = require __DIR__ . '/../../app/modules/main/scenes/theSuspect.php';
		$body = $ctx->get('/rehearse')['body'];

		preg_match('/<ol id="rehearseLines"[^>]*>(.*?)<\/ol>/s', $body, $list);
		assertSame(count($scene['lines']), substr_count($list[1] ?? '', '<li>'), 'one item per line');

		assertContains('<span class="rehearseSay">Not at all, Detective...</span>', $body, 'the first line');
		assertContains('<span class="rehearseSay">I know.</span>', $body, 'the last line');
		assertContains('<span class="rehearseDirection">(Sits.)</span> <span class="rehearseSay">You wouldn&#039;t mind', $body, 'a leading direction is its own span');
		assertContains('<span class="rehearseSay">Yes, it does.</span> <span class="rehearseDirection">(Pause)</span> <span class="rehearseSay">Should I have a lawyer here?</span>', $body, 'a direction mid-line splits the speech');

		preg_match_all('/<span class="rehearseSay">([^<]*)<\/span>/', $body, $spoken);
		foreach ($spoken[1] as $text) {
			assertTrue(strpos($text, '(') === false, "nothing spoken carries a direction: {$text}");
		}
	},

	'nothing links to the rehearsal page' => function ($ctx) {
		foreach (['/', '/contact', '/exchange-rates'] as $path) {
			assertTrue(strpos($ctx->get($path)['body'], 'rehearse') === false, "{$path} does not mention it");
		}
	},

];
