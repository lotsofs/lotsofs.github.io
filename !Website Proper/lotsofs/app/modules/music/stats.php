<?php

require_once __MODULES__ . '/music/format.php';

/// Count, mean, median, population deviation and modes for one set of scores.
function musicScoreStats($scores) {
	$sorted = array_values($scores);
	sort($sorted);

	$rated = count($sorted);

	if ($rated === 0) {
		return ['rated' => 0, 'average' => null, 'median' => null, 'deviation' => null, 'modes' => [], 'lowest' => null, 'highest' => null];
	}

	$average = array_sum($sorted) / $rated;

	$middle = intdiv($rated, 2);
	$median = $rated % 2 === 1 ? $sorted[$middle] : ($sorted[$middle - 1] + $sorted[$middle]) / 2;

	$spread = 0.0;
	foreach ($sorted as $score) {
		$spread += ($score - $average) ** 2;
	}

	/// Population deviation, not sample.
	$deviation = sqrt($spread / $rated);

	$counts = [];
	foreach ($sorted as $score) {
		$key = (string)$score;
		$counts[$key] = ($counts[$key] ?? 0) + 1;
	}

	/// All-distinct scores report no mode at all.
	$modes = [];
	if (max($counts) > 1) {
		foreach ($counts as $value => $count) {
			if ($count === max($counts)) {
				$modes[] = (float)$value;
			}
		}
	}

	return [
		'rated' => $rated,
		'average' => $average,
		'median' => $median,
		'deviation' => $deviation,
		'modes' => $modes,
		/// Free, since the list is already sorted.
		'lowest' => $sorted[0],
		'highest' => $sorted[$rated - 1],
	];
}

/* One song's statistics as the strings its cells show, for the song list, the
   rating poll and a rating write. `modeSort` is the value an ordering uses. */
function musicSongStatFields($scores) {
	$stats = musicScoreStats($scores);

	$text = function ($value) {
		return $value === null ? '' : scoreText($value);
	};

	return [
		'average' => $text($stats['average']),
		'deviation' => $text($stats['deviation']),
		'median' => $text($stats['median']),
		'mode' => implode(', ', array_map('scoreText', $stats['modes'])),
		'modeSort' => $stats['modes'] ? scoreText(max($stats['modes'])) : '',
		'highest' => $text($stats['highest']),
		'lowest' => $text($stats['lowest']),
		'rated' => (string)$stats['rated'],
	];
}

/// Whose scores a card is read as: an account id, or null for everyone pooled.
function musicStatsWho($requested, $rows) {
	foreach ($rows as $row) {
		if ((int)$row['id'] === (int)$requested && (int)$row['rated'] > 0) {
			return (int)$row['id'];
		}
	}

	return null;
}
