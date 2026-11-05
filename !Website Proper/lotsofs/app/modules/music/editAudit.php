<?php

require_once __MODULES__ . '/music/aliases.php';
require_once __MODULES__ . '/music/links.php';
require_once __MODULES__ . '/music/relationships.php';

/// The edit log of what admins change (docs/music.md). Each write calls these inside the transaction that holds it.

/// One edit_audit row; a write that changes nothing logs nothing.
function musicLogEdit($db, $kind, $id, $field, $previous, $value) {
	$previous = $previous === null || $previous === '' ? null : (string)$previous;
	$value = $value === null || $value === '' ? null : (string)$value;

	if ($previous === $value) {
		return;
	}

	$db->query("
		INSERT INTO edit_audit (account_id, kind, entity_id, field, value, previous_value, created_at)
		VALUES (?, ?, ?, ?, ?, ?, ?)
	", [(int)currentAccountId(), $kind, (int)$id, $field, $value, $previous, time()]);
}

/// The name a song, album or artist goes by now, as the cards show it.
function musicAuditName($db, $kind, $id) {
	return $id === null ? null : (musicAliasNames($db, $kind, $id)[0] ?? null);
}

/// A track as the album's listing reads it, "3. Title" under the name it is listed by; null while the album has no such track.
function musicAuditTrackLine($db, $albumId, $songId) {
	$track = $db->query("
		SELECT at.position,
			COALESCE(listed.name, (SELECT name FROM song_alias WHERE song_id = at.song_id ORDER BY is_actual DESC, id LIMIT 1)) AS name
		FROM album_track at
		LEFT JOIN song_alias listed ON listed.id = at.song_alias_id
		WHERE at.album_id = ? AND at.song_id = ?
	", [$albumId, $songId])->fetch();

	if (!$track) {
		return null;
	}

	$name = $track['name'] ?? '';

	return $track['position'] === null ? $name : "{$track['position']}. {$name}";
}

/// A write to one album_track row and the change to its line, in one transaction.
function musicWriteTrack($db, $albumId, $songId, $sql, $params) {
	$before = musicAuditTrackLine($db, $albumId, $songId);

	$db->pdo->beginTransaction();
	try {
		$db->query($sql, $params);
		musicLogEdit($db, 'album', $albumId, 'track', $before, musicAuditTrackLine($db, $albumId, $songId));
		$db->pdo->commit();
	}
	catch (PDOException $e) {
		$db->pdo->rollBack();
		throw $e;
	}
}

/* A song_relationship row as ['variant' => id, 'target' => id, 'type' => code],
   or null for none. Logged on the variant, the end that reads "remastered
   version of", so a relationship has one place in the log; a row whose variant
   changed is a removal from one song and an addition to the other. */
function musicLogRelationEdit($db, $before, $after) {
	$text = fn($row) => $row === null ? null : json_encode([
		'type' => $row['type'],
		'song' => (int)$row['target'],
		'title' => musicAuditName($db, 'song', $row['target']),
	]);

	if ($before !== null && $after !== null && (int)$before['variant'] === (int)$after['variant']) {
		musicLogEdit($db, 'song', $after['variant'], 'relation', $text($before), $text($after));
		return;
	}

	if ($before !== null) {
		musicLogEdit($db, 'song', $before['variant'], 'relation', $text($before), null);
	}
	if ($after !== null) {
		musicLogEdit($db, 'song', $after['variant'], 'relation', null, $text($after));
	}
}

/// Each field's label on the History page; a field missing here shows as itself.
function musicAuditFieldLabels() {
	$labels = [
		'score' => t('audit.field.score'),
		'note' => t('audit.field.note'),
		'added' => t('audit.field.added'),
		'title' => t('audit.field.title'),
		'name' => t('audit.field.name'),
		'alias' => t('audit.field.alias'),
		'artist' => t('audit.field.artist'),
		'year' => t('audit.field.year'),
		'duration' => t('audit.field.duration'),
		'track' => t('audit.field.track'),
		'relation' => t('audit.field.relation'),
	];

	foreach (songLinkFields() as $link) {
		$labels[$link['key']] = $link['label'];
	}

	return $labels;
}

/// A logged value as the page shows it: a relationship as its variant's card names it, in the reader's language; anything else as written.
function musicAuditValueText($field, $raw) {
	if ($raw === null || $field !== 'relation') {
		return $raw;
	}

	$relation = json_decode($raw, true);
	if (!is_array($relation)) {
		return $raw;
	}

	$type = $relation['type'] ?? '';
	$label = musicSongRelationTypes()[$type]['variant'] ?? $type;

	return $label . ' ' . ($relation['song'] ?? '') . ': ' . ($relation['title'] ?? '');
}
