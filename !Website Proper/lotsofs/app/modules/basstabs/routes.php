<?php

$routes += [
	"/basstabs" => __MODULES__ . "/basstabs/routes/index.php",
];

/// One page per song file, named after it: the router matches whole paths only.
foreach (glob(__MODULES__ . '/basstabs/songs/*.php') as $bassTabFile) {
	$routes += ["/basstabs/" . basename($bassTabFile, '.php') => __MODULES__ . "/basstabs/routes/song.php"];
}
