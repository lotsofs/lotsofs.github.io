<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('artist.list.heading') ?>
</h1>

<?php if (!$globalData['artists']): ?>
	<p><?= t('artist.list.empty') ?></p>
<?php else: ?>
	<table id="artistListTable">
		<thead>
			<tr>
				<th class="listIdCell"><?= t('artist.column.id') ?></th>
				<th class="listNameCell"><?= t('artist.column.name') ?></th>
				<th class="listAliasCell"><?= t('artist.column.aliases') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($globalData['artists'] as $artist): ?>
				<tr>
					<td class="listIdCell"><?= htmlspecialchars($artist['id']) ?></td>
					<td class="listNameCell"><a href="/music/songs?artist=<?= (int)$artist['id'] ?>"><?= $artist['name'] === null ? t('artist.list.noName') : htmlspecialchars($artist['name']) ?></a></td>
					<td class="listAliasCell"><?= htmlspecialchars($artist['aliases'] ?? '') ?></td>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
