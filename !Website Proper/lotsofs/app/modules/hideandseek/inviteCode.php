<?php

const HNS_INVITE_CODE_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const HNS_INVITE_CODE_LENGTH = 8;
const HNS_INVITE_CODE_GROUP = 4;

function hnsGenerateInviteCode() {
	$code = '';
	for ($i = 0; $i < HNS_INVITE_CODE_LENGTH; $i++) {
		$code .= HNS_INVITE_CODE_ALPHABET[random_int(0, strlen(HNS_INVITE_CODE_ALPHABET) - 1)];
	}
	return $code;
}

function hnsFormatInviteCode($code) {
	return implode('-', str_split($code, HNS_INVITE_CODE_GROUP));
}

/// Letters only, uppercased, so a pasted code keeps working with or without its
/// dashes and whatever case it was typed in.
function hnsNormalizeInviteCode($raw) {
	return preg_replace('/[^' . HNS_INVITE_CODE_ALPHABET . ']/', '', strtoupper($raw));
}
