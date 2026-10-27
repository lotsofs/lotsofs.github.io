<?php

return [
	'title' => 'Amsterdam',
	'artist' => 'Nothing but Thieves',
	'accidentals' => 'sharp',
	'key' => 'D Phrygian',
	'sections' => [
		['name' => 'Riff', 'notes' => 'E6/2 A5/2 E6 A5/2 A8/2 A5 A5 A5 A5 A5 | D5/2 D8/2 D5 A5/2 A8/2 A5 A5 A5 A8 D7'],
		['name' => 'End of verse 2', 'notes' => 'E6/2 A5/2 E6 A5/2 A8/2 A5 B3 B3 B3 B3 | D5/2 D8/2 D5 A5/2 A8/2 A5 B3 B3 B6 E5'],
		['name' => 'Chorus', 'notes' => 'A6/2 A6/2 A6 B4/2 B4/2 B4 :| E1/2 E3/2 E3 E1/2 E3/2 E3 :| E3/2 E6/2 E6 E6/2 E8/2 E8'],
		['name' => 'Bridge', 'notes' => 'E3: E5: E6: E8: | E3: E5: E6: E8: | E3~ E5~ E6~ E8~ | D5: D7: D8: D10:'],
		[
			'name' => 'Quiet part',
			'notes' => 'B4--/2 E3-/2 E1-/2',
			'board' => ['key' => 'D phrygian', 'anchor' => 'A5', 'position' => 'A5 A6 A8 D5 D7 D8 G5 G7'],
		],
	],
];
