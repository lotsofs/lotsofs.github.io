<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$songId = ajaxInt($data['song_id'] ?? null);

$join = [
	'idKey' => 'artist_id',
	'table' => 'song_artist',
	'column' => 'artist_id',
	'entity' => 'artist',
	'notFound' => t('song.result.artistNotFound'),
	'duplicate' => t('song.result.artistDuplicate'),
	'insert' => "INSERT INTO song_artist (song_id, artist_id) VALUES (?, ?)",
];

require __MODULES__ . '/music/ajax/songJoin.php';
