<?php

/// Top to bottom as the sheet draws them, so the low B is the bottom line.
const BASS_TAB_STRINGS = ['G', 'D', 'A', 'E', 'B'];

const BASS_TAB_HIGHEST_FRET = 24;

/// Bar line tokens => which side of a repeat they mark, if any.
const BASS_TAB_BARS = ['|' => null, ':|' => 'end', '|:' => 'start'];

/// Marks drawn over a note's digit, by the character that asks for one.
const BASS_TAB_MARKS = [':' => 'dots', '.' => 'dot', '~' => 'tilde'];

/// A note's length from its suffix: 1, doubled by each '-' and halved by a '/2' after them.
function bassTabLength($dashes, $half) {
	return $half === '' ? 2 ** strlen($dashes) : 2 ** strlen($dashes) / 2;
}

/// Each open string's pitch in semitones above C.
const BASS_TAB_OPEN_PITCHES = ['G' => 7, 'D' => 2, 'A' => 9, 'E' => 4, 'B' => 11];

/// Note names upwards from C, spelt the way a song asks for.
const BASS_TAB_NOTE_NAMES = [
	'sharp' => ['C', 'C♯', 'D', 'D♯', 'E', 'F', 'F♯', 'G', 'G♯', 'A', 'A♯', 'B'],
	'flat' => ['C', 'D♭', 'D', 'E♭', 'E', 'F', 'G♭', 'G', 'A♭', 'A', 'B♭', 'B'],
];

/// Semitones above the root, by the name a key is written with. Minor is natural minor; a mode keeps its capital.
const BASS_TAB_SCALES = [
	'major' => [0, 2, 4, 5, 7, 9, 11],
	'minor' => [0, 2, 3, 5, 7, 8, 10],
	'Phrygian' => [0, 1, 3, 5, 7, 8, 10],
];

const BASS_TAB_LETTER_PITCHES = ['C' => 0, 'D' => 2, 'E' => 4, 'F' => 5, 'G' => 7, 'A' => 9, 'B' => 11];

/// The fretboard drawing runs from the open string to the octave.
const BASS_TAB_BOARD_FRETS = 12;

/// The frets a bass marks with a dot on its board, shaded on the drawing to match.
const BASS_TAB_BOARD_INLAYS = [3, 5, 7, 9, 12];

/// 'E', 8 -> 0: semitones above C, from 0 to 11.
function bassTabPitch($string, $fret) {
	return (BASS_TAB_OPEN_PITCHES[$string] + $fret) % 12;
}

/// 'E', 8 -> 'C'. $accidentals is 'sharp' or 'flat'.
function bassTabNoteName($string, $fret, $accidentals) {
	if (!isset(BASS_TAB_NOTE_NAMES[$accidentals])) {
		throw new InvalidArgumentException("Accidentals are 'sharp' or 'flat', not '{$accidentals}'");
	}

	return BASS_TAB_NOTE_NAMES[$accidentals][bassTabPitch($string, $fret)];
}

/// 'C minor' -> [0, 2, 3, 5, 7, 8, 10], the pitches in it from the root up.
function bassTabKey($key) {
	if (!preg_match('/^([A-G])(b|#|♭|♯)? (\w+)$/', $key, $match) || bassTabScaleName($match[3]) === null) {
		throw new InvalidArgumentException("Not a key: '{$key}'");
	}

	$shift = ['' => 0, 'b' => -1, '♭' => -1, '#' => 1, '♯' => 1][$match[2]];
	$root = (BASS_TAB_LETTER_PITCHES[$match[1]] + $shift + 12) % 12;

	return array_map(fn($step) => ($root + $step) % 12, BASS_TAB_SCALES[bassTabScaleName($match[3])]);
}

/// 'phrygian' -> 'Phrygian': a scale's name as BASS_TAB_SCALES spells it, whatever case it was typed in; null if there is none.
function bassTabScaleName($name) {
	foreach (array_keys(BASS_TAB_SCALES) as $scale) {
		if (strcasecmp($scale, $name) === 0) {
			return $scale;
		}
	}

	return null;
}

/// 'Bb minor' -> 'B♭ minor', 'D phrygian' -> 'D Phrygian': a key as the sheet prints it. Throws on anything bassTabKey() would.
function bassTabKeyName($key) {
	bassTabKey($key);
	[$root, $scale] = explode(' ', $key);

	return strtr($root, ['b' => '♭', '#' => '♯']) . ' ' . bassTabScaleName($scale);
}

/// Px for the column left of a board that holds its key's name, root over scale, wide enough for the
/// longest word among $keys: one width for every board on a sheet keeps a stack of them lined up.
/// Guessed at 8.5 px a character, there being no font metrics here; characters, not bytes, as ♯ is three.
function bassTabKeyColumn($keys) {
	$longest = 0;
	foreach ($keys as $key) {
		foreach (explode(' ', bassTabKeyName($key)) as $word) {
			$longest = max($longest, preg_match_all('/./u', $word));
		}
	}

	return (int)ceil($longest * 8.5) + 8;
}

/// Every fret in the key from the open string to BASS_TAB_BOARD_FRETS as a dot, the hand
/// position and its anchor marked, laid out in px. Throws unless the position is plain notes,
/// all in the key and on the board, with the anchor among them.
function bassTabFretboard($key, $anchor, $position, $accidentals, $keyColumn = null) {
	$pitches = bassTabKey($key);
	/// The key's own triad, its first, third and fifth notes: minor or major as the scale makes it.
	$chord = [$pitches[0], $pitches[2], $pitches[4]];

	$marked = [];
	foreach (bassTabParse($position) as $item) {
		if ($item['kind'] !== 'note' || $item['length'] !== 1 || $item['held'] || $item['mark'] !== null || $item['arrow'] !== null) {
			throw new InvalidArgumentException("A hand position is plain notes: '{$position}'");
		}

		$note = $item['string'] . $item['fret'];
		if ($item['fret'] > BASS_TAB_BOARD_FRETS) {
			throw new InvalidArgumentException("{$note} is past the board's last fret, " . BASS_TAB_BOARD_FRETS);
		}
		if (!in_array(bassTabPitch($item['string'], $item['fret']), $pitches, true)) {
			throw new InvalidArgumentException("{$note} is not in {$key}");
		}

		$marked[$note] = 'position';
	}

	if (!isset($marked[$anchor])) {
		throw new InvalidArgumentException("The anchor '{$anchor}' is not one of the hand position's notes");
	}
	$marked[$anchor] = 'anchor';

	$stringGap = 24;
	/// The key's name stands in a column left of the string letters, inside the frame, rather than across the top, which costs a board 20 px of height.
	$keyColumn ??= bassTabKeyColumn([$key]);
	$pad = 14;
	$lineStart = $keyColumn + 16;
	$openWidth = 30;
	/// Narrow enough for the board, frame and all, to sit beside a short staff on an A4 page.
	$cell = 35;
	/// Room between the drawing and the frame around it.
	$inset = 6;

	$nutX = $lineStart + $openWidth;
	$boardEnd = $nutX + BASS_TAB_BOARD_FRETS * $cell;
	/// The open string sits left of the nut; every fret is the middle of its cell.
	$fretX = fn($fret) => $fret === 0 ? $lineStart + $openWidth / 2 : $nutX + ($fret - 0.5) * $cell;

	$stringY = [];
	foreach (BASS_TAB_STRINGS as $index => $string) {
		$stringY[$string] = $pad + $index * $stringGap;
	}
	$top = reset($stringY);
	$bottom = end($stringY);

	$dots = [];
	foreach ($stringY as $string => $y) {
		foreach (range(0, BASS_TAB_BOARD_FRETS) as $fret) {
			if (!in_array(bassTabPitch($string, $fret), $pitches, true)) {
				continue;
			}

			$dots[] = [
				'x' => $fretX($fret),
				'y' => $y,
				'string' => $string,
				'fret' => $fret,
				'name' => bassTabNoteName($string, $fret, $accidentals),
				'tier' => $marked[$string . $fret] ?? 'scale',
				'chord' => in_array(bassTabPitch($string, $fret), $chord, true),
			];
		}
	}

	return [
		'width' => $boardEnd + 4 + 2 * $inset,
		/// The fret numbers' row with a few px under it before the frame.
		'height' => $bottom + $pad + 12 + 2 * $inset,
		'inset' => $inset,
		/// The root level with the top string and the scale with the next.
		'keyLines' => array_map(null, array_slice(array_values($stringY), 0, 2), explode(' ', bassTabKeyName($key))),
		'label' => "{$key} on frets 0 to " . BASS_TAB_BOARD_FRETS . ", hand position {$position}, anchored on {$anchor}",
		'anchor' => $anchor,
		'nameX' => $keyColumn,
		'lineStart' => $lineStart,
		'boardEnd' => $boardEnd,
		'stringY' => $stringY,
		'top' => $top,
		'bottom' => $bottom,
		'nutX' => $nutX,
		'wires' => array_map(fn($fret) => $nutX + $fret * $cell, range(1, BASS_TAB_BOARD_FRETS)),
		'inlays' => array_map(fn($fret) => [$nutX + ($fret - 1) * $cell, $cell], BASS_TAB_BOARD_INLAYS),
		'numbers' => array_map(fn($fret) => [$fretX($fret), $fret], range(0, BASS_TAB_BOARD_FRETS)),
		'numberY' => $bottom + $pad + 8,
		'dots' => $dots,
	];
}

/// One section's notation as notes, bar lines and bracket marks, in order. Throws
/// InvalidArgumentException naming what it could not read.
function bassTabParse($notation) {
	/// A quoted label may hold spaces, so it stays one token with whatever it is attached to.
	preg_match_all('/[^\s"]*"[^"]*"|\S+/', $notation, $matches);

	$items = [];
	$openAt = null;

	foreach ($matches[0] as $token) {
		if (preg_match('/^([GDAEB])(0|[1-9][0-9]?)(-{0,3})(\/2)?(:|\.|~)?(?:\^"([^"]*)")?$/', $token, $match) && (int)$match[2] <= BASS_TAB_HIGHEST_FRET) {
			$items[] = [
				'kind' => 'note',
				'string' => $match[1],
				'fret' => (int)$match[2],
				'length' => bassTabLength($match[3], $match[4] ?? ''),
				/// Held is the line, not the length: 'E3-/2' is drawn held but lasts as long as a plain note.
				'held' => $match[3] !== '',
				'mark' => BASS_TAB_MARKS[$match[5] ?? ''] ?? null,
				'arrow' => $match[6] ?? null,
			];
		}
		else if (array_key_exists($token, BASS_TAB_BARS)) {
			$items[] = ['kind' => 'bar', 'repeat' => BASS_TAB_BARS[$token]];
		}
		else if ($token === ',') {
			$items[] = ['kind' => 'gap'];
		}
		else if ($token === 'r' || $token === 'r/2') {
			$items[] = ['kind' => 'rest', 'length' => $token === 'r' ? 1 : 0.5];
		}
		else if ($token === 'h') {
			$items[] = ['kind' => 'hammer'];
		}
		else if ($token === 's') {
			$items[] = ['kind' => 'slide'];
		}
		else if ($token === '/') {
			if ($openAt !== null) {
				throw new InvalidArgumentException("A line break '/' inside a bracket");
			}
			$items[] = ['kind' => 'break'];
		}
		else if (preg_match('/^\[(?:"([^"]*)")?$/', $token, $match)) {
			if ($openAt !== null) {
				throw new InvalidArgumentException("A bracket inside a bracket: '{$token}'");
			}
			$openAt = count($items);
			$items[] = ['kind' => 'open', 'label' => $match[1] ?? ''];
		}
		else if ($token === ']') {
			if ($openAt === null) {
				throw new InvalidArgumentException("A ']' with no '[' before it");
			}
			if (!array_intersect(array_column(array_slice($items, $openAt + 1), 'kind'), ['note', 'rest', 'bar'])) {
				throw new InvalidArgumentException("A bracket around nothing");
			}
			$items[] = ['kind' => 'close'];
			$openAt = null;
		}
		else {
			throw new InvalidArgumentException("Not a note: '{$token}'");
		}
	}

	if ($openAt !== null) {
		throw new InvalidArgumentException("A '[' that is never closed");
	}

	foreach ($items as $index => $item) {
		$before = $items[$index - 1] ?? null;
		$after = $items[$index + 1] ?? null;

		if ($item['kind'] === 'hammer' && (
			($before['kind'] ?? null) !== 'note' || ($after['kind'] ?? null) !== 'note'
			|| $before['string'] !== $after['string'] || $after['fret'] <= $before['fret']
		)) {
			throw new InvalidArgumentException("A hammer-on 'h' goes between two notes on one string, the second higher");
		}

		if ($item['kind'] === 'slide' && (
			($before['kind'] ?? null) !== 'note' || ($after['kind'] ?? null) !== 'note'
			|| $before['string'] !== $after['string'] || $after['fret'] === $before['fret']
		)) {
			throw new InvalidArgumentException("A slide 's' goes between two notes on one string, on different frets");
		}

		if ($item['kind'] === 'break' && ($before === null || $after === null || $after['kind'] === 'break')) {
			throw new InvalidArgumentException("A line break '/' needs notes on both sides of it");
		}
	}

	return $items;
}

/// One staff per line of a section, split at its '/' line breaks.
function bassTabStaves($items, $accidentals) {
	$lines = [[]];
	foreach ($items as $item) {
		if ($item['kind'] === 'break') {
			$lines[] = [];
		}
		else {
			$lines[count($lines) - 1][] = $item;
		}
	}

	return array_map(fn($line) => bassTabLayout($line, $accidentals), $lines);
}

/// Where everything on one section's staff goes, in px. The gap after a note is its
/// length times $unit, and a ',' adds $groupGap, which groups notes for the eye only.
/// Under the lines come the note names, then brackets and arrows.
function bassTabLayout($items, $accidentals) {
	$unit = 30;
	$lineGap = 18;
	$pad = 12;
	$lineStart = 16;
	$barGap = 16;
	$groupGap = 10;
	$noteHalf = 10;
	/// Under the note names: a bracket or arrow, its label, and room for the label's descenders.
	$under = 30;

	/// A mark or a hammer-on over a note on the top string would otherwise stand above the staff's edge and be cut off.
	$markRoom = 0;
	foreach ($items as $index => $item) {
		if ($item['kind'] === 'note' && $item['mark'] !== null && $item['string'] === BASS_TAB_STRINGS[0]) {
			$markRoom = max($markRoom, 10);
		}
		if ($item['kind'] === 'hammer' && $items[$index - 1]['string'] === BASS_TAB_STRINGS[0]) {
			$markRoom = max($markRoom, 16);
		}
	}

	$lineY = [];
	foreach (BASS_TAB_STRINGS as $index => $string) {
		$lineY[$string] = $pad + $markRoom + $index * $lineGap;
	}
	$top = reset($lineY);
	$bottom = end($lineY);
	$nameY = $bottom + 17;
	$below = $bottom + 20;

	$notes = [];
	$bars = [];
	$arrows = [];
	$spans = [];
	/// Notes and bar lines in order, for a bracket to find what is next to it.
	$placed = [];

	$cursor = $lineStart + 22;
	$lastNoteEnd = $lineStart;
	$openAt = null;
	$openLabel = '';
	/// The index in $notes of each hammer-on's first note; the second is the one after it.
	$hammerFrom = [];
	$slideFrom = [];
	$rests = [];

	foreach ($items as $item) {
		if ($item['kind'] === 'note') {
			$x = $cursor;
			$y = $lineY[$item['string']];
			$notes[] = [
				'x' => $x,
				'y' => $y,
				'string' => $item['string'],
				'fret' => $item['fret'],
				'name' => bassTabNoteName($item['string'], $item['fret'], $accidentals),
				'hold' => $item['held'] ? [$x + 9, $x + $item['length'] * $unit - 14] : null,
				'mark' => $item['mark'],
				'markDots' => match ($item['mark']) {
					'dots' => [[$x - 3, $y - 13], [$x + 3, $y - 13]],
					'dot' => [[$x, $y - 13]],
					default => [],
				},
				/// One wave, down then up, drawn rather than set in a font that may not have the glyph.
				'markTilde' => $item['mark'] === 'tilde' ? 'M' . ($x - 5) . ',' . ($y - 12) . ' q2.5,-3.5 5,0 t5,0' : null,
			];
			if ($item['arrow'] !== null) {
				$arrows[] = [
					'x' => $x,
					'head' => "{$x}," . ($below + 5) . " " . ($x - 4) . "," . ($below + 12) . " " . ($x + 4) . "," . ($below + 12),
					'shaft' => [$below + 11, $below + 15],
					'labelY' => $below + 22,
					'label' => $item['arrow'],
				];
			}
			$placed[] = ['kind' => 'note', 'x' => $x];
			/// A held note's line runs into its extra length, so a bar line has to come after that.
			$lastNoteEnd = $x + max($item['length'] - 1, 0) * $unit;
			$cursor += $item['length'] * $unit;
		}
		else if ($item['kind'] === 'bar') {
			$cursor += $barGap;
			$x = ($lastNoteEnd + $cursor) / 2;
			$dotX = $x + ($item['repeat'] === 'end' ? -5 : 5);
			$bars[] = [
				'x' => $x,
				'y1' => $top,
				'y2' => $bottom,
				'dots' => $item['repeat'] === null ? [] : [
					[$dotX, ($lineY['D'] + $lineY['A']) / 2],
					[$dotX, ($lineY['A'] + $lineY['E']) / 2],
				],
			];
			$placed[] = ['kind' => 'bar', 'x' => $x];
			$lastNoteEnd = $x;
		}
		else if ($item['kind'] === 'gap') {
			$cursor += $groupGap;
		}
		else if ($item['kind'] === 'rest') {
			$x = $cursor;
			$y = $lineY['A'];
			$rests[] = $item['length'] === 1
				/// A zigzag down through the middle line ending in a curl: a quarter rest.
				? ['path' => 'M' . ($x - 2) . ',' . ($y - 12) . ' l4,6 l-5,5 l5,6 q-6,-2 -3,6', 'dot' => null]
				/// A dot, a flick to the right and a stem slanting back down: an eighth rest, the 7 on the owner's page.
				: ['path' => 'M' . ($x - 3) . ',' . ($y - 5) . ' q3,2 6,-1 l-4,12', 'dot' => [$x - 3, $y - 5]];
			$placed[] = ['kind' => 'note', 'x' => $x];
			$lastNoteEnd = $x;
			$cursor += $item['length'] * $unit;
		}
		else if ($item['kind'] === 'hammer') {
			$hammerFrom[] = count($notes) - 1;
		}
		else if ($item['kind'] === 'slide') {
			$slideFrom[] = count($notes) - 1;
		}
		else if ($item['kind'] === 'open') {
			$openAt = count($placed);
			$openLabel = $item['label'];
		}
		else {
			$spans[] = [$openAt, count($placed) - 1, $openLabel];
		}
	}

	/// An arc from digit to digit over the string, the 'h' above it.
	$hammers = [];
	foreach ($hammerFrom as $index) {
		[$from, $to] = [$notes[$index], $notes[$index + 1]];
		$y = $from['y'];
		$hammers[] = [
			'arc' => 'M' . ($from['x'] + 4) . ',' . ($y - 9) . ' Q' . (($from['x'] + $to['x']) / 2) . ',' . ($y - 17) . ' ' . ($to['x'] - 4) . ',' . ($y - 9),
			'labelX' => ($from['x'] + $to['x']) / 2,
			'labelY' => $y - 20,
		];
	}

	/// A slash just before the second digit, as tab writes 5/7, rising for a slide up and falling for one down.
	/// Measured from that digit, so after a held note it rises out of the end of the hold.
	$slides = [];
	foreach ($slideFrom as $index) {
		[$from, $to] = [$notes[$index], $notes[$index + 1]];
		$rise = $to['fret'] > $from['fret'] ? 6 : -6;
		$slides[] = [
			'x1' => $to['x'] - 19,
			'y1' => $to['y'] + $rise,
			'x2' => $to['x'] - 11,
			'y2' => $to['y'] - $rise,
			'direction' => $rise > 0 ? 'up' : 'down',
		];
	}

	/// A bracket end that meets a bar line, inside or just outside, stops on it.
	$brackets = [];
	foreach ($spans as [$first, $last, $label]) {
		$before = $placed[$first - 1] ?? null;
		$after = $placed[$last + 1] ?? null;

		if ($placed[$first]['kind'] === 'bar') {
			$x1 = $placed[$first]['x'];
		}
		else {
			$x1 = $before !== null && $before['kind'] === 'bar' ? $before['x'] : $placed[$first]['x'] - $noteHalf;
		}

		if ($placed[$last]['kind'] === 'bar') {
			$x2 = $placed[$last]['x'];
		}
		else {
			$x2 = $after !== null && $after['kind'] === 'bar' ? $after['x'] : $placed[$last]['x'] + $noteHalf;
		}

		$brackets[] = [
			'points' => "{$x1}," . ($below + 6) . " {$x1}," . ($below + 12) . " {$x2}," . ($below + 12) . " {$x2}," . ($below + 6),
			'labelX' => ($x1 + $x2) / 2,
			'labelY' => $below + 22,
			'label' => $label,
		];
	}

	/// Labels are centred under what they mark; this guesses their width, there being no font metrics here.
	$width = $cursor;
	foreach ($arrows as $arrow) {
		$width = max($width, $arrow['x'] + strlen($arrow['label']) * 3.5 + 4);
	}
	foreach ($brackets as $bracket) {
		$width = max($width, $bracket['labelX'] + strlen($bracket['label']) * 3.5 + 4);
	}

	return [
		'width' => ceil($width),
		'height' => $below + ($arrows || $brackets ? $under : 6),
		'lineStart' => $lineStart,
		'nameY' => $nameY,
		/// Far past the viewBox: the lines overflow it to the edge of the page, which clips them.
		'lineEnd' => 5000,
		'lineY' => $lineY,
		'notes' => $notes,
		'bars' => $bars,
		'brackets' => $brackets,
		'arrows' => $arrows,
		'hammers' => $hammers,
		'slides' => $slides,
		'rests' => $rests,
		/// The band under the note names for brackets and arrows, and where its leftmost label starts.
		'under' => $arrows || $brackets ? $under : 0,
		'labelLeft' => min([INF, ...array_map(fn($arrow) => $arrow['x'] - strlen($arrow['label']) * 3.5, $arrows), ...array_map(fn($bracket) => $bracket['labelX'] - strlen($bracket['label']) * 3.5, $brackets)]),
	];
}

/// A sheet's sections as the view draws them: one section; a row of them side by side, each after the
/// first marked 'beside'; or a run of sections that are only a board. Under a section with a board beside
/// it, such a run lines up with that board on the right instead of starting at the left edge. Throws on a
/// 'beside' with nothing to sit beside, and on a board in a row, whose column needs the section's full width.
function bassTabBlocks($sections) {
	$blocks = [];
	foreach ($sections as $section) {
		$last = array_key_last($blocks);
		$above = $last !== null ? $blocks[$last] : null;
		$drawn = $section['staves'] !== [] || $section['lines'] !== [] || $section['text'] !== '';

		if ($section['beside']) {
			if (!$drawn || !in_array($above['kind'] ?? null, ['section', 'row'], true)) {
				throw new InvalidArgumentException("A section 'beside' another goes after a section with notes, lines or text, and has one of those itself");
			}
			$row = $above['kind'] === 'row' ? $above['sections'] : [$above['section']];
			$row[] = $section;
			if (array_filter($row, fn($member) => $member['board'] !== null)) {
				throw new InvalidArgumentException("A section with a board cannot share its line with another");
			}
			$blocks[$last] = ['kind' => 'row', 'sections' => $row];
		}
		else if ($drawn) {
			$blocks[] = ['kind' => 'section', 'section' => $section];
		}
		else if (($above['kind'] ?? null) === 'boards') {
			$blocks[$last]['sections'][] = $section;
		}
		else {
			$blocks[] = [
				'kind' => 'boards',
				'sections' => [$section],
				'right' => ($above['kind'] ?? null) === 'section' && $above['section']['board'] !== null,
			];
		}
	}

	return $blocks;
}

/// Every song file, slug => its array, by title.
function bassTabSongs() {
	$songs = [];

	foreach (glob(__DIR__ . '/songs/*.php') as $file) {
		$songs[basename($file, '.php')] = require $file;
	}

	uasort($songs, fn($a, $b) => strcasecmp($a['title'], $b['title']));

	return $songs;
}

/// Only ever handed a slug the router matched to a song file.
function bassTabSong($slug) {
	return require __DIR__ . '/songs/' . $slug . '.php';
}
