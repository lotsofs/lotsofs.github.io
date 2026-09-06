<?php

require $_SERVER['DOCUMENT_ROOT'] . '/ajax/ajax.php';

stringCatalogue('music');

$db = require __MODULES__ . '/music/db.php';

$results = [];

foreach ($data as $datum) {
	$artistId = $datum['artist_id'] ?? null;
	$title = trim($datum['title'] ?? '');

	if ($artistId === null || $artistId === '' || $title === '') {
		$results[] = ['artist_id' => $artistId, 'title' => $title, 'status' => 'error', 'message' => t('song.required')];
		continue;
	}

	$existingStmt = $db->query("SELECT id FROM song WHERE artist_id = ? AND title = ?", [$artistId, $title]);
	$existing = $existingStmt ? $existingStmt->fetch() : false;

	if ($existing) {
		$results[] = ['artist_id' => (int)$artistId, 'title' => $title, 'status' => 'duplicate', 'message' => t('song.duplicate')];
		continue;
	}

	// prefer the artist's actual name, fall back to any alias it has
	$artistNameStmt = $db->query("SELECT name FROM artist_alias WHERE artist_id = ? ORDER BY is_actual DESC LIMIT 1", [$artistId]);
	$artistRow = $artistNameStmt ? $artistNameStmt->fetch() : false;
	$artistName = $artistRow ? $artistRow['name'] : $artistId;

	$db->query("INSERT INTO song (artist_id, title) VALUES (?, ?)", [$artistId, $title]);
	$results[] = ['artist_id' => (int)$artistId, 'title' => $title, 'status' => 'ok', 'message' => t('song.added', ['title' => $title, 'artist' => $artistName])];
}

echo json_encode($results);
