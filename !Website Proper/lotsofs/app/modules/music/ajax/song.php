<?php

require_once __MODULES__ . '/music/ajaxGuard.php';
require_once __MODULES__ . '/music/relationships.php';
requireMusicAdminJson($db, t('ajax.notAdmin'));

const SONG_ID_NEW = 'new';
const SONG_ID_SKIP = 'skip';
/// A new song even though one by this artist already goes by the name: another recording of it.
const SONG_ID_SEPARATE = 'separate';

function listedAsAliasId($db, $songId, $providedName) {
	$row = $db->query("SELECT id FROM song_alias WHERE song_id = ? AND name = ? AND is_actual = 0", [$songId, $providedName])->fetch();
	return $row ? (int)$row['id'] : null;
}

$results = [];

$db->pdo->beginTransaction();
try {
	foreach ($data as $datum) {
		$artistId = $datum['artist_id'] ?? null;
		$providedName = trim($datum['title'] ?? '');
		$rawId = (string)($datum['song_id'] ?? SONG_ID_NEW);
		$aliasName = trim($datum['og_name'] ?? '');
		if ($aliasName === '') {
			$aliasName = $providedName;
		}

		if ($rawId === SONG_ID_SKIP) {
			$results[] = ['provided_name' => $providedName, 'artist_id' => $artistId, 'title' => $providedName, 'status' => 'skipped', 'message' => t('song.result.skipped')];
			continue;
		}

		if ($artistId === null || $artistId === '' || $aliasName === '') {
			$results[] = ['provided_name' => $providedName, 'artist_id' => $artistId, 'title' => $providedName, 'status' => 'error', 'message' => t('song.result.required')];
			continue;
		}

		$artistRow = $db->query("SELECT name FROM artist_alias WHERE artist_id = ? ORDER BY is_actual DESC, id LIMIT 1", [$artistId])->fetch();
		$artistName = $artistRow ? $artistRow['name'] : $artistId;

		if ($rawId !== SONG_ID_NEW && $rawId !== SONG_ID_SEPARATE) {
			$songId = (int)$rawId;

			if (!$db->query("SELECT id FROM song WHERE id = ? AND EXISTS (SELECT 1 FROM song_artist WHERE song_id = song.id AND artist_id = ?)", [$songId, $artistId])->fetch()) {
				$results[] = ['provided_name' => $providedName, 'artist_id' => (int)$artistId, 'title' => $providedName, 'status' => 'error', 'message' => t('song.result.notFound')];
				continue;
			}

			$targetRow = $db->query("SELECT name FROM song_alias WHERE song_id = ? AND is_actual = 1", [$songId])->fetch();
			$targetTitle = $targetRow ? $targetRow['name'] : $providedName;

			if ($db->query("SELECT id FROM song_alias WHERE song_id = ? AND name = ?", [$songId, $providedName])->fetch()) {
				$results[] = ['provided_name' => $providedName, 'artist_id' => (int)$artistId, 'song_id' => $songId, 'song_alias_id' => listedAsAliasId($db, $songId, $providedName), 'title' => $providedName, 'status' => 'duplicate', 'message' => t('song.result.aliasDuplicate', ['title' => $targetTitle])];
				continue;
			}

			if (empty($datum['also_alias_provided_name'])) {
				$results[] = ['provided_name' => $providedName, 'artist_id' => (int)$artistId, 'song_id' => $songId, 'song_alias_id' => null, 'title' => $providedName, 'status' => 'duplicate', 'message' => t('song.result.matchedOnly', ['title' => $targetTitle])];
				continue;
			}

			$db->query("INSERT INTO song_alias (song_id, name, is_actual) VALUES (?, ?, 0)", [$songId, $providedName]);
			$results[] = ['provided_name' => $providedName, 'artist_id' => (int)$artistId, 'song_id' => $songId, 'song_alias_id' => listedAsAliasId($db, $songId, $providedName), 'title' => $providedName, 'status' => 'ok', 'message' => t('song.result.aliased', ['name' => $providedName, 'title' => $targetTitle])];
			continue;
		}

		if ($rawId === SONG_ID_NEW) {
			$existing = $db->query("
				SELECT s.id
				FROM song s
				JOIN song_alias sa ON sa.song_id = s.id
				JOIN song_artist art ON art.song_id = s.id
				WHERE art.artist_id = ? AND sa.name = ?
			", [$artistId, $aliasName])->fetch();

			if ($existing) {
				$results[] = ['provided_name' => $providedName, 'artist_id' => (int)$artistId, 'song_id' => (int)$existing['id'], 'song_alias_id' => listedAsAliasId($db, (int)$existing['id'], $providedName), 'title' => $aliasName, 'status' => 'duplicate', 'message' => t('song.result.duplicate')];
				continue;
			}
		}

		/// A separate song may say how it relates to the song of this artist it was kept apart from.
		$relatedId = $rawId === SONG_ID_SEPARATE ? ajaxInt($datum['related_song_id'] ?? null) : 0;
		$relationType = ajaxText($datum['relationship_type'] ?? null);
		$relationRole = ajaxText($datum['relationship_role'] ?? null);

		if ($relatedId !== 0) {
			$refusal = null;
			if (!isset(musicSongRelationTypes()[$relationType]) || !isset(MUSIC_RELATION_ROLES[$relationRole])) {
				$refusal = t('relation.result.unknownType');
			}
			elseif (!$db->query("SELECT 1 FROM song_artist WHERE song_id = ? AND artist_id = ?", [$relatedId, $artistId])->fetch()) {
				$refusal = t('relation.result.notThisArtist');
			}

			if ($refusal !== null) {
				$results[] = ['provided_name' => $providedName, 'artist_id' => (int)$artistId, 'title' => $providedName, 'status' => 'error', 'message' => $refusal];
				continue;
			}
		}

		$db->query("INSERT INTO song DEFAULT VALUES");
		$songId = (int)$db->pdo->lastInsertId();
		$db->query("INSERT INTO song_artist (song_id, artist_id) VALUES (?, ?)", [$songId, $artistId]);
		$db->query("INSERT INTO song_alias (song_id, name, is_actual) VALUES (?, ?, 1)", [$songId, $aliasName]);

		if ($relatedId !== 0) {
			/// The role is the new song's end of the row.
			[$variantId, $targetId] = $relationRole === 'variant' ? [$songId, $relatedId] : [$relatedId, $songId];
			musicAddSongRelation($db, $variantId, $targetId, $relationType);
		}

		$message = t('song.result.added', ['title' => $aliasName, 'artist' => $artistName]);

		if (!empty($datum['also_alias_provided_name']) && $providedName !== '' && $providedName !== $aliasName) {
			$db->query("INSERT INTO song_alias (song_id, name, is_actual) VALUES (?, ?, 0)", [$songId, $providedName]);
			$message .= t('song.result.alsoAliased', ['name' => $providedName]);
		}

		$results[] = ['provided_name' => $providedName, 'artist_id' => (int)$artistId, 'song_id' => $songId, 'song_alias_id' => listedAsAliasId($db, $songId, $providedName), 'title' => $aliasName, 'status' => 'ok', 'message' => $message];
	}

	$db->pdo->commit();
}
catch (PDOException $e) {
	$db->pdo->rollBack();
	throw $e;
}

echo json_encode($results);
