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
		?>
		<dt class="albumGraphHeading"><?= htmlspecialchars(t('album.card.graph')) ?></dt>
		<dd class="albumGraphCell">
			<svg class="albumGraph" viewBox="0 0 <?= $graphWidth ?> <?= $graphHeight ?>" preserveAspectRatio="xMidYMid meet" role="img" aria-label="<?= htmlspecialchars(t('album.card.graphLabel')) ?>">
				<?php for ($score = 0; $score <= 10; $score++): ?>
					<?php if ($score % 2 === 0 || $score === 5): ?>
						<line class="albumGraphGrid<?= $score % 5 === 0 ? ' albumGraphGridMark' : '' ?>" x1="<?= $graphPadLeft ?>" y1="<?= $graphY($score) ?>" x2="<?= $graphWidth - 6 ?>" y2="<?= $graphY($score) ?>"></line>
					<?php endif ?>
					<?php if ($score % 5 === 0): ?>
						<text class="albumGraphAxis" x="<?= $graphPadLeft - 4 ?>" y="<?= $graphY($score) + 3 ?>" text-anchor="end"><?= $score ?></text>
					<?php endif ?>
				<?php endfor ?>
				<?php foreach ($albumTracks as $index => $track): ?>
					<?php if ($index % $graphLabelEvery === 0): ?>
						<text class="albumGraphAxis" x="<?= $graphX($index) ?>" y="<?= $graphHeight - 4 ?>" text-anchor="middle"><?= $track['position'] === null ? $index + 1 : (int)$track['position'] ?></text>
					<?php endif ?>
				<?php endforeach ?>
				<?php foreach ($albumGraphRaters as $rater): ?>
					<?php
						$raterId = (int)$rater['id'];
						$colour = $albumRaterColours[$raterId];
						$runs = [];
						$run = [];
						foreach ($albumTracks as $index => $track) {
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
					<?php foreach ($albumTracks as $index => $track): ?>
						<?php $score = $albumTrackScores[(int)$track['song_id']][$raterId] ?? null ?>
						<?php if ($score !== null): ?>
							<circle class="albumGraphDot" cx="<?= $graphX($index) ?>" cy="<?= $graphY($score) ?>" r="3" fill="<?= $colour ?>">
								<title><?= htmlspecialchars(t('album.card.graphPoint', [
									'name' => $rater['account_name'],
									'track' => ($track['title'] ?? '') === '' ? ($track['position'] === null ? $index + 1 : (int)$track['position']) : $track['title'],
									'score' => $albumScoreText($score),
								])) ?></title>
							</circle>
						<?php endif ?>
					<?php endforeach ?>
				<?php endforeach ?>
			</svg>
		</dd>
	<?php endif ?>
	<dd class="albumCardStatus" data-field="status"></dd>
	<dd class="albumCardActions">
		<a class="albumCardSongsLink" href="<?= htmlspecialchars(albumSongsHref($album['id'], $albumArtistId)) ?>"><?= t('album.card.showSongs') ?></a>
	</dd>
</dl>
