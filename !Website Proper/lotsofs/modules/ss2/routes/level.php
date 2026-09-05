<?php


$levels = [
	'11' => "Jungle (M'Digbo)",
	'12' => "Riverdance (M'Digbo)",
	'13' => "M'Keke Village (M'Digbo)",
	'14' => "Road to Ursul (M'Digbo)",
	'15' => "Ursul Suburbs (M'Digbo)",
	'16' => "Kukulele Prison (M'Digbo)",
	'17' => "Ursul Gardens (M'Digbo)",
	'18' => "Kwongo (M'Digbo)",
];
	
$levelId = getLastUrlPart();

$pageTitle = $levels[$levelId];

require(__MODULES__ . '/ss2/views/level.view.php');