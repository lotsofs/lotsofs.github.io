<?php

$scene = require __MAIN__ . "/scenes/theSuspect.php";

$pageTitle = $scene['title'];

/// Each line as [['say' | 'direction', text], ...], directions being whatever stands in parentheses.
$sceneLines = [];
foreach ($scene['lines'] as $line) {
	$parts = [];
	foreach (preg_split('/(\([^)]*\))/', $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY) as $piece) {
		$piece = trim($piece);
		if ($piece === '') {
			continue;
		}
		$parts[] = [$piece[0] === '(' ? 'direction' : 'say', $piece];
	}
	$sceneLines[] = $parts;
}

require __MAIN__ . "/views/rehearse.view.php";
