<?php
	require_once __MODULES__ . '/music/links.php';
	require_once __MODULES__ . '/music/hue.php';
	require_once __MODULES__ . '/music/format.php';
	require_once __MODULES__ . '/music/views/partials/scorePresentation.php';

	$albumName = ($album['name'] ?? '') === '' ? t('album.list.noName') : $album['name'];
	$albumArtistId = $album['artist_id'] === null ? null : (int)$album['artist_id'];
	$albumArtist = ($album['artist'] ?? '') === '' ? t('album.list.noArtist') : $album['artist'];
	$albumAliases = $album['aliases'] ?? '';
	$albumTracks = $album['tracks'] ?? [];
	$albumAverages = $album['averages'] ?? [];
	$albumTrackCount = $album['trackCount'] ?? count($albumTracks);
	$albumIsAdmin = $album['isAdmin'] ?? false;
	$albumTrackScores = $album['trackScores'] ?? [];
	$albumTotals = $album['totals'] ?? null;

	$albumRaterColours = scoreRaterColours($albumAverages);
	$albumGraphRaters = scoreActiveRaters($albumAverages);
	$albumStatsWho = $album['statsWho'] ?? null;
?>
<dl class="albumCard card" data-card-kind="album" data-card-id="<?= (int)$album['id'] ?>" data-album-id="<?= (int)$album['id'] ?>" data-artist-id="<?= $albumArtistId === null ? '' : $albumArtistId ?>" data-release-year="<?= htmlspecialchars($album['release_year'] ?? '') ?>" data-album-name="<?= htmlspecialchars($album['name'] ?? '') ?>">
	<div class="albumCardIdRow cardIdRow">
		<dd class="albumIdCell cardIdCell"><?= (int)$album['id'] ?></dd>
		<?php if ($albumIsAdmin): ?>
			<button type="button" class="albumCardEditBtn cardEditBtn"><?= htmlspecialchars(t('album.card.edit')) ?></button>
		<?php endif ?>
	</div>
	<dd class="albumTitleCell cardTitle" data-field="name"><?= htmlspecialchars($albumName) ?></dd>
	<div class="albumCardInfo">
		<dt><?= htmlspecialchars(t('album.column.artist')) ?></dt>
		<dd class="albumArtistCell" data-field="artist"><?php if ($albumArtistId !== null): ?><a class="songArtistLink" href="<?= htmlspecialchars('/music/songs?artist=' . $albumArtistId) ?>" data-artist-card-id="<?= $albumArtistId ?>"><?= htmlspecialchars($albumArtist) ?></a><?php else: ?><?= htmlspecialchars($albumArtist) ?><?php endif ?></dd>
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
			<?= scoreWhoSelect($albumGraphRaters, $albumStatsWho) ?>
			<table class="albumTrackTable">
				<thead>
					<tr>
						<th class="albumTrackPosition"><?= htmlspecialchars(t('album.card.trackNumber')) ?></th>
						<th class="albumTrackTitle"><?= htmlspecialchars(t('song.column.title')) ?></th>
						<?php if ($albumArtistId === null): ?>
							<th class="albumTrackArtist"><?= htmlspecialchars(t('song.column.artist')) ?></th>
						<?php endif ?>
						<?php if ($albumGraphRaters && $albumStatsWho !== null): ?>
							<th class="albumTrackScore"><?= htmlspecialchars(scoreWhoName($albumGraphRaters, $albumStatsWho)) ?></th>
						<?php elseif ($albumGraphRaters): ?>
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
							<?php if ($albumGraphRaters && $albumStatsWho !== null): ?>
								<?php $trackScore = $track['whoScore'] ?? null ?>
								<?php if ($trackScore === null): ?>
									<td class="albumTrackScore albumTrackScoreEmpty"><?= htmlspecialchars(t('album.card.noAverage')) ?></td>
								<?php else: ?>
									<td class="albumTrackScore"<?= scoreColourAttr($trackScore) ?>><?= htmlspecialchars(scoreText($trackScore)) ?></td>
								<?php endif ?>
							<?php elseif ($albumGraphRaters): ?>
								<?php $trackAverage = $track['average'] ?? null ?>
								<?php if ($trackAverage === null): ?>
									<td class="albumTrackScore albumTrackScoreEmpty"><?= htmlspecialchars(t('album.card.noAverage')) ?></td>
									<td class="albumTrackDeviation albumTrackScoreEmpty"><?= htmlspecialchars(t('album.card.noAverage')) ?></td>
								<?php else: ?>
									<td class="albumTrackScore"<?= scoreColourAttr($trackAverage) ?>><?= htmlspecialchars(scoreText($trackAverage)) ?></td>
									<td class="albumTrackDeviation"><?= htmlspecialchars(scoreText($track['deviation'])) ?></td>
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
		<?php
			$statsRows = $albumAverages;
			$statsTotals = $albumTotals;
			$statsColours = $albumRaterColours;
			$statsDenominator = $albumTrackCount;
			require __MODULES__ . '/music/views/partials/scoreStats.php';
		?>
	<?php endif ?>
	<?php if ($albumGraphRaters && $albumTracks): ?>
		<?php
			$graphSongs = $albumTracks;
			$graphScores = $albumTrackScores;
			$graphRaters = $albumGraphRaters;
			$graphColours = $albumRaterColours;
			$graphSortKey = $album['graphSort'] ?? '';
			$graphSortDir = $album['graphDir'] ?? '';
			/// A record has a running order, so the graph offers it.
			$graphNaturalLabel = t('album.card.sortAlbum');
			require __MODULES__ . '/music/views/partials/scoreGraph.php';
		?>
	<?php endif ?>
	<dd class="albumCardStatus" data-field="status"></dd>
	<dd class="albumCardActions">
		<a class="albumCardSongsLink" href="<?= htmlspecialchars(albumSongsHref($album['id'], $albumArtistId)) ?>"><?= t('album.card.showSongs') ?></a>
	</dd>
</dl>
