<?php

require_once __MODULES__ . '/mtasa/ajaxGuard.php';
requireMtasaAccountJson($db, t('ajax.notLoggedIn'));

require_once __MODULES__ . '/mtasa/fields.php';

/// One of the owner's fields on one map, which only admins change. Answers
/// with the value as the page shows it, so the editor writes what was stored.
if (!mtasaIsAdmin($db)) {
	http_response_code(403);
	echo json_encode(['status' => 'error', 'message' => t('map.adminsOnly')]);
	exit;
}

$field = is_string($data['field'] ?? null) ? $data['field'] : '';
$fields = mtasaFields();
if (!isset($fields[$field]) || !$fields[$field]['editable']) {
	echo json_encode(['status' => 'error', 'message' => t('map.unknownField')]);
	exit;
}

$mapId = ajaxInt($data['mapId'] ?? null);
if ($mapId === 0 || !$db->query("SELECT 1 FROM map WHERE id = ?", [$mapId])->fetch()) {
	echo json_encode(['status' => 'error', 'message' => t('map.unknownMap')]);
	exit;
}

[$value, $error] = mtasaFieldValue($field, $data['value'] ?? null, true);
if ($error !== null) {
	echo json_encode(['status' => 'error', 'message' => t('value.error.' . $error)]);
	exit;
}

$db->query("UPDATE map SET {$field} = ?, updated_at = ? WHERE id = ?", [$value, time(), $mapId]);

echo json_encode(['status' => 'ok', 'value' => $value === null ? '' : (string)$value, 'text' => mtasaFieldText($field, $value)]);
