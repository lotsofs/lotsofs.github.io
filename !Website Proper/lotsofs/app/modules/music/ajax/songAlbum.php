<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

$songId = ajaxInt($data['song_id'] ?? null);

$join = [
	'idKey' => 'album_id',
	'table' => 'album_track',
	'column' => 'album_id',
	'entity' => 'album',
	'notFound' => t('song.result.albumNotFound'),
	'duplicate' => t('song.result.albumDuplicate'),
	'insert' => "INSERT INTO album_track (song_id, album_id, position, song_alias_id) VALUES (?, ?, NULL, NULL)",
];

require __MODULES__ . '/music/ajax/songJoin.php';
