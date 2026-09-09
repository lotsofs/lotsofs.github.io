<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('album.list.heading') ?>
</h1>

<?php if (!$globalData['albums']): ?>
	<p><?= t('album.list.empty') ?></p>
<?php else: ?>
	<table id="albumListTable">
		<thead>
			<tr>
				<th class="listIdCell"><?= t('album.column.id') ?></th>
				<th class="listNameCell"><?= t('album.column.name') ?></th>
				<th class="listAliasCell"><?= t('album.column.aliases') ?></th>
				<th class="listArtistCell"><?= t('album.column.artist') ?></th>
				<th class="listYearCell"><?= t('album.column.year') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($globalData['albums'] as $album): ?>
				<tr>
					<td class="listIdCell"><?= htmlspecialchars($album['id']) ?></td>
					<td class="listNameCell"><?= $album['name'] === null ? t('album.list.noName') : htmlspecialchars($album['name']) ?></td>
					<td class="listAliasCell"><?= htmlspecialchars($album['aliases'] ?? '') ?></td>
					<td class="listArtistCell"><?= $album['artist'] === null ? t('album.list.noArtist') : htmlspecialchars($album['artist']) ?></td>
					<td class="listYearCell"><?= htmlspecialchars($album['release_year'] ?? '') ?></td>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
