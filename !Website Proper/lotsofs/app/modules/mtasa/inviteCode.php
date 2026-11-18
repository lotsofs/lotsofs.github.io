<?php

const MTASA_INVITE_CODE_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const MTASA_INVITE_CODE_LENGTH = 8;
const MTASA_INVITE_CODE_GROUP = 4;

function mtasaGenerateInviteCode() {
	$code = '';
	for ($i = 0; $i < MTASA_INVITE_CODE_LENGTH; $i++) {
		$code .= MTASA_INVITE_CODE_ALPHABET[random_int(0, strlen(MTASA_INVITE_CODE_ALPHABET) - 1)];
	}
	return $code;
}

function mtasaFormatInviteCode($code) {
	return implode('-', str_split($code, MTASA_INVITE_CODE_GROUP));
}

/// Letters only, uppercased, so a pasted code keeps working with or without its
/// dashes and whatever case it was typed in.
function mtasaNormalizeInviteCode($raw) {
	return preg_replace('/[^' . MTASA_INVITE_CODE_ALPHABET . ']/', '', strtoupper($raw));
}
