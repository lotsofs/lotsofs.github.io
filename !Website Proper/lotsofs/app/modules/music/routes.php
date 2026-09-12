<?php

$routes += [
	"/music" => __MODULES__ . "/music/routes/index.php",
	"/music/add-songs" => __MODULES__ . "/music/routes/addSongs.php",
	"/music/songs" => __MODULES__ . "/music/routes/songs.php",
	"/music/artists" => __MODULES__ . "/music/routes/artists.php",
	"/music/albums" => __MODULES__ . "/music/routes/albums.php",
	"/music/register" => __MODULES__ . "/music/routes/register.php",
	"/music/login" => __MODULES__ . "/music/routes/login.php",
	"/music/logout" => __MODULES__ . "/music/routes/logout.php",
	"/music/language" => __MODULES__ . "/music/routes/language.php",
	"/music/invites" => __MODULES__ . "/music/routes/invites.php",
	"/music/accounts" => __MODULES__ . "/music/routes/accounts.php",
	"/music/ajax/artist-alias" => __MODULES__ . "/music/ajax/artistAlias.php",
	"/music/ajax/song" => __MODULES__ . "/music/ajax/song.php",
	"/music/ajax/song-edit" => __MODULES__ . "/music/ajax/songEdit.php",
	"/music/ajax/song-rating" => __MODULES__ . "/music/ajax/songRating.php",
	"/music/ajax/song-rating-poll" => __MODULES__ . "/music/ajax/songRatingPoll.php",
	"/music/ajax/album" => __MODULES__ . "/music/ajax/album.php",
];
