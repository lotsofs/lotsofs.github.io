<?php

/// Helpers shared by more than one case file. They live here rather than in
/// whichever case file happened to need them first: run.php loads the cases
/// with a plain require in glob() order, so a helper defined in one case file
/// and called from another works only while the defining file sorts earlier,
/// and turns into a fatal the moment either is renamed.

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

/// Asserts the element's class list contains every name given, without caring
/// what order they are written in or what else is alongside them. CLAUDE.md's
/// rule for the card markup is "add to a class list, never replace one", so an
/// assertion that pins the exact attribute string fails on a legal change.
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
