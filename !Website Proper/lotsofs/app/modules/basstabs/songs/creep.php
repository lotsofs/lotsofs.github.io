<?php

return [
	'title' => 'Creep',
	'artist' => 'Radiohead',
	'accidentals' => 'sharp',
	'key' => 'G major',
	'sections' => [
		['name' => 'Main', 'notes' => 'E3~ | A2~ E2 | A3~ E3 A2 | A3~ E3 A3/2 A5/2 A6/2 A8 :|'],
		['name' => 'Outro', 'beside' => true, 'notes' => 'D5 s D7 D7 h D9'],
		['name' => 'Chorus Guitar Flair', 'notes' => 'E5 G4/2 G5/2 G4 D5 A5 | E7 G8/2 G9/2 G8 D9 A9 |'],
		['board' => ['key' => 'G major', 'anchor' => 'E3', 'position' => 'E3 E5 A2 A3 A5 D2 D4 D5']],
		['board' => ['key' => 'B major', 'anchor' => 'A2', 'position' => 'A2 A4 D1 D2 D4 G1 G3 G4']],
		['board' => ['key' => 'C major', 'anchor' => 'A3', 'position' => 'A3 A5 D2 D3 D5 G2 G4 G5']],
		['board' => ['key' => 'C minor', 'anchor' => 'A3', 'position' => 'A3 A5 A6 D3 D5 D6 G3 G5', 'accidentals' => 'flat']],
	],
];
