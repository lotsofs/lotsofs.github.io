<?php

/// Ordering for the artist and album lists. Both sort in PHP, in one pass, because
/// their statistics are worked out there: an ORDER BY could not see half the columns.

/// The column a list is ordered by. A key it does not have falls back to the default.
function musicListSort($columns, $requested, $default) {
	return is_string($requested) && isset($columns[$requested]) ? $requested : $default;
}

/// 'asc' unless 'desc' was asked for.
function musicListDir($requested) {
	return $requested === 'desc' ? 'desc' : 'asc';
}

/// The seven statistics columns both lists carry, in the order they are rendered.
function musicListStatColumns() {
	return [
		'average' => [
			'label' => t('album.card.statAverage'),
			'class' => 'albumStatsScoreCell',
			'value' => fn($row) => $row['average'],
		],
		'deviation' => [
			'label' => t('album.card.statDeviation'),
			'class' => 'albumStatsScoreCell',
			'value' => fn($row) => $row['deviation'],
		],
		'median' => [
			'label' => t('album.card.statMedian'),
			'class' => 'albumStatsScoreCell',
			'value' => fn($row) => $row['median'],
		],
		'mode' => [
			'label' => t('album.card.statMode'),
			'class' => 'albumStatsScoreCell',
			/// The highest of the tied scores, which is what the album graph orders on.
			'value' => fn($row) => $row['modes'] ? max($row['modes']) : null,
		],
		'highest' => [
			'label' => t('song.column.statHighest'),
			'class' => 'albumStatsScoreCell',
			'value' => fn($row) => $row['highest'],
		],
		'lowest' => [
			'label' => t('song.column.statLowest'),
			'class' => 'albumStatsScoreCell',
			'value' => fn($row) => $row['lowest'],
		],
		'rated' => [
			'label' => t('album.card.statRated'),
			'class' => 'albumStatsRatedCell',
			/// A count of nothing is a count, not a blank, so it sorts as zero.
			'value' => fn($row) => (int)$row['rated'],
		],
	];
}

/// Rows by one column, with empty cells last whichever way the column points.
function musicListSorted($rows, $column, $dir) {
	$read = $column['value'];
	$text = $column['text'] ?? false;
	$flip = $dir === 'desc' ? -1 : 1;

	$decorated = [];
	foreach ($rows as $row) {
		$cell = $read($row);

		$decorated[] = [
			'empty' => $cell === null || $cell === '',
			'cell' => $cell,
			'id' => (int)$row['id'],
			'row' => $row,
		];
	}

	usort($decorated, function ($a, $b) use ($flip, $text) {
		if ($a['empty'] || $b['empty']) {
			if ($a['empty'] === $b['empty']) {
				return $a['id'] <=> $b['id'];
			}

			return $a['empty'] ? 1 : -1;
		}

		$order = $text ? strcasecmp((string)$a['cell'], (string)$b['cell']) : ($a['cell'] <=> $b['cell']);

		return $order === 0 ? $a['id'] <=> $b['id'] : $order * $flip;
	});

	return array_column($decorated, 'row');
}

/// Every column with the link, arrow and hint its header needs.
function musicListHeaders($columns, $sort, $dir) {
	$headers = [];

	foreach ($columns as $key => $column) {
		$isActive = $key === $sort;

		$headers[] = array_merge($column, [
			'key' => $key,
			'link' => '?sort=' . $key . '&dir=' . ($isActive && $dir === 'asc' ? 'desc' : 'asc'),
			'indicator' => $isActive ? ($dir === 'asc' ? ' ▲' : ' ▼') : '',
			'title' => t('song.list.columnSortHint', ['column' => $column['label']]),
		]);
	}

	return $headers;
}
