<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<?php require_once __MODULES__ . '/music/views/partials/scorePresentation.php' ?>

<h1>
	<?= t('artist.list.heading') ?>
</h1>

<?php if (!$globalData['artists']): ?>
	<p><?= t('artist.list.empty') ?></p>
<?php else: ?>
	<table id="artistListTable">
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
			<?php foreach ($globalData['artists'] as $artist): ?>
				<tr>
					<td class="listIdCell"><?= htmlspecialchars($artist['id']) ?></td>
					<td class="listNameCell"><a href="/music/songs?artist=<?= (int)$artist['id'] ?>" data-artist-card-id="<?= (int)$artist['id'] ?>"><?= $artist['name'] === null ? t('artist.list.noName') : htmlspecialchars($artist['name']) ?></a></td>
					<td class="listAliasCell"><?= htmlspecialchars($artist['aliases'] ?? '') ?></td>
					<td class="listCountCell"><?= (int)$artist['songs'] ?></td>
					<td class="listCountCell"><?= (int)$artist['albums'] ?></td>
					<?= scoreStatCells($artist, ['average', 'deviation', 'median', 'mode', 'highest', 'lowest']) ?>
					<td class="albumStatsRatedCell"><?= htmlspecialchars(t('album.card.ratedOf', ['rated' => (int)$artist['rated'], 'total' => (int)$artist['possible']])) ?></td>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>
	<?php require(__MODULES__ . '/music/views/partials/albumCardModal.php') ?>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
