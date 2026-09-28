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
