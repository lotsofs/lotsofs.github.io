<?php

return [
	'title' => 'My Favourite Game',
	'artist' => 'The Cardigans',
	'accidentals' => 'flat',
	'key' => 'C minor',
	'sections' => [
		[
			'name' => 'Riff',
			'notes' => 'E8 E6 B6 B9/2 E6/2',
			'board' => ['key' => 'C minor', 'anchor' => 'E8', 'position' => 'E8 E10 E11 A8 A10 A11 D8 D10'],
		],
		['name' => 'Chorus', 'notes' => 'E8- B8 E6 :| ["Only 2nd chorus" E8 B9 E6 B8/2 E6/2 :| ] E8 B9 B6 E6 B8^"Drum fill"'],
		['name' => 'Bridge', 'notes' => '["6x" E8 E9 , E8 E9 , E8 E9 , E8 E9 , E8 E9 , E8 E9 ] | E8 B9 B6 E6 B8'],
		[
			'name' => 'Outro',
			'text' => 'Riff 2x, then end of riff 2x',
			'notes' => 'B6 B9/2 E6/2 | B6 B9/2 E6/2',
			'board' => ['key' => 'F major', 'anchor' => 'B6', 'position' => 'B6 B8 E5 E6 E8 A5 A7 A8'],
		],
	],
];
