<?php

require_once __MODULES__ . '/basstabs/tabs.php';

$song = bassTabSong(getLastUrlPart());

$pageTitle = $song['title'] . " - Bass Tabs";

$accidentals = $song['accidentals'] ?? 'sharp';

$globalData['title'] = $song['title'];
$globalData['subtitle'] = implode(' · ', array_filter(
	[$song['artist'] ?? '', isset($song['key']) ? bassTabKeyName($song['key']) : ''],
	fn($part) => $part !== ''
));
$keyColumn = bassTabKeyColumn(array_filter(array_map(fn($section) => $section['board']['key'] ?? null, $song['sections'])));

$globalData['sections'] = array_map(function ($section) use ($accidentals, $keyColumn) {
	if (!isset($section['notes']) && !isset($section['board']) && !isset($section['lines'])) {
		throw new InvalidArgumentException("A section needs notes, a board or lines");
	}

	return [
		'name' => $section['name'] ?? '',
		'text' => $section['text'] ?? '',
		'lines' => $section['lines'] ?? [],
		'notation' => $section['notes'] ?? '',
		'staves' => isset($section['notes']) ? bassTabStaves(bassTabParse($section['notes']), $accidentals) : [],
		'board' => isset($section['board'])
			/// A board may name its notes its own way: C minor in flats on a sheet otherwise in sharps.
			? bassTabFretboard($section['board']['key'] ?? '', $section['board']['anchor'] ?? '', $section['board']['position'] ?? '', $section['board']['accidentals'] ?? $accidentals, $keyColumn)
			: null,
		'beside' => ($section['beside'] ?? false) === true,
		'bleed' => 0,
	];
}, $song['sections']);

/// Every staff shrinks by the factor that fits the widest one, so digits match from section to section.
$globalData['widest'] = max(1, ...array_merge(...array_map(fn($section) => array_column($section['staves'], 'width'), $globalData['sections'])));

$globalData['blocks'] = bassTabBlocks($globalData['sections']);

/// A section's last staff lets its bracket band share height with the next heading, when nothing can collide:
/// the next section opens with a heading, neither has a board, and every label in the band starts right of where the heading's text ends.
/// A row takes no part either way: its later headings stand further right, over labels this cannot see.
foreach ($globalData['blocks'] as $index => $block) {
	$next = $globalData['blocks'][$index + 1] ?? null;
	if ($block['kind'] !== 'section' || ($next['kind'] ?? null) !== 'section' || $block['section']['staves'] === []
		|| $block['section']['board'] !== null || $next['section']['board'] !== null || $next['section']['name'] === '') {
		continue;
	}

	$last = end($block['section']['staves']);
	/// The heading is 1.1rem bold, about 11 px a character.
	if ($last['under'] > 0 && $last['labelLeft'] > strlen($next['section']['name']) * 11 + 16) {
		$globalData['blocks'][$index]['section']['bleed'] = $last['under'];
	}
}

require __MODULES__ . '/basstabs/views/song.view.php';
