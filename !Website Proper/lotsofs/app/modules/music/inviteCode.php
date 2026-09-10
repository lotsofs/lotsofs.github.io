<?php

const INVITE_CODE_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
const INVITE_CODE_LENGTH = 8;
const INVITE_CODE_GROUP = 4;

function generateInviteCode() {
	$code = '';
	for ($i = 0; $i < INVITE_CODE_LENGTH; $i++) {
		$code .= INVITE_CODE_ALPHABET[random_int(0, strlen(INVITE_CODE_ALPHABET) - 1)];
	}
	return $code;
}

function formatInviteCode($code) {
	return implode('-', str_split($code, INVITE_CODE_GROUP));
}

function normalizeInviteCode($raw) {
	return preg_replace('/[^' . INVITE_CODE_ALPHABET . ']/', '', strtoupper($raw));
}
