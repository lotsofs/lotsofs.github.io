<?php

return [
	'title' => 'Inside',
	'artist' => 'Stiltskin',
	'accidentals' => 'sharp',
	'key' => 'E minor',
	'sections' => [
		['name' => 'Intro / bridge / riff', 'notes' => 'A5. E5. A7. A7. | ["Pre-Verse 2" A7. A7. ] |'],
		['name' => 'Chorus', 'notes' => 'E0 E0 r E3 E3 r A0 A0 r/2 A3-/2 A3 A3'],
		['name' => 'Fill!!', 'notes' => 'E0 A2 D2 E0 | E3 A2 D0 E3 | A0 A4 D0 A0 | E3 A2 D0 E3'],
		[
			'name' => 'Verse / solo',
			'notes' => 'E0. A3. A0. A3.',
			'board' => ['key' => 'E minor', 'anchor' => 'E0', 'position' => 'E0 E2 E3 A0 A2 A3 D0 D2'],
		],
		['board' => ['key' => 'C major', 'anchor' => 'A3', 'position' => 'A3 A5 D2 D3 D5 G2 G4 G5']],
		['board' => ['key' => 'A major', 'anchor' => 'A0', 'position' => 'A0 A2 A4 D0 D2 D4 G1 G2']],
	],
];
