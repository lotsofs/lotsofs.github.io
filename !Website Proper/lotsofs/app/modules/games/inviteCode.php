<?php

const GAMES_INVITE_CODE_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const GAMES_INVITE_CODE_LENGTH = 8;
const GAMES_INVITE_CODE_GROUP = 4;

function gamesGenerateInviteCode() {
	$code = '';
	for ($i = 0; $i < GAMES_INVITE_CODE_LENGTH; $i++) {
		$code .= GAMES_INVITE_CODE_ALPHABET[random_int(0, strlen(GAMES_INVITE_CODE_ALPHABET) - 1)];
	}
	return $code;
}

function gamesFormatInviteCode($code) {
	return implode('-', str_split($code, GAMES_INVITE_CODE_GROUP));
}

/// Letters only, uppercased, so a pasted code keeps working with or without its
/// dashes and whatever case it was typed in.
function gamesNormalizeInviteCode($raw) {
	return preg_replace('/[^' . GAMES_INVITE_CODE_ALPHABET . ']/', '', strtoupper($raw));
}
