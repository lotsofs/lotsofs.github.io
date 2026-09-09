<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('artistList.heading') ?>
</h1>

<?php if (!$globalData['artists']): ?>
	<p><?= t('artistList.empty') ?></p>
<?php else: ?>
	<table id="artistListTable">
		<thead>
			<tr>
				<th class="listIdCell"><?= t('artistList.column.id') ?></th>
				<th class="listNameCell"><?= t('artistList.column.name') ?></th>
				<th class="listAliasCell"><?= t('artistList.column.aliases') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($globalData['artists'] as $artist): ?>
				<tr>
					<td class="listIdCell"><?= htmlspecialchars($artist['id']) ?></td>
					<td class="listNameCell"><?= $artist['name'] === null ? t('artistList.noName') : htmlspecialchars($artist['name']) ?></td>
					<td class="listAliasCell"><?= htmlspecialchars($artist['aliases'] ?? '') ?></td>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
