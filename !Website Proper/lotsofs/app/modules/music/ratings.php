<?php

require_once __MODULES__ . '/music/relationships.php';
require_once __MODULES__ . '/music/stats.php';

/* Effective ratings: what each account gave each song, the real score or note
   where there is one, else what they gave the song's other versions - every
   song a relationship of an `inherits` kind ties it to, directly. A borrowed
   score is the average of theirs on those versions, a borrowed note the one
   they wrote last. Everything that reads ratings reads effective_rating, so the
   song list, both lists and both cards agree on what counts; account_song stays
   the only thing written, and the poll's cursor stays on it.

   The newest note is by its rating_audit time. Every note written since
   migration 005 is audited, so an unaudited note is older than any audited one,
   and only between unaudited notes does account_song.updated_at decide: it moves
   on score writes too, so it cannot say when a note was written.

   TEMP views on this request's connection, created by every reader after the
   migrations. Window functions are avoided: the host's SQLite version is not
   known (see migration 009), so the newest note is found with NOT EXISTS. */
function musicRatingView($db) {
	$lending = [];
	foreach (musicSongRelationTypes() as $type => $spec) {
		if (!empty($spec['inherits'])) {
			$lending[] = $db->pdo->quote($type);
		}
	}
	$lends = $lending ? 'relationship_type IN (' . implode(', ', $lending) . ')' : '0';

	$db->execSQL("
		CREATE TEMP VIEW IF NOT EXISTS song_version AS
			SELECT song_id, version_id, MIN(relation_id) AS relation_id
			FROM (
				SELECT variant_song_id AS song_id, target_song_id AS version_id, id AS relation_id FROM song_relationship WHERE {$lends}
				UNION ALL
				SELECT target_song_id, variant_song_id, id FROM song_relationship WHERE {$lends}
			)
			GROUP BY song_id, version_id;

		CREATE TEMP VIEW IF NOT EXISTS borrowed_score AS
			SELECT v.song_id, a.account_id, ROUND(AVG(a.score), 2) AS score
			FROM song_version v
			JOIN account_song a ON a.song_id = v.version_id
			WHERE a.score IS NOT NULL
			GROUP BY v.song_id, a.account_id;

		CREATE TEMP VIEW IF NOT EXISTS version_note AS
			SELECT v.song_id, a.account_id, a.subjective_note AS note, v.relation_id, COALESCE(ra.at, -1) AS noted_at, a.updated_at
			FROM song_version v
			JOIN account_song a ON a.song_id = v.version_id
			LEFT JOIN (
				SELECT account_id, song_id, MAX(created_at) AS at
				FROM rating_audit
				WHERE field = 'note' AND song_id IN (SELECT version_id FROM song_version)
				GROUP BY account_id, song_id
			) ra ON ra.account_id = a.account_id AND ra.song_id = a.song_id
			WHERE a.subjective_note IS NOT NULL AND a.subjective_note != '';

		CREATE TEMP VIEW IF NOT EXISTS borrowed_note AS
			SELECT n.song_id, n.account_id, n.note
			FROM version_note n
			WHERE NOT EXISTS (
				SELECT 1 FROM version_note o
				WHERE o.song_id = n.song_id AND o.account_id = n.account_id
					AND (o.noted_at > n.noted_at
						OR (o.noted_at = n.noted_at AND o.updated_at > n.updated_at)
						OR (o.noted_at = n.noted_at AND o.updated_at = n.updated_at AND o.relation_id < n.relation_id))
			);

		CREATE TEMP VIEW IF NOT EXISTS effective_rating AS
			SELECT
				a.account_id,
				a.song_id,
				COALESCE(a.score, bs.score) AS score,
				CASE WHEN a.subjective_note IS NULL OR a.subjective_note = '' THEN bn.note ELSE a.subjective_note END AS subjective_note,
				a.score IS NULL AND bs.score IS NOT NULL AS score_borrowed,
				(a.subjective_note IS NULL OR a.subjective_note = '') AND bn.note IS NOT NULL AS note_borrowed
			FROM account_song a
			LEFT JOIN borrowed_score bs ON bs.song_id = a.song_id AND bs.account_id = a.account_id
			LEFT JOIN borrowed_note bn ON bn.song_id = a.song_id AND bn.account_id = a.account_id
			UNION ALL
			SELECT k.account_id, k.song_id, bs.score, bn.note, bs.score IS NOT NULL, bn.note IS NOT NULL
			FROM (SELECT song_id, account_id FROM borrowed_score UNION SELECT song_id, account_id FROM borrowed_note) k
			LEFT JOIN borrowed_score bs ON bs.song_id = k.song_id AND bs.account_id = k.account_id
			LEFT JOIN borrowed_note bn ON bn.song_id = k.song_id AND bn.account_id = k.account_id
			WHERE NOT EXISTS (SELECT 1 FROM account_song a WHERE a.account_id = k.account_id AND a.song_id = k.song_id);
	");
}

/// The songs that lend to these, or borrow from them: whose effective ratings a write on these can change.
function musicVersionIds($db, $songIds) {
	$ids = array_values(array_unique(array_map('intval', $songIds)));
	if (!$ids) {
		return [];
	}

	$placeholders = implode(', ', array_fill(0, count($ids), '?'));
	$related = array_map('intval', array_column($db->query("SELECT DISTINCT version_id FROM song_version WHERE song_id IN ({$placeholders})", $ids)->fetchAll(), 'version_id'));

	return array_values(array_diff($related, $ids));
}

/* Effective ratings for the live answers, as the song list's cells hold them: an
   entry for every song and account asked about (every account when none are
   named), empty where there is nothing, since a borrowed value can go away. */
function musicEffectiveRatings($db, $songIds, $accountIds = null) {
	$ids = array_values(array_unique(array_map('intval', $songIds)));
	$accounts = $accountIds === null
		? array_map('intval', array_column($db->query("SELECT id FROM account")->fetchAll(), 'id'))
		: array_values(array_unique(array_map('intval', $accountIds)));
	if (!$ids || !$accounts) {
		return [];
	}

	$entries = [];
	foreach ($ids as $songId) {
		foreach ($accounts as $accountId) {
			$entries["{$songId}:{$accountId}"] = musicRatingEntry(['song_id' => $songId, 'account_id' => $accountId, 'score' => null, 'subjective_note' => null, 'score_borrowed' => 0, 'note_borrowed' => 0]);
		}
	}

	$songIn = implode(', ', array_fill(0, count($ids), '?'));
	$accountIn = implode(', ', array_fill(0, count($accounts), '?'));
	foreach ($db->query("
		SELECT account_id, song_id, score, subjective_note, score_borrowed, note_borrowed
		FROM effective_rating
		WHERE song_id IN ({$songIn}) AND account_id IN ({$accountIn})
	", array_merge($ids, $accounts))->fetchAll() as $row) {
		$entries[(int)$row['song_id'] . ':' . (int)$row['account_id']] = musicRatingEntry($row);
	}

	return array_values($entries);
}

/// One account's rating on one song for the page: values as the cells print them, and which half is borrowed.
function musicRatingEntry($row) {
	return [
		'song' => (int)$row['song_id'],
		'account' => (int)$row['account_id'],
		'score' => $row['score'] === null ? '' : (string)(float)$row['score'],
		'note' => $row['subjective_note'] ?? '',
		'scoreBorrowed' => (bool)$row['score_borrowed'],
		'noteBorrowed' => (bool)$row['note_borrowed'],
	];
}

/// The song list's statistics columns for one song as they stand, over its effective scores.
function musicSongStatsRow($db, $songId) {
	$scores = [];
	foreach ($db->query("SELECT score FROM effective_rating WHERE song_id = ? AND score IS NOT NULL", [$songId])->fetchAll() as $row) {
		$scores[] = (float)$row['score'];
	}

	return array_merge(['song' => (int)$songId], musicSongStatFields($scores));
}
