<?php
	/* Score graph and order control, shared by the album and artist cards. */
	require_once __MODULES__ . '/music/views/partials/scorePresentation.php';

	/// Fixed coordinate box; its ratio sets the rendered scale of everything.
	$graphWidth = 528;
	$graphHeight = 270;
	$graphPadLeft = 38;
	$graphPadRight = 10;
	$graphPadTop = 14;

	/// Whether any song carries a track number to put on the x axis.
	$graphNumbered = false;
	foreach ($graphSongs as $graphSong) {
		if (($graphSong['position'] ?? null) !== null) {
			$graphNumbered = true;
			break;
		}
	}

	$graphPadBottom = $graphNumbered ? 28 : 8;
	$graphPlotWidth = $graphWidth - $graphPadLeft - $graphPadRight;
	$graphPlotHeight = $graphHeight - $graphPadTop - $graphPadBottom;
	$graphCount = max(1, count($graphSongs));
	$graphSlot = $graphPlotWidth / $graphCount;

	/// Dot size follows the spacing, clamped so dense graphs keep a dot.
	$graphDotRadius = round(max(2, min(5.5, $graphSlot / 4)), 2);

	$graphX = function ($index) use ($graphPadLeft, $graphSlot) {
		return round($graphPadLeft + ($index + 0.5) * $graphSlot, 2);
	};
	$graphY = function ($score) use ($graphPadTop, $graphPlotHeight) {
		return round($graphPadTop + (10 - max(0, min(10, (float)$score))) / 10 * $graphPlotHeight, 2);
	};

	/// Label every Nth song, so about eighteen labels fit at most.
	$graphLabelEvery = max(1, (int)ceil($graphCount / 18));

	/// Every order the graph offers, each with the direction it opens on.
	$graphOrders = [];

	if ($graphNaturalLabel !== null) {
		$graphOrders['album'] = ['label' => $graphNaturalLabel, 'dir' => 'asc'];
	}

	/// Release year, offered and defaulted to only where the songs carry one.
	foreach ($graphSongs as $graphSong) {
		if (($graphSong['year'] ?? null) !== null) {
			$graphOrders['year'] = ['label' => t('album.card.sortYear'), 'dir' => 'asc'];
			break;
		}
	}

	$graphOrders += [
		'title' => ['label' => t('album.card.sortTitle'), 'dir' => 'asc'],
		'average' => ['label' => t('album.card.sortAverage'), 'dir' => 'desc'],
		'deviation' => ['label' => t('album.card.sortDeviation'), 'dir' => 'desc'],
		'median' => ['label' => t('album.card.sortMedian'), 'dir' => 'desc'],
		'highest' => ['label' => t('album.card.sortHighest'), 'dir' => 'desc'],
		'lowest' => ['label' => t('album.card.sortLowest'), 'dir' => 'desc'],
		'mode' => ['label' => t('album.card.sortMode'), 'dir' => 'desc'],
		'rated' => ['label' => t('album.card.sortRated'), 'dir' => 'desc'],
		'duration' => ['label' => t('album.card.sortDuration'), 'dir' => 'desc'],
	];

	foreach ($graphRaters as $graphRater) {
		$graphOrders['rater_' . (int)$graphRater['id']] = [
			'label' => t('album.card.sortRater', ['name' => $graphRater['account_name']]),
			'dir' => 'desc',
		];
	}

	/// An unrecognised key or direction falls back to the order's own default.
	$graphSort = isset($graphOrders[$graphSortKey ?? '']) ? $graphSortKey : array_key_first($graphOrders);
	$graphDir = in_array($graphSortDir ?? '', ['asc', 'desc'], true)
		? $graphSortDir
		: $graphOrders[$graphSort]['dir'];

	/// The value one song sorts on; null for a song with nothing to compare.
	$graphValue = function ($song) use ($graphSort, $graphScores) {
		if (strpos($graphSort, 'rater_') === 0) {
			return $graphScores[(int)$song['song_id']][(int)substr($graphSort, 6)] ?? null;
		}

		switch ($graphSort) {
			case 'title': return $song['title'] ?? '';
			case 'year': return ($song['year'] ?? null) === null ? null : (int)$song['year'];
			case 'average': return $song['average'];
			case 'deviation': return $song['deviation'];
			case 'median': return $song['median'];
			case 'highest': return $song['highest'];
			case 'lowest': return $song['lowest'];
			case 'mode': return ($song['modes'] ?? []) ? max($song['modes']) : null;
			case 'rated': return (int)($song['rated'] ?? 0);
			case 'duration': return ($song['duration'] ?? null) === null ? null : (int)$song['duration'];
		}

		return $song['graphIndex'];
	};

	$graphOrdered = [];
	foreach ($graphSongs as $graphIndex => $graphSong) {
		$graphSong['graphIndex'] = $graphIndex;
		$graphOrdered[] = $graphSong;
	}

	usort($graphOrdered, function ($a, $b) use ($graphValue, $graphDir) {
		$left = $graphValue($a);
		$right = $graphValue($b);

		if ($left === null || $right === null) {
			return $left === $right ? $a['graphIndex'] <=> $b['graphIndex'] : ($left === null ? 1 : -1);
		}

		$order = is_string($left) ? strcasecmp($left, $right) : $left <=> $right;

		/// Ties keep the natural order.
		return $order === 0
			? $a['graphIndex'] <=> $b['graphIndex']
			: ($graphDir === 'desc' ? -$order : $order);
	});

	$graphLabel = function ($song) {
		return ($song['title'] ?? '') === ''
			? (($song['position'] ?? null) === null ? $song['graphIndex'] + 1 : (int)$song['position'])
			: $song['title'];
	};
?>
<dt class="albumGraphHeading"><?= htmlspecialchars(t('album.card.graph')) ?></dt>
<dd class="albumGraphCell">
	<div class="albumGraphSort">
		<label for="albumGraphSortKey"><?= htmlspecialchars(t('album.card.sortBy')) ?></label>
		<select id="albumGraphSortKey" class="albumGraphSortKey cardWheelSelect">
			<?php foreach ($graphOrders as $graphKey => $graphOrder): ?>
				<option value="<?= htmlspecialchars($graphKey) ?>" data-default-dir="<?= $graphOrder['dir'] ?>"<?= $graphKey === $graphSort ? ' selected' : '' ?>><?= htmlspecialchars($graphOrder['label']) ?></option>
			<?php endforeach ?>
		</select>
		<select id="albumGraphSortDir" class="albumGraphSortDir cardWheelSelect">
			<option value="desc"<?= $graphDir === 'desc' ? ' selected' : '' ?>><?= htmlspecialchars(t('album.card.sortDescending')) ?></option>
			<option value="asc"<?= $graphDir === 'asc' ? ' selected' : '' ?>><?= htmlspecialchars(t('album.card.sortAscending')) ?></option>
		</select>
	</div>
	<svg class="albumGraph" viewBox="0 0 <?= $graphWidth ?> <?= $graphHeight ?>" preserveAspectRatio="xMidYMid meet" role="img" aria-label="<?= htmlspecialchars(t('album.card.graphLabel')) ?>">
		<?php for ($score = 0; $score <= 10; $score++): ?>
			<?php if ($score % 2 === 0 || $score === 5): ?>
				<line class="albumGraphGrid<?= $score % 5 === 0 ? ' albumGraphGridMark' : '' ?>" x1="<?= $graphPadLeft ?>" y1="<?= $graphY($score) ?>" x2="<?= $graphWidth - $graphPadRight ?>" y2="<?= $graphY($score) ?>"></line>
			<?php endif ?>
			<?php if ($score % 5 === 0): ?>
				<text class="albumGraphAxis" x="<?= $graphPadLeft - 7 ?>" y="<?= $graphY($score) + 4 ?>" text-anchor="end"><?= $score ?></text>
			<?php endif ?>
		<?php endfor ?>
		<?php if ($graphNumbered): ?>
			<?php foreach ($graphOrdered as $index => $song): ?>
				<?php if ($index % $graphLabelEvery === 0 && ($song['position'] ?? null) !== null): ?>
					<text class="albumGraphAxis" x="<?= $graphX($index) ?>" y="<?= $graphHeight - 9 ?>" text-anchor="middle"><?= (int)$song['position'] ?></text>
				<?php endif ?>
			<?php endforeach ?>
		<?php endif ?>
		<?php foreach ($graphRaters as $rater): ?>
			<?php
				$raterId = (int)$rater['id'];
				$colour = $graphColours[$raterId];
				$runs = [];
				$run = [];
				foreach ($graphOrdered as $index => $song) {
					$score = $graphScores[(int)$song['song_id']][$raterId] ?? null;
					if ($score === null) {
						if (count($run) > 1) {
							$runs[] = $run;
						}
						$run = [];
						continue;
					}
					$run[] = $graphX($index) . ',' . $graphY($score);
				}
				if (count($run) > 1) {
					$runs[] = $run;
				}
			?>
			<?php foreach ($runs as $points): ?>
				<polyline class="albumGraphLine" points="<?= implode(' ', $points) ?>" stroke="<?= $colour ?>"></polyline>
			<?php endforeach ?>
			<?php foreach ($graphOrdered as $index => $song): ?>
				<?php $score = $graphScores[(int)$song['song_id']][$raterId] ?? null ?>
				<?php if ($score !== null): ?>
					<circle class="albumGraphDot" cx="<?= $graphX($index) ?>" cy="<?= $graphY($score) ?>" r="<?= $graphDotRadius ?>" fill="<?= $colour ?>">
						<title><?= htmlspecialchars(t('album.card.graphPoint', [
							'name' => $rater['account_name'],
							'track' => $graphLabel($song),
							'score' => scoreText($score),
						])) ?></title>
					</circle>
				<?php endif ?>
			<?php endforeach ?>
		<?php endforeach ?>
		<?php foreach ($graphOrdered as $index => $song): ?>
			<?php
				/// Invisible hit area per song, drawn last so it sits over the dots.
				$columnLines = [$graphLabel($song)];

				foreach ($graphRaters as $columnRater) {
					$columnScore = $graphScores[(int)$song['song_id']][(int)$columnRater['id']] ?? null;
					$columnLines[] = t('album.card.tooltipScore', [
						'name' => $columnRater['account_name'],
						'score' => $columnScore === null ? t('album.card.noAverage') : scoreText($columnScore),
					]);
				}

				$columnText = implode("\n", $columnLines);
			?>
			<rect class="albumGraphColumn" x="<?= round($graphPadLeft + $index * $graphSlot, 2) ?>" y="<?= $graphPadTop ?>" width="<?= round($graphSlot, 2) ?>" height="<?= $graphPlotHeight ?>" data-tooltip="<?= htmlspecialchars($columnText) ?>">
				<title><?= htmlspecialchars($columnText) ?></title>
			</rect>
		<?php endforeach ?>
	</svg>
</dd>
