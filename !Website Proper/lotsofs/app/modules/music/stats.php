<?php

/// The four numbers the album card reports, for one set of scores - whether
/// that set is "what one rater gave this album" or "what everyone gave this
/// track". Both were written out longhand and side by side before, which meant
/// the per-rater and per-track columns on the same card could end up computed
/// differently: someone "fixing" one of them to sample deviation would leave
/// the other on population deviation, with nothing on screen to say so.
///
/// Sorts its own copy rather than trusting the caller to have done it, so the
/// median cannot silently come from an unsorted list. SQLite has no stddev()
/// and median and mode are awkward in SQL, which is why none of this is a
/// query.
function musicScoreStats($scores) {
	$sorted = array_values($scores);
	sort($sorted);

	$rated = count($sorted);

	if ($rated === 0) {
		return ['rated' => 0, 'average' => null, 'median' => null, 'deviation' => null, 'modes' => []];
	}

	$average = array_sum($sorted) / $rated;

	$middle = intdiv($rated, 2);
	$median = $rated % 2 === 1 ? $sorted[$middle] : ($sorted[$middle - 1] + $sorted[$middle]) / 2;

	$spread = 0.0;
	foreach ($sorted as $score) {
		$spread += ($score - $average) ** 2;
	}

	/// Population, not sample: these are all the scores that were given, not a
	/// sample of some larger set, and n = 1 has a spread of nothing rather
	/// than being undefined.
	$deviation = sqrt($spread / $rated);

	$counts = [];
	foreach ($sorted as $score) {
		$key = (string)$score;
		$counts[$key] = ($counts[$key] ?? 0) + 1;
	}

	/// All-distinct scores make every one of them a mode, which says nothing,
	/// so that case reports no mode at all rather than the lot.
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
	];
}
