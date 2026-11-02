<?php

require_once __DIR__ . '/../../app/modules/basstabs/tabs.php';

/// Every element of one section carrying a class, as its attributes plus 'text', in document order.
function bassTabShapes($body, $sectionName, $class) {
	$pattern = '/<h2[^>]*>' . preg_quote($sectionName, '/') . '<\/h2>(.*?)<\/section>/s';
	if (!preg_match($pattern, $body, $section)) {
		throw new Exception("no section named {$sectionName}");
	}

	preg_match_all('/<\w+\b([^>]*?)\/?>([^<]*)/', $section[1], $matches, PREG_SET_ORDER);

	$shapes = [];
	foreach ($matches as $match) {
		preg_match_all('/([\w-]+)="([^"]*)"/', $match[1], $pairs, PREG_SET_ORDER);

		$attributes = [];
		foreach ($pairs as $pair) {
			$attributes[$pair[1]] = html_entity_decode($pair[2]);
		}

		if (in_array($class, preg_split('/\s+/', $attributes['class'] ?? ''), true)) {
			$shapes[] = $attributes + ['text' => trim(html_entity_decode($match[2]))];
		}
	}

	return $shapes;
}

/// A section's fretboard dots as 'E8' => 'anchor'|'position'|'scale'.
function bassTabBoardTiers($body, $sectionName) {
	$tiers = [];
	foreach (bassTabShapes($body, $sectionName, 'bassTabBoardDot') as $dot) {
		$classes = preg_split('/\s+/', $dot['class']);
		$tiers[$dot['data-string'] . $dot['data-fret']] = in_array('bassTabBoardAnchor', $classes, true)
			? 'anchor'
			: (in_array('bassTabBoardPosition', $classes, true) ? 'position' : 'scale');
	}

	return $tiers;
}

/// The anchor and the rest of the hand position together, sorted.
function bassTabBoardPosition($tiers) {
	$position = array_merge(array_keys($tiers, 'anchor'), array_keys($tiers, 'position'));
	sort($position);

	return $position;
}

/// A section's frets as 'E8 E6 ...', in order.
function bassTabFrets($shapes) {
	return implode(' ', array_map(fn($fret) => $fret['data-string'] . $fret['text'], $shapes));
}

return [

	'the bass tab list links every song sheet by its title' => function ($ctx) {
		$response = $ctx->get('/basstabs');
		assertSame(200, $response['status'], 'GET /basstabs');

		$songs = bassTabSongs();
		assertTrue(count($songs) > 0, 'there is at least one song file to list');

		foreach ($songs as $slug => $song) {
			assertContains('href="/basstabs/' . $slug . '"', $response['body'], "the list links {$slug}");
			assertContains(htmlspecialchars($song['title']), $response['body'], "the list names {$slug}");
		}
	},

	'every song sheet serves its title and sections without a warning' => function ($ctx) {
		foreach (bassTabSongs() as $slug => $song) {
			$response = $ctx->get("/basstabs/{$slug}");
			assertSame(200, $response['status'], "GET /basstabs/{$slug}");

			foreach (['Warning:', 'Notice:', 'Fatal error', 'Uncaught', 'Undefined'] as $sign) {
				assertTrue(strpos($response['body'], $sign) === false, "/basstabs/{$slug} contains '{$sign}'");
			}

			assertTrue(
				(bool)preg_match('/<h1[^>]*>' . preg_quote(htmlspecialchars($song['title']), '/') . '<\/h1>/', $response['body']),
				"/basstabs/{$slug} carries its title as the heading"
			);

			/// A section that is only a board needs no name.
			foreach (array_filter($song['sections'], fn($section) => isset($section['name'])) as $section) {
				assertContains('>' . htmlspecialchars($section['name']) . '</h2>', $response['body'], "/basstabs/{$slug} has its {$section['name']}");

				if (isset($section['text'])) {
					assertContains('>' . htmlspecialchars($section['text']) . '</p>', $response['body'], "/basstabs/{$slug} writes out the {$section['name']}'s text");
				}
			}
		}
	},

	'the list and every song sheet ask search engines to leave them out' => function ($ctx) {
		$paths = ['/basstabs'];
		foreach (array_keys(bassTabSongs()) as $slug) {
			$paths[] = "/basstabs/{$slug}";
		}

		foreach ($paths as $path) {
			assertContains('<meta name="robots" content="noindex">', $ctx->get($path)['body'], "{$path} is noindex");
		}
	},

	'my favourite game has its riff on the right strings' => function ($ctx) {
		$body = $ctx->get('/basstabs/my-favourite-game')['body'];

		$frets = bassTabShapes($body, 'Riff', 'bassTabFret');
		assertSame('E8 E6 B6 B9 E6', bassTabFrets($frets), 'the riff reads back in order');

		$lineY = [];
		foreach (bassTabShapes($body, 'Riff', 'bassTabLine') as $line) {
			$lineY[$line['data-string']] = (float)$line['y1'];
		}
		assertSame(['G', 'D', 'A', 'E', 'B'], array_keys($lineY), 'five lines, G at the top');
		assertSame(max($lineY), $lineY['B'], 'the low B is the bottom line');

		foreach ($frets as $fret) {
			assertSame($lineY[$fret['data-string']], (float)$fret['y'], "{$fret['data-string']}{$fret['text']} sits on its line");
		}

		$names = bassTabShapes($body, 'Riff', 'bassTabNoteName');
		assertSame(['C', 'B♭', 'F', 'A♭', 'B♭'], array_column($names, 'text'), 'each note is named, in flats as the song asks');
		assertSame(array_column($frets, 'x'), array_column($names, 'x'), 'each name right under its fret');
		assertSame(1, count(array_unique(array_column($names, 'y'))), 'all on one row');
		assertTrue((float)$names[0]['y'] > $lineY['B'], 'below the bottom line');
	},

	'my favourite game has its chorus with repeats, a held note, a bracket and the drum fill' => function ($ctx) {
		$body = $ctx->get('/basstabs/my-favourite-game')['body'];

		$frets = bassTabShapes($body, 'Chorus', 'bassTabFret');
		assertSame('E8 B8 E6 E8 B9 E6 B8 E6 E8 B9 B6 E6 B8', bassTabFrets($frets), 'the chorus reads back in order');
		$x = array_map(fn($fret) => (float)$fret['x'], $frets);
		$gap = $x[4] - $x[3];

		$bars = array_map(fn($bar) => (float)$bar['x1'], bassTabShapes($body, 'Chorus', 'bassTabBar'));
		assertSame(2, count($bars), 'two bar lines');
		assertTrue($x[2] < $bars[0] && $bars[0] < $x[3], 'the first closes the first block');
		assertTrue($x[7] < $bars[1] && $bars[1] < $x[8], 'the second closes the second');

		$dots = array_map(fn($dot) => (float)$dot['cx'], bassTabShapes($body, 'Chorus', 'bassTabRepeatDot'));
		assertSame(4, count($dots), 'both bar lines are repeats');
		assertTrue($x[2] < $dots[0] && $dots[1] < $bars[0], 'with their dots before the first');
		assertTrue($x[7] < $dots[2] && $dots[3] < $bars[1], 'and before the second');

		$holds = bassTabShapes($body, 'Chorus', 'bassTabHold');
		assertSame(1, count($holds), 'one held note');
		assertSame($frets[0]['y'], $holds[0]['y1'], 'the hold runs along the E line');
		assertTrue($x[0] < (float)$holds[0]['x1'] && (float)$holds[0]['x2'] < $x[1], 'from the first E8 towards the B8');
		assertSame(2 * $gap, $x[1] - $x[0], 'the held note takes twice the room');
		assertSame($gap / 2, $x[7] - $x[6], 'the last two of the second block are half as far apart');

		$brackets = bassTabShapes($body, 'Chorus', 'bassTabBracket');
		assertSame(1, count($brackets), 'one bracket');
		$bracketX = array_map(fn($point) => (float)explode(',', $point)[0], explode(' ', $brackets[0]['points']));
		assertSame([$bars[0], $bars[1]], [min($bracketX), max($bracketX)], 'it spans the second block, bar line to bar line');

		$arrows = bassTabShapes($body, 'Chorus', 'bassTabArrow');
		assertSame(1, count($arrows), 'one arrow');
		assertSame($x[12], (float)$arrows[0]['x1'], 'under the last B8');

		$labels = array_column(bassTabShapes($body, 'Chorus', 'bassTabLabel'), 'text');
		assertSame(['Only 2nd chorus', 'Drum fill'], $labels, 'the bracket and the arrow say what they mean');
	},

	'my favourite game has its bridge and its outro' => function ($ctx) {
		$body = $ctx->get('/basstabs/my-favourite-game')['body'];

		$frets = bassTabShapes($body, 'Bridge', 'bassTabFret');
		assertSame(str_repeat('E8 E9 ', 6) . 'E8 B9 B6 E6 B8', bassTabFrets($frets), 'the bridge reads back in order');

		$x = array_map(fn($fret) => (float)$fret['x'], $frets);
		$within = $x[1] - $x[0];
		$between = $x[2] - $x[1];
		assertTrue($within < $between && $between < 1.5 * $within, 'each E8 E9 pair stands a little apart from the next');
		foreach (range(0, 10) as $i) {
			assertSame($i % 2 === 0 ? $within : $between, $x[$i + 1] - $x[$i], 'every pair and every space between them alike');
		}

		$bars = bassTabShapes($body, 'Bridge', 'bassTabBar');
		assertSame(1, count($bars), 'one bar line');
		assertSame(0, count(bassTabShapes($body, 'Bridge', 'bassTabRepeatDot')), 'and no repeat: the 6x says it');
		$barX = (float)$bars[0]['x1'];
		assertTrue((float)$frets[11]['x'] < $barX && $barX < (float)$frets[12]['x'], 'between the run and the last block');

		$brackets = bassTabShapes($body, 'Bridge', 'bassTabBracket');
		assertSame(1, count($brackets), 'one bracket');
		$bracketX = array_map(fn($point) => (float)explode(',', $point)[0], explode(' ', $brackets[0]['points']));
		assertTrue(min($bracketX) < (float)$frets[0]['x'] && max($bracketX) === $barX, 'under the whole run, up to the bar line');
		assertSame(['6x'], array_column(bassTabShapes($body, 'Bridge', 'bassTabLabel'), 'text'), 'labelled 6x');

		$names = bassTabShapes($body, 'Bridge', 'bassTabNoteName');
		assertSame(['C', 'D♭'], array_column(array_slice($names, 0, 2), 'text'), 'the pairs are C and D flat');
		assertTrue((float)$names[0]['y'] < min(array_map(fn($point) => (float)explode(',', $point)[1], explode(' ', $brackets[0]['points']))), 'with the bracket below the names, not over them');

		assertSame('B6 B9 E6 B6 B9 E6', bassTabFrets(bassTabShapes($body, 'Outro', 'bassTabFret')), 'the outro is the end of the riff twice');
		assertTrue(
			(bool)preg_match('/<h2[^>]*>Outro<\/h2>\s*<p class="bassTabSectionText">Riff 2x, then end of riff 2x<\/p>/', $body),
			'with what to play written under its name'
		);
	},

	'a sheet names its artist and key in the top left corner, across from its title' => function ($ctx) {
		$expected = [
			'my-favourite-game' => ['My Favourite Game', 'The Cardigans · C minor'],
			'amsterdam' => ['Amsterdam', 'Nothing but Thieves · D Phrygian'],
		];

		foreach ($expected as $slug => [$title, $subtitle]) {
			assertTrue(
				(bool)preg_match('/<div class="bassTabCorner">.*?<p class="bassTabSubtitle">' . preg_quote($subtitle, '/') . '<\/p>\s*<\/div>\s*<h1 class="bassTabTitle">' . preg_quote($title, '/') . '<\/h1>/s', $ctx->get("/basstabs/{$slug}")['body']),
				"{$title} carries '{$subtitle}' in the corner, across from its title"
			);
		}

		foreach (bassTabSongs() as $slug => $song) {
			if (isset($song['key'])) {
				bassTabKeyName($song['key']);
			}
		}
	},

	/// The band a bracket or arrow needs under a staff may rise beside the next heading, but only where nothing can collide.
	'a bracket band shares height with the next heading only where it cannot collide' => function ($ctx) {
		$bleeds = fn($body, $section) => (bool)preg_match(
			'/<h2[^>]*>' . preg_quote($section, '/') . '<\/h2>(?:(?!<\/section>).)*<div class="bassTabStaffClip bassTabBleed" style="--bleed: min\(30px, [\d.]+cqw\)">(?:(?!bassTabStaffClip).)*<\/section>/s',
			$body
		);

		$mfg = $ctx->get('/basstabs/my-favourite-game')['body'];
		assertTrue($bleeds($mfg, 'Chorus'), 'the chorus\'s bracket and arrow rise beside the bridge\'s heading, being well right of it');
		assertTrue(!$bleeds($mfg, 'Bridge'), 'the bridge\'s do not: the outro has a board, whose top edge is level with its heading');
		assertTrue(!$bleeds($mfg, 'Riff'), 'nor does a staff with no bracket or arrow');

		$tiers = bassTabStaves(bassTabParse('["x" E8 ] E8'), 'sharp')[0];
		assertSame(30, $tiers['under'], 'a bracket keeps 30 px under the names');
		assertTrue($tiers['labelLeft'] < 60, 'and knows where its label starts, here too far left to share a heading\'s height');

		$css = $ctx->get('/modules/basstabs/css/styles.css')['body'];
		assertTrue((bool)preg_match('/@container \(max-width: 560px\) \{.*?\.bassTabBleed\s*\{\s*margin-bottom:\s*0;/s', $css), 'and never on a narrow screen, where the staff shrinks and the heading does not');
	},

	'amsterdam reads back as its handwritten page' => function ($ctx) {
		$body = $ctx->get('/basstabs/amsterdam')['body'];

		$expected = [
			'Riff' => 'E6 A5 E6 A5 A8 A5 A5 A5 A5 A5 D5 D8 D5 A5 A8 A5 A5 A5 A8 D7',
			'End of verse 2' => 'E6 A5 E6 A5 A8 A5 B3 B3 B3 B3 D5 D8 D5 A5 A8 A5 B3 B3 B6 E5',
			'Chorus' => 'A6 A6 A6 B4 B4 B4 E1 E3 E3 E1 E3 E3 E3 E6 E6 E6 E8 E8',
			'Bridge' => 'E3 E5 E6 E8 E3 E5 E6 E8 E3 E5 E6 E8 D5 D7 D8 D10',
			'Quiet part' => 'B4 E3 E1',
		];
		foreach ($expected as $section => $frets) {
			assertSame($frets, bassTabFrets(bassTabShapes($body, $section, 'bassTabFret')), "the {$section} reads back in order");
		}

		assertSame(2, count(bassTabShapes($body, 'Chorus', 'bassTabRepeatDot')) / 2, 'the chorus\'s first two blocks repeat');
		assertSame(['A♯', 'D', 'A♯'], array_column(array_slice(bassTabShapes($body, 'Riff', 'bassTabNoteName'), 0, 3), 'text'), 'named in sharps, as the page writes them');

		$bridge = bassTabShapes($body, 'Bridge', 'bassTabFret');
		assertSame(3, count(bassTabShapes($body, 'Bridge', 'bassTabBar')), 'four blocks');
		assertSame(
			array_merge(array_fill(0, 8, 'dots'), array_fill(0, 4, 'tilde'), array_fill(0, 4, 'dots')),
			array_column($bridge, 'data-mark'),
			'eighths marked with two dots, the third block with tildes'
		);

		$dots = bassTabShapes($body, 'Bridge', 'bassTabMarkDot');
		$tildes = bassTabShapes($body, 'Bridge', 'bassTabMarkTilde');
		assertSame(24, count($dots), 'two dots over each of twelve notes');
		assertSame(4, count($tildes), 'a tilde over each of four');
		foreach (array_merge(array_slice($bridge, 0, 8), array_slice($bridge, 12)) as $index => $fret) {
			$pair = array_slice($dots, $index * 2, 2);
			assertSame((float)$fret['x'], ((float)$pair[0]['cx'] + (float)$pair[1]['cx']) / 2, 'the dots are centred over their digit');
			assertTrue((float)$pair[0]['cy'] < (float)$fret['y'], 'and above it');
		}

		$quiet = array_map(fn($fret) => (float)$fret['x'], bassTabShapes($body, 'Quiet part', 'bassTabFret'));
		assertSame(3, count(bassTabShapes($body, 'Quiet part', 'bassTabHold')), 'all three are held');
		assertSame(2 * ($quiet[2] - $quiet[1]), $quiet[1] - $quiet[0], 'the B4 twice as long as the others');

		$tiers = bassTabBoardTiers($body, 'Quiet part');
		assertTrue(
			(bool)preg_match('/<h2[^>]*>Quiet part<\/h2>.*?<text class="bassTabBoardKey"[^>]*>D<\/text>\s*<text class="bassTabBoardKey"[^>]*>Phrygian<\/text>.*?<\/section>/s', $body),
			'the quiet part has a D Phrygian board beside it'
		);
		assertSame(['A5'], array_keys($tiers, 'anchor'), 'anchored on A5, the riff\'s D');
		assertSame(['A5', 'A6', 'A8', 'D5', 'D7', 'D8', 'G5', 'G7'], bassTabBoardPosition($tiers), 'the Phrygian box from frets 5 to 8');
		assertTrue(isset($tiers['A6']) && !isset($tiers['A7']), 'with E flat in it, not E');
	},

	/// Shrinking each staff to fit on its own would print the riff's digits bigger than the chorus's.
	'every staff on a sheet shrinks to fit a narrow screen by the same factor' => function ($ctx) {
		$body = $ctx->get('/basstabs/my-favourite-game')['body'];

		/// The key's fretboard is not a staff and shrinks by itself.
		preg_match_all('/<svg\b[^>]*\bclass="[^"]*\bbassTabStaff\b[^"]*"[^>]*\bstyle="width: min\(([\d.]+)px, ([\d.]+)cqw\)"/', $body, $matches, PREG_SET_ORDER);
		assertSame(count(bassTabSong('my-favourite-game')['sections']), count($matches), 'every staff carries a width that can shrink');

		$factors = array_map(fn($match) => round((float)$match[2] / (float)$match[1], 4), $matches);
		assertSame(1, count(array_unique($factors)), 'one factor for the whole sheet');
		assertSame(100.0, max(array_map(fn($match) => (float)$match[2], $matches)), 'the widest staff takes the whole width of the sheet');
	},

	/// The router 301s any other path to its lowercase, so such a sheet could never be reached.
	'song file names are lowercase kebab case' => function ($ctx) {
		foreach (array_keys(bassTabSongs()) as $slug) {
			assertTrue((bool)preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $slug), "'{$slug}' is a reachable address");
		}
	},

	'the notation reads notes, bar lines, brackets and labels' => function ($ctx) {
		$notes = array_map(fn($item) => $item['string'] . $item['fret'], bassTabParse(" E8  B0\nG24 "));
		assertSame(['E8', 'B0', 'G24'], $notes, 'notes split on any whitespace');

		$items = bassTabParse('|: E8- B8/2 ["Six times over" E6^"Drum fill" ] :| |');
		assertSame(
			['bar', 'note', 'note', 'open', 'note', 'close', 'bar', 'bar'],
			array_column($items, 'kind'),
			'every token is one item'
		);
		assertSame(['note', 'gap', 'note'], array_column(bassTabParse('E8 , E9'), 'kind'), 'a comma on its own is a gap');
		assertSame(['start', 'end', null], [$items[0]['repeat'], $items[6]['repeat'], $items[7]['repeat']], 'the three bar lines');
		assertSame([2, 0.5, 1], [$items[1]['length'], $items[2]['length'], $items[4]['length']], 'held, half and plain lengths');
		assertSame('Six times over', $items[3]['label'], 'a bracket label keeps its spaces');
		assertSame('Drum fill', $items[4]['arrow'], 'and so does an arrow label');
		assertSame('', bassTabParse('[ E8 ]')[0]['label'], 'a bracket needs no label');
	},

	'an h is a hammer-on between two notes, and a slash starts a new line' => function ($ctx) {
		assertSame(['note', 'hammer', 'note'], array_column(bassTabParse('A5 h A7'), 'kind'), 'a hammer-on between its two notes');
		assertSame(['note', 'break', 'note'], array_column(bassTabParse('A5 / A7'), 'kind'), 'a line break between two notes');

		$staves = bassTabStaves(bassTabParse('E0 E2 h E3 | / A5 h A7 [ A3 ]'), 'sharp');
		assertSame(2, count($staves), 'the break makes two staves');
		assertSame([3, 3], array_map(fn($staff) => count($staff['notes']), $staves), 'each with its own notes');
		assertSame([1, 1], array_map(fn($staff) => count($staff['hammers']), $staves), 'and its own hammer-on');

		foreach (['h A5', 'A5 h', 'A5 h E7', 'A7 h A5', 'A5 h A5', 'A5 h | A7', 'A5 h , A7', 'r h A5', 'A5 h r', '/ A5', 'A5 /', 'A5 / / A7', '[ A5 / A7 ]'] as $bad) {
			$refused = false;
			try {
				bassTabParse($bad);
			}
			catch (InvalidArgumentException $e) {
				$refused = true;
			}
			assertTrue($refused, "'{$bad}' is refused");
		}
	},

	'an s is a slide between two notes on one string, a slash rising or falling with it' => function ($ctx) {
		assertSame(['note', 'slide', 'note'], array_column(bassTabParse('A5 s A7'), 'kind'), 'a slide between its two notes');

		$staff = bassTabStaves(bassTabParse('D5 s D7 s D5'), 'sharp')[0];
		[$up, $down] = $staff['slides'];
		assertSame(['up', 'down'], [$up['direction'], $down['direction']], 'up to a higher fret, down to a lower one');
		assertTrue($up['y1'] > $up['y2'] && $down['y1'] < $down['y2'], 'the slash rises left to right going up and falls going down');
		[$first, $second] = $staff['notes'];
		assertTrue($first['x'] < $up['x1'] && $up['x2'] < $second['x'], 'between the two digits');
		assertSame(30 * 3 + 38, (int)$staff['width'], 'taking no room of its own');

		foreach (['s A5', 'A5 s', 'A5 s E7', 'A5 s A5', 'A5 s | A7', 'A5 s , A7', 'r s A5', 'A5 s r', 'A5 s h A7'] as $bad) {
			$refused = false;
			try {
				bassTabParse($bad);
			}
			catch (InvalidArgumentException $e) {
				$refused = true;
			}
			assertTrue($refused, "'{$bad}' is refused");
		}
	},

	'a section marked beside shares the line of the one before it, which only a section without a board can' => function ($ctx) {
		$section = fn($fields = []) => $fields + ['name' => '', 'text' => '', 'lines' => [], 'staves' => [['width' => 100]], 'board' => null, 'beside' => false];
		$board = ['width' => 482];

		$blocks = bassTabBlocks([$section(), $section(['beside' => true]), $section(['beside' => true]), $section()]);
		assertSame(['row', 'section'], array_column($blocks, 'kind'), 'one row, then a section on a line of its own');
		assertSame(3, count($blocks[0]['sections']), 'with three side by side');

		foreach ([
			'on the first section' => [$section(['beside' => true])],
			'after a board on its own' => [$section(), $section(['staves' => [], 'board' => $board]), $section(['beside' => true])],
			'beside a section with a board' => [$section(['board' => $board]), $section(['beside' => true])],
			'with a board of its own' => [$section(), $section(['beside' => true, 'board' => $board])],
			'on a section that is only a board' => [$section(), $section(['beside' => true, 'staves' => [], 'board' => $board])],
		] as $what => $sections) {
			$refused = false;
			try {
				bassTabBlocks($sections);
			}
			catch (InvalidArgumentException $e) {
				$refused = true;
			}
			assertTrue($refused, "beside is refused {$what}");
		}
	},

	'creep\'s outro sits beside its main riff under a heading of its own, sliding up into it' => function ($ctx) {
		$body = $ctx->get('/basstabs/creep')['body'];

		assertTrue((bool)preg_match('/<div class="bassTabRow">(.*?)<\/section>\s*<\/div>/s', $body, $row), 'Creep has a row of sections');
		preg_match_all('/<h2[^>]*>([^<]*)<\/h2>/', $row[1], $headings);
		assertSame(['Main', 'Outro'], $headings[1], 'Main and Outro share it, each under its own heading');
		assertSame([], bassTabShapes($body, 'Main', 'bassTabBracket'), 'the outro is no longer a bracket under the main riff');

		assertSame('D5 D7 D7 D9', bassTabFrets(bassTabShapes($body, 'Outro', 'bassTabFret')), 'the outro reads back');
		assertSame(['up'], array_column(bassTabShapes($body, 'Outro', 'bassTabSlide'), 'data-slide'), 'one slide, up');
		assertSame(1, count(bassTabShapes($body, 'Outro', 'bassTabHammer')), 'and one hammer-on');
	},

	/// The transcription is the owner's and still changing, so this pins what the code draws, not the notes.
	'bring me to life draws its hammer-ons and its dotted notes' => function ($ctx) {
		$body = $ctx->get('/basstabs/bring-me-to-life')['body'];

		$frets = bassTabShapes($body, 'Main riff', 'bassTabFret');
		$arcs = bassTabShapes($body, 'Main riff', 'bassTabHammer');
		assertTrue(count($arcs) > 0, 'the main riff has a hammer-on');
		assertSame(count($arcs), count(bassTabShapes($body, 'Main riff', 'bassTabHammerLabel')), 'each one marked h');

		foreach ($arcs as $arc) {
			preg_match('/^M([\d.]+),[\d.]+ Q[\d.]+,[\d.]+ ([\d.]+),/', $arc['d'], $ends);
			$before = array_values(array_filter($frets, fn($fret) => (float)$fret['x'] < (float)$ends[1]));
			$after = array_values(array_filter($frets, fn($fret) => (float)$fret['x'] > (float)$ends[2]));
			[$from, $to] = [end($before), $after[0]];
			assertSame($from['data-string'], $to['data-string'], 'a hammer-on arcs between two notes on one string');
			assertTrue((int)$to['text'] > (int)$from['text'], 'going up it');
		}

		/// A dot on the page is written -/2: held, so a line, but only a stub of one.
		$stubs = array_filter(
			bassTabShapes($body, 'Bridge', 'bassTabHold'),
			fn($hold) => (float)$hold['x2'] - (float)$hold['x1'] < 10
		);
		assertTrue(count($stubs) > 0, 'the bridge\'s dotted notes are short holds');

		assertContains('<p class="bassTabSubtitle">Evanescence · E minor</p>', $body, 'by Evanescence, in E minor');
	},

	'love myself has four chord boards with no staff of their own, one above another' => function ($ctx) {
		$body = $ctx->get('/basstabs/love-myself')['body'];

		assertContains('<p class="bassTabSubtitle">Rondé · C♯ major</p>', $body, 'by Rondé, in C sharp major');
		assertTrue((bool)preg_match('/<div class="bassTabBoards">(.*?)<\/div>\s*<\/main>/s', $body, $block), 'the boards share one block, after the riff');
		assertTrue(strpos($block[1], '<h2') === false && strpos($block[1], 'bassTabStaff') === false, 'with no names and no staves');

		$expected = [
			'D♯ minor' => ['A6', ['A6', 'A8', 'A9', 'D6', 'D8', 'D9', 'G6', 'G8']],
			'G♯ major' => ['E4', ['A3', 'A4', 'A6', 'D3', 'D5', 'D6', 'E4', 'E6']],
			'C♯ major' => ['A4', ['A4', 'A6', 'D3', 'D4', 'D6', 'G3', 'G5', 'G6']],
			'F♯ major' => ['E2', ['A1', 'A2', 'A4', 'D1', 'D3', 'D4', 'E2', 'E4']],
		];
		$boards = array_slice(explode('<section class="bassTabBoardOnly">', $block[1]), 1);
		assertSame(count($expected), count($boards), 'one board per chord');

		foreach (array_keys($expected) as $index => $key) {
			[$anchor, $position] = $expected[$key];
			[$root, $scale] = explode(' ', $key);
			assertTrue((bool)preg_match('/>' . preg_quote($root, '/') . '<\/text>\s*<text class="bassTabBoardKey"[^>]*>' . $scale . '<\/text>/', $boards[$index]), "board " . ($index + 1) . " is {$key}");

			preg_match_all('/class="bassTabBoardDot bassTabBoard(Position|Anchor)[^"]*" data-string="(\w)" data-fret="(\d+)"/', $boards[$index], $marked, PREG_SET_ORDER);
			$found = array_map(fn($dot) => $dot[2] . $dot[3], $marked);
			sort($found);
			assertSame($position, $found, "{$key}'s hand position");
			assertSame([$anchor], array_values(array_map(fn($dot) => $dot[2] . $dot[3], array_filter($marked, fn($dot) => $dot[1] === 'Anchor'))), "anchored on {$anchor}");
		}

		$css = $ctx->get('/modules/basstabs/css/styles.css')['body'];
		assertTrue((bool)preg_match('/\.bassTabBoards\s*\{[^}]*grid-template-columns:\s*minmax\(0, 1fr\);/', $css), 'one to a row, at full size');
	},

	/// The notes are the owner's and still changing, so this pins the shape of the sheet, not the notes.
	'monday morning has three parts, fuzz under two, and its word list' => function ($ctx) {
		$body = $ctx->get('/basstabs/monday-morning')['body'];

		assertContains('<p class="bassTabSubtitle">Melanie Fiona · B♭ minor</p>', $body, 'by Melanie Fiona, in B flat minor');

		foreach (['Verse' => [], 'Pre-chorus' => ['Fuzz'], 'Chorus' => ['Fuzz']] as $section => $labels) {
			assertTrue(count(bassTabShapes($body, $section, 'bassTabFret')) > 0, "the {$section} has notes");
			assertSame($labels, array_column(bassTabShapes($body, $section, 'bassTabLabel'), 'text'), "the {$section}'s bracket");
		}
		assertSame('B♭', bassTabShapes($body, 'Verse', 'bassTabNoteName')[0]['text'], 'named in flats');

		assertTrue((bool)preg_match('/<ul class="bassTabLines">(.*?)<\/ul>/s', $body, $list), 'the words are a list');
		assertSame(8, preg_match_all('/<li>[^<]*<\/li>/', $list[1]), 'of eight cues');
		assertTrue(
			!preg_match('/<h2[^>]*>Chorus<\/h2>(?:(?!<\/section>).)*bassTabBleed/s', $body),
			'and the chorus\'s Fuzz keeps its room, the list after it having no heading to rise beside'
		);
	},

	/// The notes are the owner's and still changing, so this pins what the sheet draws, not the notes.
	'inside draws its dots, its rests and its verse chords\' boards' => function ($ctx) {
		$body = $ctx->get('/basstabs/inside')['body'];

		assertContains('<p class="bassTabSubtitle">Stiltskin · E minor</p>', $body, 'by Stiltskin, in E minor');

		foreach (['Intro / bridge / riff', 'Verse / solo'] as $section) {
			$frets = bassTabShapes($body, $section, 'bassTabFret');
			assertSame(array_fill(0, count($frets), 'dot'), array_column($frets, 'data-mark'), "every {$section} note has a dot over it");
			assertSame(count($frets), count(bassTabShapes($body, $section, 'bassTabMarkDot')), 'one dot each');
		}

		$frets = array_map(fn($fret) => (float)$fret['x'], bassTabShapes($body, 'Chorus', 'bassTabFret'));
		$rests = bassTabShapes($body, 'Chorus', 'bassTabRest');
		assertTrue(count($rests) > 0, 'the chorus has rests');
		foreach ($rests as $rest) {
			/// A quarter rest's path starts 2 px left of its beat, an eighth rest's 3.
			$x = (float)preg_replace('/^M([\d.]+),.*$/', '$1', $rest['d']) + (str_contains($rest['d'], 'l4,6') ? 2 : 3);
			$nearest = min(array_map(fn($fret) => abs($fret - $x), $frets));
			assertTrue($nearest >= 15, 'each rest takes room of its own, a beat or half of one');
		}

		assertTrue(strpos($body, '>Fill!!</h2>') < strpos($body, '>Verse / solo</h2>'), 'the fill comes before the verse');

		/// The verse's chords, Em C A: Em beside it, C and A under it in the same column.
		$verse = bassTabBoardTiers($body, 'Verse / solo');
		assertSame(['E0'], array_keys($verse, 'anchor'), 'E minor beside the verse, from the open E');
		assertSame(['A0', 'A2', 'A3', 'D0', 'D2', 'E0', 'E2', 'E3'], bassTabBoardPosition($verse), 'in the open minor box');

		assertTrue((bool)preg_match('/<div class="bassTabBoards bassTabBoardsRight">(.*?)<\/div>/s', $body, $pair), 'the other two line up under the E minor board');
		$expected = ['C major' => ['A3', ['A3', 'A5', 'D2', 'D3', 'D5', 'G2', 'G4', 'G5']], 'A major' => ['A0', ['A0', 'A2', 'A4', 'D0', 'D2', 'D4', 'G1', 'G2']]];
		$boards = array_slice(explode('<section class="bassTabBoardOnly">', $pair[1]), 1);
		assertSame(2, count($boards), 'two of them');
		foreach (array_keys($expected) as $index => $key) {
			[$anchor, $position] = $expected[$key];
			[$root, $scale] = explode(' ', $key);
			assertTrue((bool)preg_match('/>' . preg_quote($root, '/') . '<\/text>\s*<text class="bassTabBoardKey"[^>]*>' . $scale . '<\/text>/', $boards[$index]), "the " . ($index ? 'second' : 'first') . " is {$key}");
			preg_match_all('/class="bassTabBoardDot bassTabBoard(Position|Anchor)[^"]*" data-string="(\w)" data-fret="(\d+)"/', $boards[$index], $marked, PREG_SET_ORDER);
			$found = array_map(fn($dot) => $dot[2] . $dot[3], $marked);
			sort($found);
			assertSame($position, $found, "{$key}'s hand position, anchored on {$anchor}");
		}
	},

	'boards under a section with a board line up with it on the right' => function ($ctx) {
		assertTrue(strpos($ctx->get('/basstabs/love-myself')['body'], 'bassTabBoardsRight') === false, 'Love Myself\'s riff has no board, so its boards start at the left');

		$css = $ctx->get('/modules/basstabs/css/styles.css')['body'];
		assertTrue((bool)preg_match('/\.bassTabBoardsRight\s*\{[^}]*justify-items:\s*end;/', $css), 'right-aligned, in the column of the board above');
	},

	'a dash holds a note, each one doubling it, and a mark goes over it' => function ($ctx) {
		assertSame(
			[1, 2, 4, 8, 0.5],
			array_column(bassTabParse('E8 E8- E8-- E8--- B8/2'), 'length'),
			'plain, held, held twice as long, and again, and half'
		);
		$halved = bassTabParse('E8-/2 E8--/2 E8 E8/2');
		assertSame([1, 2, 1, 0.5], array_column($halved, 'length'), 'a /2 after the dashes halves them');
		assertSame([true, true, false, false], array_column($halved, 'held'), 'and the note is still held, though no longer than a plain one');
		assertSame(
			[null, 'dots', 'dot', 'tilde'],
			array_column(bassTabParse('E3 E3: E3. E3~'), 'mark'),
			'two dots, one dot or a tilde over the digit'
		);
		[$dotted] = bassTabParse('A5-.^"x"');
		assertSame([2, 'dot', 'x'], [$dotted['length'], $dotted['mark'], $dotted['arrow']], 'a dot goes where the other marks do');

		assertSame(['note', 'rest', 'note'], array_column(bassTabParse('A5 r A7'), 'kind'), 'an r on its own is a rest');
		$staff = bassTabStaves(bassTabParse('A5 r A7'), 'sharp')[0];
		assertSame(60, $staff['notes'][1]['x'] - $staff['notes'][0]['x'], 'taking a beat of room');
		assertSame(1, count($staff['rests']), 'drawn once');
		assertSame(null, $staff['rests'][0]['dot'], 'as a quarter rest, with no dot');
		assertSame(['bar', 'open', 'rest', 'close'], array_column(bassTabParse('| [ r ]'), 'kind'), 'and a bracket may hold one');

		$eighth = bassTabStaves(bassTabParse('A5 r/2 A7'), 'sharp')[0];
		assertSame(45.0, $eighth['notes'][1]['x'] - $eighth['notes'][0]['x'], 'r/2 is an eighth rest: half a beat of room');
		assertTrue($eighth['rests'][0]['dot'] !== null, 'drawn with the dot of an eighth rest, the 7 on the page');

		[$all] = bassTabParse('E8-:^"x"');
		assertSame([2, 'dots', 'x'], [$all['length'], $all['mark'], $all['arrow']], 'length, then mark, then arrow');
	},

	'a fret is named by its string and the semitones above it' => function ($ctx) {
		foreach (['B' => 'B', 'E' => 'E', 'A' => 'A', 'D' => 'D', 'G' => 'G'] as $string => $open) {
			assertSame($open, bassTabNoteName($string, 0, 'sharp'), "the open {$string} string");
		}

		assertSame('C', bassTabNoteName('E', 8, 'sharp'), 'E8 is a C');
		assertSame('C', bassTabNoteName('B', 1, 'flat'), 'B1 goes round past B to C');
		assertSame('G', bassTabNoteName('G', 24, 'flat'), 'two octaves up is the same note');
		assertSame(['A♯', 'B♭'], [bassTabNoteName('A', 1, 'sharp'), bassTabNoteName('A', 1, 'flat')], 'a black key is spelt as the song asks');

		$refused = false;
		try {
			bassTabNoteName('E', 8, 'natural');
		}
		catch (InvalidArgumentException $e) {
			$refused = strpos($e->getMessage(), 'natural') !== false;
		}
		assertTrue($refused, 'a song asks for sharps or flats and nothing else');
	},

	'my favourite game shows c minor on a fretboard beside the riff' => function ($ctx) {
		$body = $ctx->get('/basstabs/my-favourite-game')['body'];

		assertTrue(
			(bool)preg_match('/<section class="[^"]*\bbassTabWithBoard\b[^"]*">\s*<div class="bassTabSectionMain">\s*<h2[^>]*>Riff<\/h2>.*?<svg class="bassTabBoard"[^>]*>\s*<rect class="bassTabBoardFrame"[^>]*\/>.*?<text class="bassTabBoardKey"[^>]*>C<\/text>\s*<text class="bassTabBoardKey"[^>]*>minor<\/text>.*?<\/svg>.*?<\/section>/s', $body),
			'the riff\'s section holds the C minor board beside its staff, the key named inside its frame'
		);
		foreach (['Chorus', 'Bridge'] as $name) {
			assertSame([], bassTabBoardTiers($body, $name), "the {$name} has no board");
		}

		$tiers = bassTabBoardTiers($body, 'Riff');
		assertSame(37, count($tiers), 'seven notes on each string, and the octave of the open G and D at fret 12');
		$ends = array_keys(array_filter($tiers, fn($note) => in_array(substr($note, 1), ['0', '12'], true), ARRAY_FILTER_USE_KEY));
		sort($ends);
		assertSame(['D0', 'D12', 'G0', 'G12'], $ends, 'an open string in the key comes round again at 12');

		/// A cell is shaded where a bass has a dot on its board.
		$shaded = [];
		$cells = bassTabShapes($body, 'Riff', 'bassTabBoardInlay');
		foreach (bassTabShapes($body, 'Riff', 'bassTabFretNumber') as $number) {
			foreach ($cells as $cell) {
				if ((float)$cell['x'] < (float)$number['x'] && (float)$number['x'] < (float)$cell['x'] + (float)$cell['width']) {
					$shaded[] = (int)$number['text'];
				}
			}
		}
		assertSame([3, 5, 7, 9, 12], $shaded, 'the inlay frets are shaded');

		$names = array_column(bassTabShapes($body, 'Riff', 'bassTabBoardName'), 'text');
		assertSame([], array_values(array_diff($names, ['C', 'D', 'E♭', 'F', 'G', 'A♭', 'B♭'])), 'every one of them in C minor');
		assertSame(7, count(array_unique($names)), 'and all seven of them there');

		assertTrue(!isset($tiers['E9']), 'the bridge\'s D flat is not in the key');
		assertSame(['E8'], array_keys($tiers, 'anchor'), 'E8 is the anchor');
		assertSame(['A10', 'A11', 'A8', 'D10', 'D8', 'E10', 'E11', 'E8'], bassTabBoardPosition($tiers), 'the minor box from frets 8 to 11');

		$nut = bassTabShapes($body, 'Riff', 'bassTabNut');
		$open = array_values(array_filter(bassTabShapes($body, 'Riff', 'bassTabBoardDot'), fn($dot) => ($dot['data-fret'] ?? null) === '0'));
		assertTrue(count($open) > 0 && (float)$open[0]['cx'] < (float)$nut[0]['x1'], 'an open string is drawn left of the nut');

		/// A circle and its name come out in pairs, in the same order.
		$onDark = array_map(
			fn($name) => in_array('bassTabBoardNameOnDark', preg_split('/\s+/', $name['class']), true),
			bassTabShapes($body, 'Riff', 'bassTabBoardName')
		);
		assertSame(
			array_map(fn($tier) => $tier !== 'scale', array_values($tiers)),
			$onDark,
			'the names on the hand position and its anchor are white, the rest are ink'
		);

		assertTrue(strpos($body, 'bassTabLegend') === false, 'and there is no legend');
	},

	'my favourite game shows f major on a fretboard beside the outro' => function ($ctx) {
		$body = $ctx->get('/basstabs/my-favourite-game')['body'];

		assertTrue(
			(bool)preg_match('/<h2[^>]*>Outro<\/h2>.*?<svg class="bassTabBoard"[^>]*>\s*<rect class="bassTabBoardFrame"[^>]*\/>.*?<text class="bassTabBoardKey"[^>]*>F<\/text>\s*<text class="bassTabBoardKey"[^>]*>major<\/text>.*?<\/svg>.*?<\/section>/s', $body),
			'the outro\'s section holds the F major board, the key named inside its frame'
		);

		$tiers = bassTabBoardTiers($body, 'Outro');
		assertSame(39, count($tiers), 'seven notes on each string, and the open G, D, A and E again at 12');

		$names = array_column(bassTabShapes($body, 'Outro', 'bassTabBoardName'), 'text');
		assertSame([], array_values(array_diff($names, ['F', 'G', 'A', 'B♭', 'C', 'D', 'E'])), 'every one of them in F major');
		assertTrue(!isset($tiers['E4']) && !isset($tiers['B9']), 'no A flat: that is C minor\'s');

		assertSame(['B6'], array_keys($tiers, 'anchor'), 'B6, the riff\'s F, is the anchor');
		assertSame(['A5', 'A7', 'A8', 'B6', 'B8', 'E5', 'E6', 'E8'], bassTabBoardPosition($tiers), 'the major shape from frets 5 to 8');
	},

	'a key is the pitches of its scale' => function ($ctx) {
		assertSame([0, 2, 3, 5, 7, 8, 10], bassTabKey('C minor'), 'C natural minor');
		assertSame([9, 11, 1, 2, 4, 6, 8], bassTabKey('A major'), 'A major goes round past B');
		assertSame(bassTabKey('A# minor'), bassTabKey('Bb minor'), 'a sharp and a flat can name the same key');
		assertSame(bassTabKey('Bb minor'), bassTabKey('B♭ minor'), 'and so can the sign');
		assertSame([2, 3, 5, 7, 9, 10, 0], bassTabKey('D Phrygian'), 'D Phrygian: minor with a lowered second');

		assertSame(['B♭ minor', 'F♯ major', 'D Phrygian'], array_map('bassTabKeyName', ['Bb minor', 'F# major', 'D Phrygian']), 'a key is printed with real signs');
		assertSame(['D Phrygian', 'C minor'], array_map('bassTabKeyName', ['D phrygian', 'C Minor']), 'and its scale spelt one way, whatever case it was typed in');

		foreach (['H minor', 'C dorian', 'C', 'c minor', 'C  minor'] as $bad) {
			$refused = false;
			try {
				bassTabKey($bad);
			}
			catch (InvalidArgumentException $e) {
				$refused = true;
			}
			assertTrue($refused, "'{$bad}' is refused");
		}
	},

	'a board marks every note of its own triad, fingered or not' => function ($ctx) {
		/// Marked exactly where the note's name is one of the triad's, over the whole board.
		$marksTriad = function ($board, $triad) {
			foreach ($board['dots'] as $dot) {
				if ($dot['chord'] !== in_array($dot['name'], $triad, true)) {
					return false;
				}
			}
			return true;
		};

		$cMinor = bassTabFretboard('C minor', 'E8', 'E8 E10 E11 A8 A10 A11 D8 D10', 'flat');
		assertTrue($marksTriad($cMinor, ['C', 'E♭', 'G']), 'C minor: C, E flat and G');
		assertTrue($marksTriad(bassTabFretboard('F major', 'B6', 'B6 B8 E5 E6 E8 A5 A7 A8', 'flat'), ['F', 'A', 'C']), 'F major: F, A and C');
		assertTrue($marksTriad(bassTabFretboard('D Phrygian', 'A5', 'A5 A6 A8 D5 D7 D8 G5 G7', 'sharp'), ['D', 'F', 'A']), 'D Phrygian: the minor triad D, F and A');

		$tiers = array_unique(array_column(array_filter($cMinor['dots'], fn($dot) => $dot['chord']), 'tier'));
		sort($tiers);
		assertSame(['anchor', 'position', 'scale'], $tiers, 'in the hand position and outside it alike');

		$body = $ctx->get('/basstabs/inside')['body'];
		foreach (['E minor' => ['E', 'G', 'B'], 'C major' => ['C', 'E', 'G'], 'A major' => ['A', 'C♯', 'E']] as $key => $triad) {
			assertTrue((bool)preg_match('/<svg class="bassTabBoard"[^>]*aria-label="' . $key . ' [^"]*">(.*?)<\/svg>/s', $body, $svg), "Inside's {$key} board");
			preg_match_all('/<circle class="([^"]*)"[^>]*\/>\s*<text class="bassTabBoardName[^"]*"[^>]*>([^<]*)<\/text>/', $svg[1], $dots, PREG_SET_ORDER);
			assertTrue(count($dots) > 30, "{$key}'s dots read back");
			foreach ($dots as [, $classes, $name]) {
				$marked = in_array('bassTabBoardChord', preg_split('/\s+/', $classes), true);
				assertSame(in_array($name, $triad, true), $marked, "{$key}'s {$name} is " . ($marked ? '' : 'not ') . 'marked');
			}
		}

		$css = $ctx->get('/modules/basstabs/css/styles.css')['body'];
		assertTrue((bool)preg_match('/\.bassTabBoardDot\.bassTabBoardChord\s*\{[^}]*stroke-width:\s*3px;/', $css), 'with a heavier border');
		assertTrue((bool)preg_match('/\.bassTabBoardDot\.bassTabBoardScale\.bassTabBoardChord\s*\{[^}]*stroke-width:\s*2\.5px;/', $css), 'and a lighter one outside the hand position');
	},

	'a board can spell its notes its own way, whatever the sheet uses' => function ($ctx) {
		$body = $ctx->get('/basstabs/creep')['body'];

		$names = function ($key) use ($body) {
			preg_match('/<svg class="bassTabBoard"[^>]*aria-label="' . $key . ' [^"]*">(.*?)<\/svg>/s', $body, $svg);
			preg_match_all('/<text class="bassTabBoardName[^"]*"[^>]*>([^<]*)<\/text>/', $svg[1] ?? '', $found);
			return array_unique($found[1]);
		};

		$cMinor = $names('C minor');
		assertTrue(in_array('E♭', $cMinor, true) && !array_filter($cMinor, fn($name) => str_contains($name, '♯')), 'Creep\'s C minor board is in flats');
		assertTrue(in_array('F♯', $names('G major'), true), 'while the sheet around it, and its G major board, are in sharps');
	},

	'a board names its key in a column on its left, root over scale, one width for the whole sheet' => function ($ctx) {
		$board = bassTabFretboard('C minor', 'E8', 'E8 E10', 'flat', 60);
		assertSame([[$board['stringY']['G'], 'C'], [$board['stringY']['D'], 'minor']], $board['keyLines'], 'the root level with the G string, the scale with the D');
		assertSame([60, 76], [$board['nameX'], $board['lineStart']], 'the string letters start at the column\'s width, the strings after them');
		assertSame(bassTabFretboard('C minor', 'E8', 'E8 E10', 'flat', 0)['width'] + 60, $board['width'], 'the column widens the board by its own width');
		assertSame($board['top'], $board['stringY']['G'], 'and nothing stands above the top string');

		assertTrue(bassTabKeyColumn(['D phrygian']) > bassTabKeyColumn(['C minor']), 'a longer scale name takes a wider column');
		assertSame(bassTabKeyColumn(['D phrygian']), bassTabKeyColumn(['C minor', 'D phrygian']), 'and the widest key on a sheet sets it');

		$body = $ctx->get('/basstabs/inside')['body'];
		preg_match_all('/<line class="bassTabNut" x1="([^"]*)"/', $body, $nuts);
		assertSame(3, count($nuts[1]), 'Inside has three boards');
		assertSame(1, count(array_unique($nuts[1])), 'every one with its nut in the same place');
	},

	'a hand position has to be plain notes in the key and on the board' => function ($ctx) {
		assertSame(37, count(bassTabFretboard('C minor', 'E8', 'E8 E10 E11 A8 A10 A11 D8 D10', 'flat')['dots']), 'the song\'s own position is taken');
		assertSame(37, count(bassTabFretboard('C minor', 'E8', 'E8 D12', 'flat')['dots']), 'fret 12 is on the board');

		foreach ([['E8', 'E8 E9'], ['E8', 'E8 E13'], ['E8', 'E8 | A8'], ['E8', 'E8 A8-'], ['E8', 'E8 A8~'], ['E8', 'E8 A8:'], ['A10', 'E8 A8']] as [$anchor, $position]) {
			$refused = false;
			try {
				bassTabFretboard('C minor', $anchor, $position, 'flat');
			}
			catch (InvalidArgumentException $e) {
				$refused = true;
			}
			assertTrue($refused, "'{$position}' anchored on {$anchor} is refused");
		}
	},

	'the notation refuses anything it cannot read' => function ($ctx) {
		foreach (['X3', 'e8', 'E25', 'E08', 'E', '8', 'E8,', 'E-1', 'E8~~', 'E8/3', 'E8^', 'E8^"x', '"x"', '||', ':|:', ',,', 'E8----', 'E8/2-', 'E8::', 'E8:~', 'E8~-', 'A5..', 'A5.:', 'A5.-', 'rr', 'r/3', 'r-', 'r.'] as $bad) {
			$refused = false;
			try {
				bassTabParse("E8 {$bad}");
			}
			catch (InvalidArgumentException $e) {
				$refused = strpos($e->getMessage(), $bad) !== false;
			}
			assertTrue($refused, "'{$bad}' is refused by name");
		}

		foreach (['E8 ]', '[ E8', '[ [ E8 ] ]', 'E8 [ ]', '["Only" ]', 'E8 [ , ]'] as $bad) {
			$refused = false;
			try {
				bassTabParse($bad);
			}
			catch (InvalidArgumentException $e) {
				$refused = true;
			}
			assertTrue($refused, "'{$bad}' is refused");
		}
	},

	// CSS has no line comments: the parser swallows the rule after one, silently.
	/// The owner prints in greyscale, where a colour is only as good as its lightness.
	'the fretboard is drawn in greys only' => function ($ctx) {
		$css = $ctx->get('/modules/basstabs/css/styles.css')['body'];

		preg_match_all('/--(board\w+):\s*#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})\b/i', $css, $matches, PREG_SET_ORDER);
		assertTrue(count($matches) >= 4, 'the board takes its shades from its own properties');
		foreach ($matches as [, $name, $red, $green, $blue]) {
			assertTrue(strcasecmp($red, $green) === 0 && strcasecmp($green, $blue) === 0, "--{$name} is a grey");
		}

		assertTrue(
			(bool)preg_match('/\.bassTabBoard\w*\s*\{[^}]*var\(--link\)/', $css) === false,
			'and no board rule borrows the link blue'
		);
		assertTrue(
			(bool)preg_match('/\.bassTabBoardFrame\s*\{[^}]*stroke:\s*var\(--boardOutline\)/', $css),
			'the board is outlined in one of those greys'
		);
	},

	/// The board is meant to look laid on top of the staff, not set beside it. The suite runs no CSS,
	/// so this pins the three things the look rests on rather than the look.
	'the lines of a staff with a board run on underneath it' => function ($ctx) {
		$css = $ctx->get('/modules/basstabs/css/styles.css')['body'];

		assertTrue((bool)preg_match('/\.bassTabWithBoard \.bassTabStaffClip\s*\{[^}]*overflow:\s*visible/', $css), 'the staff beside a board is not cut off at its column');
		assertTrue((bool)preg_match('/\.bassTabWithBoard\s*\{[^}]*overflow:\s*hidden/', $css), 'but at the edge of its section');
		assertTrue((bool)preg_match('/\.bassTabBoardFrame\s*\{[^}]*fill:\s*var\(--paper\)/', $css), 'and the board\'s frame is filled, hiding the lines behind it');

		$body = $ctx->get('/basstabs/my-favourite-game')['body'];
		preg_match_all('/<svg class="bassTabBoard"[^>]*>\s*<(\w+) class="([^"]*)"/', $body, $first, PREG_SET_ORDER);
		assertSame(2, count($first), 'both boards found');
		foreach ($first as $match) {
			assertSame(['rect', 'bassTabBoardFrame'], [$match[1], $match[2]], 'the frame is drawn first, under everything else on the board');
		}
	},

	'the bass tab stylesheet carries no line comments' => function ($ctx) {
		$response = $ctx->get('/modules/basstabs/css/styles.css');
		assertSame(200, $response['status'], 'the stylesheet serves');

		foreach (explode("\n", $response['body']) as $number => $line) {
			if (strpos(ltrim($line), '//') === 0) {
				throw new Exception('basstabs styles.css line ' . ($number + 1) . ' starts a // comment: ' . trim($line));
			}
		}
	},

];
