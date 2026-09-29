<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<?php require_once __MODULES__ . '/music/views/partials/scorePresentation.php' ?>

<h1>
	<?= t('album.list.heading') ?>
</h1>

<?php if (!$globalData['albums']): ?>
	<p><?= t('album.list.empty') ?></p>
<?php else: ?>
	<table id="albumListTable">
		<thead>
			<tr>
				<?php foreach ($globalData['columns'] as $column): ?>
					<th class="<?= $column['class'] ?>" data-sort-key="<?= $column['key'] ?>">
						<a href="<?= htmlspecialchars($column['link']) ?>" title="<?= htmlspecialchars($column['title']) ?>"><?= htmlspecialchars($column['label'] . $column['indicator']) ?></a>
					</th>
				<?php endforeach ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($globalData['albums'] as $album): ?>
				<tr>
					<td class="listIdCell"><?= htmlspecialchars($album['id']) ?></td>
					<td class="listNameCell"><a href="<?= htmlspecialchars(albumSongsHref($album['id'], $album['artist_id'])) ?>" data-album-card-id="<?= (int)$album['id'] ?>"><?= $album['name'] === null ? t('album.list.noName') : htmlspecialchars($album['name']) ?></a></td>
					<td class="listAliasCell"><?= htmlspecialchars($album['aliases'] ?? '') ?></td>
					<td class="listArtistCell"><?= $album['artist'] === null ? t('album.list.noArtist') : htmlspecialchars($album['artist']) ?></td>
					<td class="listYearCell"><?= htmlspecialchars($album['release_year'] ?? '') ?></td>
					<td class="listCountCell"><?= (int)$album['tracks'] ?></td>
					<td class="listDurationCell"><?= htmlspecialchars(musicDuration($album['duration'])) ?></td>
					<?= scoreStatCells($album, ['average', 'deviation', 'median', 'mode', 'highest', 'lowest']) ?>
					<td class="albumStatsRatedCell"><?= htmlspecialchars(t('album.card.ratedOf', ['rated' => (int)$album['rated'], 'total' => (int)$album['possible']])) ?></td>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>
	<?php require(__MODULES__ . '/music/views/partials/albumCardModal.php') ?>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
