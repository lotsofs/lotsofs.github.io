<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('albumList.heading') ?>
</h1>

<?php if (!$globalData['albums']): ?>
	<p><?= t('albumList.empty') ?></p>
<?php else: ?>
	<table id="albumListTable">
		<thead>
			<tr>
				<th class="listIdCell"><?= t('albumList.column.id') ?></th>
				<th class="listNameCell"><?= t('albumList.column.name') ?></th>
				<th class="listAliasCell"><?= t('albumList.column.aliases') ?></th>
				<th class="listArtistCell"><?= t('albumList.column.artist') ?></th>
				<th class="listYearCell"><?= t('albumList.column.year') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($globalData['albums'] as $album): ?>
				<tr>
					<td class="listIdCell"><?= htmlspecialchars($album['id']) ?></td>
					<td class="listNameCell"><?= $album['name'] === null ? t('albumList.noName') : htmlspecialchars($album['name']) ?></td>
					<td class="listAliasCell"><?= htmlspecialchars($album['aliases'] ?? '') ?></td>
					<td class="listArtistCell"><?= $album['artist'] === null ? t('albumList.noArtist') : htmlspecialchars($album['artist']) ?></td>
					<td class="listYearCell"><?= htmlspecialchars($album['release_year'] ?? '') ?></td>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
