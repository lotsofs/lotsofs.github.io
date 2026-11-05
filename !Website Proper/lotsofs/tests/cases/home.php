<?php

require_once __DIR__ . '/../../app/modules/music/homeLinks.php';

/// The home page's links as [href, text], in page order.
function homeLinksOn($body) {
	if (!preg_match('#<ul class="homeLinks">(.*?)</ul>#s', $body, $list)) {
		return [];
	}

	preg_match_all('#<a href="([^"]*)">([^<]*)</a>#', $list[1], $links, PREG_SET_ORDER);

	return array_map(fn($link) => [html_entity_decode($link[1]), html_entity_decode($link[2])], $links);
}

function homeLinkTexts($ctx) {
	return array_column(homeLinksOn($ctx->get('/music')['body']), 1);
}

function homeLinkPost($ctx, $fields) {
	return $ctx->postForm('/music', $fields + ['csrf_token' => $ctx->csrfTokenFrom('/music')]);
}

function homeLinkAdd($ctx, $fields = []) {
	return homeLinkPost($ctx, $fields + ['action' => 'add', 'filter' => '', 'sort' => 'id', 'dir' => 'asc', 'label' => '', 'note' => '']);
}

function homeLinkIds($ctx) {
	return array_map('intval', $ctx->db()->query("SELECT id FROM home_link ORDER BY position, id")->fetchAll(PDO::FETCH_COLUMN));
}

function homeLinkCount($ctx) {
	return (int)$ctx->db()->query("SELECT COUNT(*) FROM home_link")->fetchColumn();
}

return [

	'logging in lands on the home page, and so does visiting login while signed in' => function ($ctx) {
		$response = $ctx->ensureLoggedIn();

		assertSame(302, $response['status'], 'login redirects');
		assertSame('/music', $response['location'], 'to the home page');
		assertSame('/music', $ctx->get('/music/login')['location'], 'login, while signed in');
		assertSame('/music', $ctx->get('/music/register')['location'], 'register, while signed in');
		assertContains('<a href="/music" class="navCurrent">Home</a>', $ctx->get('/music')['body'], 'the nav leads home');
	},

	'with no links picked, the home page says so' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$ctx->db()->exec("DELETE FROM home_link");

		$body = $ctx->get('/music')['body'];
		assertContains('No links picked yet.', $body, 'the empty message');
		assertTrue(strpos($body, 'homeIntro') === false, 'and no welcome promising links below');
	},

	'each link says how much of it you and everyone have rated, borrowed scores included' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$ctx->db()->exec("DELETE FROM home_link");

		$artistId = $ctx->makeArtist('Home Stats Artist');
		[$one, $two, $three] = [makeSong($ctx, $artistId, 'Home Stats One'), makeSong($ctx, $artistId, 'Home Stats Two'), makeSong($ctx, $artistId, 'Home Stats Three')];
		$remaster = makeSong($ctx, $artistId, 'Home Stats One (Remastered)');
		$ctx->post('/music/ajax/song-relationship', ['action' => 'add', 'song_id' => $remaster, 'other_song_id' => $one, 'type' => 'remaster', 'role' => 'variant']);

		$ctx->post('/music/ajax/song-rating', ['id' => $one, 'field' => 'score', 'value' => '7']);
		$ctx->post('/music/ajax/song-rating', ['id' => $two, 'field' => 'note', 'value' => 'a note is not a score']);
		homeLinkAdd($ctx, ['filter' => "artist:{$artistId}"]);
		$singleId = makeAlbum($ctx, 'Home Stats Single', $artistId, [['song_id' => $three, 'position' => 1]]);
		homeLinkAdd($ctx, ['filter' => "album:{$singleId}"]);

		$ctx->ensureLoggedIn('home_stats_rater', 'test password', false);
		$ctx->post('/music/ajax/song-rating', ['id' => $three, 'field' => 'score', 'value' => '9']);

		/// test_runner has One and, borrowed from it, the remaster; home_stats_rater has Three.
		$possible = 4 * (int)$ctx->db()->query("SELECT COUNT(*) FROM account")->fetchColumn();
		$body = $ctx->get('/music')['body'];

		assertContains('<p class="homeIntro">Welcome to this music page. Below are some featured pages.</p>', $body, 'the welcome');
		assertContains(
			'<p class="homeLinkStats">You rated 1 of 4 songs (25%). Everyone together gave 3 of ' . $possible . ' possible ratings (' . musicHomePercent(3, $possible) . '%).</p>',
			$body,
			'the counts, read by home_stats_rater'
		);
		assertContains('<p class="homeLinkStats">You rated 1 of 1 song (100%).', $body, 'one song is one song');
	},

	'a percentage is 0 or 100 only when it is exactly that' => function ($ctx) {
		assertSame(0, musicHomePercent(0, 5), 'none');
		assertSame(1, musicHomePercent(1, 1000), 'one in a thousand');
		assertSame(33, musicHomePercent(1, 3), 'a third');
		assertSame(99, musicHomePercent(999, 1000), 'all but one');
		assertSame(100, musicHomePercent(5, 5), 'all');
	},

	'an artist link sorted by a score goes to that view of the song list' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$ctx->db()->exec("DELETE FROM home_link");

		$artistId = $ctx->makeArtist('Home Page Artist');
		$me = (int)$ctx->db()->query("SELECT id FROM account WHERE account_name = 'test_runner'")->fetchColumn();

		$response = homeLinkAdd($ctx, ['filter' => "artist:{$artistId}", 'sort' => "score_{$me}", 'dir' => 'desc']);
		assertSame('/music?edit=1', $response['location'], 'back to the editor');
		assertContains('id="homeLinkEditor" open', $ctx->get('/music?edit=1')['body'], 'which is open');

		$href = "/music/songs?sort=score_{$me}&dir=desc&artist={$artistId}";
		assertSame([[$href, 'Home Page Artist · test_runner score ▼']], homeLinksOn($ctx->get('/music')['body']), 'the link and its text');

		preg_match('#<h1 id="songListHeading">\s*(.*?)\s*</h1>#s', $ctx->get($href)['body'], $heading);
		assertSame('Home Page Artist', $heading[1] ?? null, 'the song list opens on that artist');
	},

	'a typed label and note are shown, escaped, and a link with neither reads as the song list heading' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$ctx->db()->exec("DELETE FROM home_link");

		homeLinkAdd($ctx, ['label' => '  <b>Best</b> & more ', 'note' => 'Start "here"']);
		homeLinkAdd($ctx);

		$body = $ctx->get('/music')['body'];
		assertContains('>&lt;b&gt;Best&lt;/b&gt; &amp; more</a>', $body, 'the label, trimmed and escaped');
		assertContains('<p class="homeLinkNote">Start &quot;here&quot;</p>', $body, 'the note, escaped');
		assertSame(['<b>Best</b> & more', 'All Songs'], homeLinkTexts($ctx), 'the unlabelled one is the song list heading');
		assertSame(1, (int)$ctx->db()->query("SELECT COUNT(*) FROM home_link WHERE label IS NULL AND note IS NULL")->fetchColumn(), 'blanks are stored as nothing');
	},

	'an album link takes its artist from the album, and a various-artists album has none' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$ctx->db()->exec("DELETE FROM home_link");

		$artistId = $ctx->makeArtist('Home Album Artist');
		$otherId = $ctx->makeArtist('Home Album Other Artist');
		$albumId = makeAlbum($ctx, 'Home Page Album', $artistId, []);
		$variousId = makeAlbum($ctx, 'Home Page Compilation', null, []);

		homeLinkAdd($ctx, ['filter' => "album:{$albumId}"]);
		homeLinkAdd($ctx, ['filter' => "album:{$variousId}"]);

		assertSame([
			["/music/songs?sort=id&dir=asc&artist={$artistId}&album={$albumId}", 'Home Album Artist — Home Page Album'],
			["/music/songs?sort=id&dir=asc&album={$variousId}", 'Home Page Compilation'],
		], homeLinksOn($ctx->get('/music')['body']), 'both links');

		$ctx->post('/music/ajax/album-edit', ['album_id' => $albumId, 'field' => 'artist', 'value' => (string)$otherId]);

		assertSame(
			["/music/songs?sort=id&dir=asc&artist={$otherId}&album={$albumId}", 'Home Album Other Artist — Home Page Album'],
			homeLinksOn($ctx->get('/music')['body'])[0],
			'an album moved to another artist takes the link with it'
		);
	},

	'a filter, sort or direction the song list does not have writes nothing, nor does a post without the token' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$before = homeLinkCount($ctx);

		foreach ([
			['filter' => 'artist:999999'],
			['filter' => 'album:999999'],
			['filter' => 'album:abc'],
			['filter' => 'song:1'],
			['sort' => 'score_999999'],
			['sort' => 'aliases'],
			['dir' => 'up'],
		] as $bad) {
			homeLinkAdd($ctx, $bad);
		}

		$ctx->postForm('/music', ['csrf_token' => 'not the token', 'action' => 'add', 'filter' => '', 'sort' => 'id', 'dir' => 'asc']);

		assertSame($before, homeLinkCount($ctx), 'no link was added');
	},

	'links move up and down, are saved in place, and are deleted' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$ctx->db()->exec("DELETE FROM home_link");

		foreach (['A', 'B', 'C'] as $label) {
			homeLinkAdd($ctx, ['label' => $label]);
		}
		[$a, $b, $c] = homeLinkIds($ctx);

		homeLinkPost($ctx, ['action' => 'up', 'link_id' => (string)$c]);
		assertSame(['A', 'C', 'B'], homeLinkTexts($ctx), 'C moved up');

		homeLinkPost($ctx, ['action' => 'down', 'link_id' => (string)$a]);
		assertSame(['C', 'A', 'B'], homeLinkTexts($ctx), 'A moved down');

		homeLinkPost($ctx, ['action' => 'up', 'link_id' => (string)$c]);
		homeLinkPost($ctx, ['action' => 'down', 'link_id' => (string)$b]);
		assertSame(['C', 'A', 'B'], homeLinkTexts($ctx), 'the ends stay put');

		homeLinkPost($ctx, ['action' => 'save', 'link_id' => (string)$a, 'filter' => '', 'sort' => 'year', 'dir' => 'desc', 'label' => '', 'note' => 'Newest']);
		assertSame(['C', 'All Songs · Year ▼', 'B'], homeLinkTexts($ctx), 'A rewritten in its place');
		assertSame([$c, $a, $b], homeLinkIds($ctx), 'keeping its id');

		homeLinkPost($ctx, ['action' => 'delete', 'link_id' => (string)$a]);
		assertSame(['C', 'B'], homeLinkTexts($ctx), 'A deleted');
	},

	'someone who is not an admin sees the links but cannot change them' => function ($ctx) {
		$ctx->ensureLoggedIn();
		$ctx->db()->exec("DELETE FROM home_link");
		homeLinkAdd($ctx, ['label' => 'For everyone']);

		$ctx->ensureLoggedIn('home_reader', 'test password', false);
		$body = $ctx->get('/music')['body'];

		assertSame(['For everyone'], array_column(homeLinksOn($body), 1), 'the links are listed');
		assertTrue(strpos($body, 'homeLinkEditor') === false, 'with no editor');

		$response = homeLinkAdd($ctx, ['label' => 'Sneaked in']);
		assertSame('/music', $response['location'], 'a post goes back home');
		assertSame(1, homeLinkCount($ctx), 'and writes nothing');

		homeLinkPost($ctx, ['action' => 'delete', 'link_id' => (string)homeLinkIds($ctx)[0]]);
		assertSame(1, homeLinkCount($ctx), 'nor deletes');
	},

];
