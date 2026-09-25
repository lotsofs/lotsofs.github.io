<?php
	require_once __MODULES__ . '/music/links.php';
	require_once __MODULES__ . '/music/hue.php';
	require_once __MODULES__ . '/music/format.php';

	$albumName = ($album['name'] ?? '') === '' ? t('album.list.noName') : $album['name'];
	$albumArtistId = $album['artist_id'] === null ? null : (int)$album['artist_id'];
	$albumArtist = ($album['artist'] ?? '') === '' ? t('album.list.noArtist') : $album['artist'];
	$albumAliases = $album['aliases'] ?? '';
	$albumTracks = $album['tracks'] ?? [];
	$albumAverages = $album['averages'] ?? [];
	$albumTrackCount = $album['trackCount'] ?? count($albumTracks);
	$albumIsAdmin = $album['isAdmin'] ?? false;
	$albumTrackScores = $album['trackScores'] ?? [];

	/* A rater's dots are drawn in the hue that rater sees the site in, so the
	   colour reads the same to everybody looking at this card - it belongs to
	   the person, not to their row in a table that sorts the viewer first. */
	$albumRaterColours = [];
	foreach ($albumAverages as $average) {
		$albumRaterColours[(int)$average['id']] = 'hsl(' . (int)($average['hue'] ?? MUSIC_HUE_DEFAULT) . ', 70%, 62%)';
	}

	$albumGraphRaters = [];
	foreach ($albumAverages as $average) {
		if ((int)$average['rated'] > 0) {
			$albumGraphRaters[] = $average;
		}
	}

	/// Trailing zeroes off, so a flat 7 reads as 7 and not 7.00 next to a 6.48.
	$albumScoreText = function ($value) {
		return rtrim(rtrim(number_format((float)$value, 2, '.', ''), '0'), '.');
	};

	/// The same ramp songs.js paints score cells with, red at 0 through green
	/// at 10. Kept in both places on purpose: this card renders server-side and
	/// is shown on pages that never load songs.js.
	$albumScoreColour = function ($value) {
		return ' style="color: hsl(' . round(max(0, min(10, (float)$value)) * 12, 2) . ', 75%, 55%)"';
	};
?>
<dl class="albumCard card" data-album-id="<?= (int)$album['id'] ?>" data-artist-id="<?= $albumArtistId === null ? '' : $albumArtistId ?>" data-release-year="<?= htmlspecialchars($album['release_year'] ?? '') ?>" data-album-name="<?= htmlspecialchars($album['name'] ?? '') ?>">
	<div class="albumCardIdRow cardIdRow">
		<dd class="albumIdCell cardIdCell"><?= (int)$album['id'] ?></dd>
		<?php if ($albumIsAdmin): ?>
			<button type="button" class="albumCardEditBtn cardEditBtn"><?= htmlspecialchars(t('album.card.edit')) ?></button>
		<?php endif ?>
	</div>
	<dd class="albumTitleCell cardTitle" data-field="name"><?= htmlspecialchars($albumName) ?></dd>
	<div class="albumCardInfo">
		<dt><?= htmlspecialchars(t('album.column.artist')) ?></dt>
		<dd class="albumArtistCell" data-field="artist"><?= htmlspecialchars($albumArtist) ?></dd>
		<dt><?= htmlspecialchars(t('album.column.year')) ?></dt>
		<dd class="albumYearCell" data-field="year"><?= htmlspecialchars($album['release_year'] ?? '') ?></dd>
		<?php if ($albumAliases !== ''): ?>
			<dt><?= htmlspecialchars(t('album.column.aliases')) ?></dt>
			<dd class="albumAliasCell"><?= htmlspecialchars($albumAliases) ?></dd>
		<?php endif ?>
	</div>
	<dt class="albumTrackHeading"><?= htmlspecialchars(t('album.column.tracks')) ?></dt>
	<dd class="albumTrackCell" data-field="tracks">
		<?php if (!$albumTracks): ?>
			<p class="albumNoTracks"><?= t('album.card.noTracks') ?></p>
		<?php else: ?>
			<table class="albumTrackTable">
				<thead>
					<tr>
						<th class="albumTrackPosition"><?= htmlspecialchars(t('album.card.trackNumber')) ?></th>
						<th class="albumTrackTitle"><?= htmlspecialchars(t('song.column.title')) ?></th>
						<?php if ($albumArtistId === null): ?>
							<th class="albumTrackArtist"><?= htmlspecialchars(t('song.column.artist')) ?></th>
						<?php endif ?>
						<?php if ($albumGraphRaters): ?>
							<th class="albumTrackScore"><?= htmlspecialchars(t('album.card.statAverage')) ?></th>
							<th class="albumTrackDeviation"><?= htmlspecialchars(t('album.card.statDeviation')) ?></th>
							<th class="albumTrackRated"><?= htmlspecialchars(t('album.card.statRated')) ?></th>
						<?php endif ?>
						<th class="albumTrackDuration"><?= htmlspecialchars(t('song.column.duration')) ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($albumTracks as $track): ?>
						<?php
							$trackDuration = musicDuration($track['duration']);
						?>
						<tr class="albumTrack" data-song-id="<?= (int)$track['song_id'] ?>" data-position="<?= $track['position'] === null ? '' : (int)$track['position'] ?>" data-song-alias-id="<?= ($track['song_alias_id'] ?? null) === null ? '' : (int)$track['song_alias_id'] ?>">
							<td class="albumTrackPosition"><?= $track['position'] === null ? '' : (int)$track['position'] ?></td>
							<td class="albumTrackTitle"><?= htmlspecialchars($track['title'] ?? '') ?></td>
							<?php if ($albumArtistId === null): ?>
								<td class="albumTrackArtist"><?= htmlspecialchars($track['artist'] ?? '') ?></td>
							<?php endif ?>
							<?php if ($albumGraphRaters): ?>
								<?php $trackAverage = $track['average'] ?? null ?>
								<?php if ($trackAverage === null): ?>
									<td class="albumTrackScore albumTrackScoreEmpty"><?= htmlspecialchars(t('album.card.noAverage')) ?></td>
									<td class="albumTrackDeviation albumTrackScoreEmpty"><?= htmlspecialchars(t('album.card.noAverage')) ?></td>
								<?php else: ?>
									<td class="albumTrackScore"<?= $albumScoreColour($trackAverage) ?>><?= htmlspecialchars($albumScoreText($trackAverage)) ?></td>
									<td class="albumTrackDeviation"><?= htmlspecialchars($albumScoreText($track['deviation'])) ?></td>
								<?php endif ?>
								<td class="albumTrackRated"><?= htmlspecialchars(t('album.card.ratedOf', ['rated' => (int)($track['rated'] ?? 0), 'total' => count($albumAverages)])) ?></td>
							<?php endif ?>
							<td class="albumTrackDuration"><?= $trackDuration ?></td>
						</tr>
					<?php endforeach ?>
				</tbody>
			</table>
		<?php endif ?>
	</dd>
	<?php if ($albumAverages): ?>
		<dt class="albumStatsHeading"><?= htmlspecialchars(t('album.card.averages')) ?></dt>
		<dd class="albumStatsCell">
			<table class="albumStatsTable">
				<thead>
					<tr>
						<th class="albumStatsNameCell"><?= htmlspecialchars(t('album.card.statRater')) ?></th>
						<th class="albumStatsScoreCell"><?= htmlspecialchars(t('album.card.statAverage')) ?></th>
						<th class="albumStatsScoreCell"><?= htmlspecialchars(t('album.card.statDeviation')) ?></th>
						<th class="albumStatsScoreCell"><?= htmlspecialchars(t('album.card.statMedian')) ?></th>
						<th class="albumStatsScoreCell"><?= htmlspecialchars(t('album.card.statMode')) ?></th>
						<th class="albumStatsRatedCell"><?= htmlspecialchars(t('album.card.statRated')) ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($albumAverages as $average): ?>
						<?php
							$rated = (int)$average['rated'];
							$modes = $average['modes'] ?? [];
							$noScore = ' class="albumStatsScoreCell albumStatsEmpty"';

							/// Coloured one value at a time: a tie between two
							/// scores is two different colours, not an average
							/// of them and not a plain grey list.
							$modeHtml = '';
							foreach ($modes as $mode) {
								$modeHtml .= ($modeHtml === '' ? '' : ', ')
									. '<span' . $albumScoreColour($mode) . '>' . htmlspecialchars($albumScoreText($mode)) . '</span>';
							}
						?>
						<tr class="albumStatsRow" data-account-id="<?= (int)$average['id'] ?>">
							<td class="albumStatsNameCell"><?php if ($rated > 0): ?><span class="albumStatsSwatch" style="background-color: <?= $albumRaterColours[(int)$average['id']] ?>"></span><?php endif ?><?= htmlspecialchars($average['account_name']) ?></td>
							<?php if ($rated === 0): ?>
								<td<?= $noScore ?>><?= htmlspecialchars(t('album.card.noAverage')) ?></td>
								<td<?= $noScore ?>><?= htmlspecialchars(t('album.card.noAverage')) ?></td>
								<td<?= $noScore ?>><?= htmlspecialchars(t('album.card.noAverage')) ?></td>
								<td<?= $noScore ?>><?= htmlspecialchars(t('album.card.noAverage')) ?></td>
							<?php else: ?>
								<td class="albumStatsScoreCell"<?= $albumScoreColour($average['average']) ?>><?= htmlspecialchars($albumScoreText($average['average'])) ?></td>
								<td class="albumStatsScoreCell albumStatsDeviationCell"><?= htmlspecialchars($albumScoreText($average['deviation'])) ?></td>
								<td class="albumStatsScoreCell"<?= $albumScoreColour($average['median']) ?>><?= htmlspecialchars($albumScoreText($average['median'])) ?></td>
								<td class="albumStatsScoreCell<?= $modeHtml === '' ? ' albumStatsEmpty' : '' ?>"><?= $modeHtml === '' ? htmlspecialchars(t('album.card.noAverage')) : $modeHtml ?></td>
							<?php endif ?>
							<td class="albumStatsRatedCell"><?= htmlspecialchars(t('album.card.ratedOf', ['rated' => $rated, 'total' => $albumTrackCount])) ?></td>
						</tr>
					<?php endforeach ?>
				</tbody>
			</table>
		</dd>
	<?php endif ?>
	<?php if ($albumGraphRaters && $albumTracks): ?>
		<?php
			/* One dot per score, drawn straight rather than through a charting
			   library: the page ships no build step and the card is rendered
			   server-side, so the svg is the cheapest thing that works on both
			   pages. Every rater's dot for a track sits on that track's line,
			   so a column can be read down as "what everyone thought of this
			   one" - the cost is that identical scores land on top of each
			   other. Dots are joined only across tracks the same rater scored;
			   a line through a gap would invent a score nobody gave. */
			$graphPadLeft = 22;
			$graphPadTop = 8;
			$graphPlotHeight = 130;
			$graphSlot = 24;
			$graphWidth = $graphPadLeft + count($albumTracks) * $graphSlot + 6;
			$graphHeight = $graphPadTop + $graphPlotHeight + 16;

			$graphX = function ($index) use ($graphPadLeft, $graphSlot) {
				return round($graphPadLeft + ($index + 0.5) * $graphSlot, 2);
			};
			$graphY = function ($score) use ($graphPadTop, $graphPlotHeight) {
				return round($graphPadTop + (10 - max(0, min(10, (float)$score))) / 10 * $graphPlotHeight, 2);
			};

			$graphLabelEvery = count($albumTracks) > 20 ? 2 : 1;

			/* The orders the graph can be drawn in. Each carries the direction
			   it naturally reads in - a record has a running order and a name
			   has an alphabet, but a score is interesting from the top - and
			   the direction control starts there and can be flipped. Only the
			   graph reorders: the track table above stays in the album's
			   running order, which is the one thing about a record that isn't
			   a statistic. */
			/// Written out rather than reusing the table's column headers: those
			/// are abbreviated because a six-column table has no room, and a
			/// dropdown has nothing but room. "Avg" is a fine heading and a
			/// poor thing to pick from a list.
			$graphOrders = [
				'album' => ['label' => t('album.card.sortAlbum'), 'dir' => 'asc'],
				'title' => ['label' => t('album.card.sortTitle'), 'dir' => 'asc'],
				'average' => ['label' => t('album.card.sortAverage'), 'dir' => 'desc'],
				'deviation' => ['label' => t('album.card.sortDeviation'), 'dir' => 'desc'],
				'median' => ['label' => t('album.card.sortMedian'), 'dir' => 'desc'],
				'mode' => ['label' => t('album.card.sortMode'), 'dir' => 'desc'],
				'rated' => ['label' => t('album.card.sortRated'), 'dir' => 'desc'],
				'duration' => ['label' => t('album.card.sortDuration'), 'dir' => 'desc'],
			];

			foreach ($albumGraphRaters as $graphRater) {
				$graphOrders['rater_' . (int)$graphRater['id']] = [
					'label' => t('album.card.sortRater', ['name' => $graphRater['account_name']]),
					'dir' => 'desc',
				];
			}

			/// A direction it doesn't recognise falls back to the order's own,
			/// rather than being coerced to one of the two - coercing turns
			/// "I don't understand this" into "reverse the album", which is a
			/// visible change nobody asked for.
			$graphSort = isset($graphOrders[$album['graphSort'] ?? '']) ? $album['graphSort'] : 'album';
			$graphDir = in_array($album['graphDir'] ?? '', ['asc', 'desc'], true)
				? $album['graphDir']
				: $graphOrders[$graphSort]['dir'];

			/// A track nobody scored sinks to the end whichever way round the
			/// sort is pointing: an empty column is not a low score, and it
			/// would otherwise lead every descending order that has no value
			/// for it.
			$graphValue = function ($track) use ($graphSort, $albumTrackScores) {
				if (strpos($graphSort, 'rater_') === 0) {
					return $albumTrackScores[(int)$track['song_id']][(int)substr($graphSort, 6)] ?? null;
				}

				switch ($graphSort) {
					case 'title': return $track['title'] ?? '';
					case 'average': return $track['average'];
					case 'deviation': return $track['deviation'];
					case 'median': return $track['median'];
					case 'mode': return ($track['modes'] ?? []) ? max($track['modes']) : null;
					case 'rated': return (int)($track['rated'] ?? 0);
					case 'duration': return $track['duration'] === null ? null : (int)$track['duration'];
				}

				return $track['graphIndex'];
			};

			$albumGraphTracks = [];
			foreach ($albumTracks as $graphIndex => $graphTrack) {
				$graphTrack['graphIndex'] = $graphIndex;
				$albumGraphTracks[] = $graphTrack;
			}

			usort($albumGraphTracks, function ($a, $b) use ($graphValue, $graphDir) {
				$left = $graphValue($a);
				$right = $graphValue($b);

				if ($left === null || $right === null) {
					return $left === $right ? $a['graphIndex'] <=> $b['graphIndex'] : ($left === null ? 1 : -1);
				}

				$order = is_string($left) ? strcasecmp($left, $right) : $left <=> $right;

				/// Ties keep the running order, so flipping the direction never
				/// shuffles tracks the sort has nothing to say about.
				return $order === 0
					? $a['graphIndex'] <=> $b['graphIndex']
					: ($graphDir === 'desc' ? -$order : $order);
			});
		?>
		<dt class="albumGraphHeading"><?= htmlspecialchars(t('album.card.graph')) ?></dt>
		<dd class="albumGraphCell">
			<div class="albumGraphSort">
				<label for="albumGraphSortKey"><?= htmlspecialchars(t('album.card.sortBy')) ?></label>
				<select id="albumGraphSortKey" class="albumGraphSortKey">
					<?php foreach ($graphOrders as $graphKey => $graphOrder): ?>
						<option value="<?= htmlspecialchars($graphKey) ?>" data-default-dir="<?= $graphOrder['dir'] ?>"<?= $graphKey === $graphSort ? ' selected' : '' ?>><?= htmlspecialchars($graphOrder['label']) ?></option>
					<?php endforeach ?>
				</select>
				<select id="albumGraphSortDir" class="albumGraphSortDir">
					<option value="desc"<?= $graphDir === 'desc' ? ' selected' : '' ?>><?= htmlspecialchars(t('album.card.sortDescending')) ?></option>
					<option value="asc"<?= $graphDir === 'asc' ? ' selected' : '' ?>><?= htmlspecialchars(t('album.card.sortAscending')) ?></option>
				</select>
			</div>
			<svg class="albumGraph" viewBox="0 0 <?= $graphWidth ?> <?= $graphHeight ?>" preserveAspectRatio="xMidYMid meet" role="img" aria-label="<?= htmlspecialchars(t('album.card.graphLabel')) ?>">
				<?php for ($score = 0; $score <= 10; $score++): ?>
					<?php if ($score % 2 === 0 || $score === 5): ?>
						<line class="albumGraphGrid<?= $score % 5 === 0 ? ' albumGraphGridMark' : '' ?>" x1="<?= $graphPadLeft ?>" y1="<?= $graphY($score) ?>" x2="<?= $graphWidth - 6 ?>" y2="<?= $graphY($score) ?>"></line>
					<?php endif ?>
					<?php if ($score % 5 === 0): ?>
						<text class="albumGraphAxis" x="<?= $graphPadLeft - 4 ?>" y="<?= $graphY($score) + 3 ?>" text-anchor="end"><?= $score ?></text>
					<?php endif ?>
				<?php endfor ?>
				<?php foreach ($albumGraphTracks as $index => $track): ?>
					<?php if ($index % $graphLabelEvery === 0): ?>
						<text class="albumGraphAxis" x="<?= $graphX($index) ?>" y="<?= $graphHeight - 4 ?>" text-anchor="middle"><?= $track['position'] === null ? $track['graphIndex'] + 1 : (int)$track['position'] ?></text>
					<?php endif ?>
				<?php endforeach ?>
				<?php foreach ($albumGraphRaters as $rater): ?>
					<?php
						$raterId = (int)$rater['id'];
						$colour = $albumRaterColours[$raterId];
						$runs = [];
						$run = [];
						foreach ($albumGraphTracks as $index => $track) {
							$score = $albumTrackScores[(int)$track['song_id']][$raterId] ?? null;
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
					<?php foreach ($albumGraphTracks as $index => $track): ?>
						<?php $score = $albumTrackScores[(int)$track['song_id']][$raterId] ?? null ?>
						<?php if ($score !== null): ?>
							<circle class="albumGraphDot" cx="<?= $graphX($index) ?>" cy="<?= $graphY($score) ?>" r="3" fill="<?= $colour ?>">
								<title><?= htmlspecialchars(t('album.card.graphPoint', [
									'name' => $rater['account_name'],
									'track' => ($track['title'] ?? '') === '' ? ($track['position'] === null ? $track['graphIndex'] + 1 : (int)$track['position']) : $track['title'],
									'score' => $albumScoreText($score),
								])) ?></title>
							</circle>
						<?php endif ?>
					<?php endforeach ?>
				<?php endforeach ?>
				<?php foreach ($albumGraphTracks as $index => $track): ?>
					<?php
						/* One invisible column per track, drawn last so it sits
						   over the dots and catches the pointer anywhere down
						   the track's line - aiming at a 3px dot to find out
						   what it is defeats the point. It carries a <title>
						   as well as the data-tooltip, so the same text is
						   there with no javascript; tooltip.js removes the
						   title when it takes over, or the browser's own
						   tooltip turns up a second later underneath ours. */
						$columnLines = [($track['title'] ?? '') === ''
							? ($track['position'] === null ? $track['graphIndex'] + 1 : (int)$track['position'])
							: $track['title']];

						foreach ($albumGraphRaters as $columnRater) {
							$columnScore = $albumTrackScores[(int)$track['song_id']][(int)$columnRater['id']] ?? null;
							$columnLines[] = t('album.card.tooltipScore', [
								'name' => $columnRater['account_name'],
								'score' => $columnScore === null ? t('album.card.noAverage') : $albumScoreText($columnScore),
							]);
						}

						$columnText = implode("\n", $columnLines);
					?>
					<rect class="albumGraphColumn" x="<?= round($graphPadLeft + $index * $graphSlot, 2) ?>" y="<?= $graphPadTop ?>" width="<?= $graphSlot ?>" height="<?= $graphPlotHeight ?>" data-tooltip="<?= htmlspecialchars($columnText) ?>">
						<title><?= htmlspecialchars($columnText) ?></title>
					</rect>
				<?php endforeach ?>
			</svg>
		</dd>
	<?php endif ?>
	<dd class="albumCardStatus" data-field="status"></dd>
	<dd class="albumCardActions">
		<a class="albumCardSongsLink" href="<?= htmlspecialchars(albumSongsHref($album['id'], $albumArtistId)) ?>"><?= t('album.card.showSongs') ?></a>
	</dd>
</dl>
