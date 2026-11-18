<?php

$routes += [
	"/games" => __MODULES__ . "/games/routes/index.php",
	"/games/register" => __MODULES__ . "/games/routes/register.php",
	"/games/login" => __MODULES__ . "/games/routes/login.php",
	"/games/logout" => __MODULES__ . "/games/routes/logout.php",
	"/games/language" => __MODULES__ . "/games/routes/language.php",
	"/games/accounts" => __MODULES__ . "/games/routes/accounts.php",
	"/games/aoe2" => __MODULES__ . "/games/routes/aoe2.php",
	"/games/sims2" => __MODULES__ . "/games/routes/sims2.php",
	"/games/ajax/aoe2-check" => __MODULES__ . "/games/ajax/aoe2Check.php",
	"/games/ajax/aoe2-fold" => __MODULES__ . "/games/ajax/aoe2Fold.php",
	"/games/ajax/aoe2-entry" => __MODULES__ . "/games/ajax/aoe2Entry.php",
	"/games/ajax/sims2-check" => __MODULES__ . "/games/ajax/sims2Check.php",
	"/games/ajax/sims2-vehicle" => __MODULES__ . "/games/ajax/sims2Vehicle.php",
];
