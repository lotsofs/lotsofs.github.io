<?php
	require_once __MODULES__ . '/music/links.php';
	require_once __MODULES__ . '/music/views/partials/scorePresentation.php';

	$artistName = ($artist['name'] ?? '') === '' ? t('artist.list.noName') : $artist['name'];
	$artistAliases = $artist['aliases'] ?? '';
	$artistSongs = $artist['songs'] ?? [];
	$artistAlbums = $artist['albums'] ?? [];
	$artistAverages = $artist['averages'] ?? [];
	$artistTotals = $artist['totals'] ?? null;
	$artistSongScores = $artist['songScores'] ?? [];
	$artistIsAdmin = $artist['isAdmin'] ?? false;

	$artistRaterColours = scoreRaterColours($artistAverages);
	$artistGraphRaters = scoreActiveRaters($artistAverages);
	$artistStatsWho = $artist['statsWho'] ?? null;
?>
<dl class="artistCard card" data-card-kind="artist" data-card-id="<?= (int)$artist['id'] ?>" data-artist-name="<?= htmlspecialchars($artist['name'] ?? '') ?>">
	<div class="artistCardIdRow cardIdRow">
		<dd class="artistIdCell cardIdCell"><?= (int)$artist['id'] ?></dd>
		<?php if ($artistIsAdmin): ?>
			<button type="button" class="artistCardEditBtn cardEditBtn"><?= htmlspecialchars(t('artist.card.edit')) ?></button>
		<?php endif ?>
	</div>
	<dd class="artistTitleCell cardTitle" data-field="name"><?= htmlspecialchars($artistName) ?></dd>
	<div class="albumCardInfo">
		<dt><?= htmlspecialchars(t('artist.card.songCount')) ?></dt>
		<dd class="artistSongCountCell"><?= (int)$artist['songCount'] ?></dd>
		<?php if ($artistAliases !== ''): ?>
			<dt><?= htmlspecialchars(t('album.column.aliases')) ?></dt>
			<dd class="artistAliasCell"><?= htmlspecialchars($artistAliases) ?></dd>
		<?php endif ?>
	</div>
	<?php if ($artistAlbums): ?>
		<dt class="artistAlbumHeading"><?= htmlspecialchars(t('artist.card.albums')) ?></dt>
		<dd class="artistAlbumCell">
			<?= scoreWhoSelect($artistGraphRaters, $artistStatsWho) ?>
			<table class="artistAlbumTable albumStatsTable">
				<thead>
					<tr>
						<th class="albumStatsNameCell"><?= htmlspecialchars(t('song.column.album')) ?></th>
						<th class="artistAlbumYearCell"><?= htmlspecialchars(t('album.column.year')) ?></th>
						<th class="albumStatsScoreCell"><?= htmlspecialchars(t('album.card.statAverage')) ?></th>
						<th class="albumStatsScoreCell"><?= htmlspecialchars(t('album.card.statDeviation')) ?></th>
						<th class="albumStatsScoreCell"><?= htmlspecialchars(t('album.card.statMedian')) ?></th>
						<th class="albumStatsScoreCell"><?= htmlspecialchars(t('album.card.statMode')) ?></th>
						<th class="albumStatsRatedCell"><?= htmlspecialchars(t('album.card.statRated')) ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($artistAlbums as $artistAlbum): ?>
						<tr class="artistAlbumRow albumStatsRow">
							<td class="albumStatsNameCell"><a class="songAlbumLink" href="<?= htmlspecialchars(albumSongsHref($artistAlbum['id'], $artist['id'])) ?>" data-album-card-id="<?= (int)$artistAlbum['id'] ?>"><?= htmlspecialchars(($artistAlbum['name'] ?? '') === '' ? t('album.list.noName') : $artistAlbum['name']) ?></a></td>
							<td class="artistAlbumYearCell"><?= htmlspecialchars($artistAlbum['release_year'] ?? '') ?></td>
							<?= scoreStatCells($artistAlbum) ?>
							<td class="albumStatsRatedCell"><?= htmlspecialchars(t('album.card.ratedOf', ['rated' => (int)($artistAlbum['rated'] ?? 0), 'total' => (int)($artistAlbum['possible'] ?? 0)])) ?></td>
						</tr>
					<?php endforeach ?>
				</tbody>
			</table>
		</dd>
	<?php endif ?>
	<?php if ($artistAverages): ?>
		<?php
			$statsRows = $artistAverages;
			$statsTotals = $artistTotals;
			$statsColours = $artistRaterColours;
			$statsDenominator = (int)$artist['songCount'];
			require __MODULES__ . '/music/views/partials/scoreStats.php';
		?>
	<?php endif ?>
	<?php if ($artistGraphRaters && $artistSongs): ?>
		<?php
			$graphSongs = $artistSongs;
			$graphScores = $artistSongScores;
			$graphRaters = $artistGraphRaters;
			$graphColours = $artistRaterColours;
			$graphSortKey = $artist['graphSort'] ?? '';
			$graphSortDir = $artist['graphDir'] ?? '';
			/// An artist's songs are a set, so there is no natural order to offer.
			$graphNaturalLabel = null;
			require __MODULES__ . '/music/views/partials/scoreGraph.php';
		?>
	<?php endif ?>
	<dd class="albumCardStatus" data-field="status"></dd>
	<dd class="albumCardActions">
		<a class="albumCardSongsLink" href="<?= htmlspecialchars('/music/songs?artist=' . (int)$artist['id']) ?>"><?= t('artist.card.showSongs') ?></a>
	</dd>
</dl>
