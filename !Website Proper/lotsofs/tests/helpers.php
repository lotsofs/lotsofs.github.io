<?php

/// Helpers shared by more than one case file; a case file defining one breaks on a rename.

function registerAccount($ctx, $fields, $extraHeaders = []) {
	$fields['csrf_token'] = $ctx->csrfTokenFrom('/music/register');
	return $ctx->postForm('/music/register', $fields, false, $extraHeaders);
}

function logInAs($ctx, $name, $password) {
	return $ctx->postForm('/music/login', [
		'csrf_token' => $ctx->csrfTokenFrom('/music/login'),
		'account_name' => $name,
		'password' => $password,
	]);
}

function makeAlbum($ctx, $name, $artistId, $tracks) {
	$response = $ctx->post('/music/ajax/album', [[
		'provided_name' => $name,
		'album_id' => 'new',
		'og_name' => $name,
		'is_actual' => true,
		'artist_id' => $artistId,
		'release_year' => '',
		'tracks' => $tracks,
	]]);
	return (int)$response['json'][0]['album_id'];
}

function makeSong($ctx, $artistId, $title) {
	$response = $ctx->post('/music/ajax/song', [['artist_id' => $artistId, 'title' => $title]]);
	return (int)$response['json'][0]['song_id'];
}

/// One row of the artist or album list, found by the card id its name cell carries.
function listRowFor($body, $attribute, $id) {
	foreach (explode('<tr>', $body) as $row) {
		if (strpos($row, $attribute . '="' . (int)$id . '"') !== false) {
			return $row;
		}
	}

	return null;
}

/// The text of every cell in one list row carrying a class, in column order.
function listCells($row, $class) {
	preg_match_all('/<td class="([^"]*)"[^>]*>(.*?)<\/td>/s', (string)$row, $matches, PREG_SET_ORDER);

	$cells = [];
	foreach ($matches as $match) {
		if (in_array($class, preg_split('/\s+/', trim($match[1])), true)) {
			$cells[] = trim(strip_tags($match[2]));
		}
	}

	return $cells;
}

/// The card ids of an artist or album list, in the order the page put them in.
function listOrder($body, $attribute) {
	preg_match_all('/' . preg_quote($attribute, '/') . '="(\d+)"/', $body, $matches);

	return array_map('intval', $matches[1]);
}

/// Only the ids given, in the order the page put them, so a shared database's other rows do not matter.
function listOrderOf($body, $attribute, $ids) {
	$order = [];

	foreach (listOrder($body, $attribute) as $id) {
		if (in_array($id, $ids, true)) {
			$order[] = $id;
		}
	}

	return $order;
}

/// Asserts a class list contains every name given, whatever the order or the rest.
function assertClasses($needles, $body, $pattern, $what) {
	if (!preg_match($pattern, $body, $match)) {
		throw new Exception("{$what}: nothing matched " . $pattern);
	}

	$classes = preg_split('/\s+/', trim($match[1]));

	foreach ((array)$needles as $needle) {
		if (!in_array($needle, $classes, true)) {
			throw new Exception("{$what}: no '{$needle}' in class=\"" . $match[1] . '"');
		}
	}
}

// One chunk per song row; the id is the leading digits.
function songsRowChunks($body) {
	$chunks = explode('<tr data-song-id="', $body);
	array_shift($chunks);
	return $chunks;
}

// Reads a data-field cell, whether it holds text, a span or links.
function songsCellValue($chunk, $field) {
	$pattern = '/data-field="' . preg_quote($field, '/') . '"[^>]*>(.*?)<\/(?:td|dd)>/s';
	return preg_match($pattern, $chunk, $m) ? strip_tags($m[1]) : null;
}

function songsValuesInOrder($body, $field) {
	$values = [];
	foreach (songsRowChunks($body) as $chunk) {
		$values[] = songsCellValue($chunk, $field);
	}
	return $values;
}

function songsRowFor($body, $songId) {
	foreach (songsRowChunks($body) as $chunk) {
		if (strpos($chunk, (int)$songId . '"') === 0) {
			return $chunk;
		}
	}
	return null;
}

// Smoke-check helper for the separate #songCards tree.
function songsCardFor($body, $songId) {
	$chunks = preg_split('/<dl class="songCard[^"]*" data-song-id="/', $body);
	array_shift($chunks);
	foreach ($chunks as $chunk) {
		if (strpos($chunk, (int)$songId . '"') === 0) {
			return $chunk;
		}
	}
	return null;
}
