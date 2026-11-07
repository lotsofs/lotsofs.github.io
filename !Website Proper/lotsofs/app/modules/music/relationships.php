<?php

/// Every kind of song_relationship, named from either end: the variant is the
/// derivative, the target the original. A row's relationship_type is one of these keys.
/// `inherits`: each song lends its ratings to the other where that one has none (ratings.php).
/// `variantNoun`/`targetNoun`: each end on its own, for the song list's "You scored the remastered version".
/// `columns`: the song list's id columns, keyed by the end whose row they are on, each headed by a
/// short `label` and named in full (`name`) in the picker and the header's tooltip; their keys are
/// also in MUSIC_SONG_COLUMNS and the stylesheet's left-edge chain and hide list.
function musicSongRelationTypes() {
	return [
		'remaster' => [
			'variant' => t('relation.remasteredVersionOf'),
			'target' => t('relation.originalVersionOf'),
			'variantNoun' => t('relation.remasteredVersion'),
			'targetNoun' => t('relation.originalVersion'),
			'inherits' => true,
			'columns' => [
				'target' => ['key' => 'remasteredBy', 'label' => t('song.column.remasteredByShort'), 'name' => t('song.column.remasteredBy')],
				'variant' => ['key' => 'remasterOf', 'label' => t('song.column.remasterOfShort'), 'name' => t('song.column.remasterOf')],
			],
		],
		/// A cue as a game plays it, and the soundtrack track released from it.
		'game' => [
			'variant' => t('relation.gameVersionOf'),
			'target' => t('relation.soundtrackReleaseOf'),
			'variantNoun' => t('relation.gameVersion'),
			'targetNoun' => t('relation.soundtrackRelease'),
			'inherits' => true,
			'columns' => [
				'target' => ['key' => 'inGameAs', 'label' => t('song.column.inGameAsShort'), 'name' => t('song.column.inGameAs')],
				'variant' => ['key' => 'gameVersionOf', 'label' => t('song.column.gameVersionOfShort'), 'name' => t('song.column.gameVersionOf')],
			],
		],
		'alternate' => [
			'variant' => t('relation.alternateTakeOf'),
			'target' => t('relation.mainTakeOf'),
			'variantNoun' => t('relation.alternateTake'),
			'targetNoun' => t('relation.mainTake'),
			'inherits' => true,
			'columns' => [
				'target' => ['key' => 'alternateTakes', 'label' => t('song.column.alternateTakesShort'), 'name' => t('song.column.alternateTakes')],
				'variant' => ['key' => 'alternateTakeOf', 'label' => t('song.column.alternateTakeOfShort'), 'name' => t('song.column.alternateTakeOf')],
			],
		],
	];
}

const MUSIC_RELATION_ROLES = ['variant' => 'target', 'target' => 'variant'];

/// The same list for the page's JS: [{type, variant, target}], in order.
function musicSongRelationTypeList() {
	$list = [];
	foreach (musicSongRelationTypes() as $type => $labels) {
		$list[] = ['type' => $type] + $labels;
	}
	return $list;
}

/// Each song's relationships from its own end, oldest first, naming the other
/// song by its current name. Every song when $songIds is null, else just those.
function musicSongRelations($db, $songIds = null) {
	$types = musicSongRelationTypes();
	$ids = $songIds === null ? null : array_map('intval', $songIds);

	$where = '';
	$params = [];
	if ($ids !== null) {
		if (!$ids) {
			return [];
		}
		$placeholders = implode(', ', array_fill(0, count($ids), '?'));
		$where = "WHERE r.variant_song_id IN ({$placeholders}) OR r.target_song_id IN ({$placeholders})";
		$params = array_merge($ids, $ids);
	}

	$rows = $db->query("
		SELECT
			r.id,
			r.variant_song_id,
			r.target_song_id,
			r.relationship_type,
			(SELECT name FROM song_alias WHERE song_id = r.variant_song_id ORDER BY is_actual DESC, id LIMIT 1) AS variant_title,
			(SELECT name FROM song_alias WHERE song_id = r.target_song_id ORDER BY is_actual DESC, id LIMIT 1) AS target_title
		FROM song_relationship r
		{$where}
		ORDER BY r.id
	", $params)->fetchAll();

	$bySong = $ids === null ? [] : array_fill_keys($ids, []);
	foreach ($rows as $row) {
		foreach (MUSIC_RELATION_ROLES as $role => $other) {
			$songId = (int)$row["{$role}_song_id"];
			if ($ids !== null && !in_array($songId, $ids, true)) {
				continue;
			}

			/// A code no longer in the list still shows, as itself.
			$bySong[$songId][] = [
				'id' => (int)$row['id'],
				'type' => $row['relationship_type'],
				'role' => $role,
				'label' => $types[$row['relationship_type']][$role] ?? $row['relationship_type'],
				'song_id' => (int)$row["{$other}_song_id"],
				'title' => $row["{$other}_title"] ?? '',
			];
		}
	}

	return $bySong;
}

/* No index holds the table to one row per pair and type, so this check does,
   either way round: the reversed row would put contradictory lines on both
   cards. $exceptId is the row being changed. */
function musicSongRelationExists($db, $variantId, $targetId, $type, $exceptId = 0) {
	return (bool)$db->query("
		SELECT id FROM song_relationship
		WHERE relationship_type = ? AND id != ?
			AND ((variant_song_id = ? AND target_song_id = ?) OR (variant_song_id = ? AND target_song_id = ?))
	", [$type, $exceptId, $variantId, $targetId, $targetId, $variantId])->fetch();
}

function musicAddSongRelation($db, $variantId, $targetId, $type) {
	if (musicSongRelationExists($db, $variantId, $targetId, $type)) {
		return 'duplicate';
	}

	$db->query("INSERT INTO song_relationship (variant_song_id, target_song_id, relationship_type) VALUES (?, ?, ?)", [$variantId, $targetId, $type]);
	return 'ok';
}
