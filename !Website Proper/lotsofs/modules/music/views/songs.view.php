<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('songs.heading') ?>
</h1>

<?php if (!$globalData['songs']): ?>
	<p><?= t('songs.empty') ?></p>
<?php else: ?>
	<?php if ($globalData['isAdmin']): ?>
		<p id="songEditHint"><?= t('songs.editHint') ?></p>
	<?php endif ?>
	<table id="songListTable" class="hideResultColumn"<?= $globalData['isAdmin'] ? ' data-can-edit="1"' : '' ?>>
		<thead>
			<tr>
				<?php foreach ($globalData['columns'] as $column): ?>
					<th class="<?= $column['class'] ?>" data-sort-key="<?= $column['key'] ?>" data-sort-type="<?= $column['type'] ?>">
						<a href="<?= htmlspecialchars($column['link']) ?>" title="<?= htmlspecialchars($column['title']) ?>"><?= htmlspecialchars($column['label'] . $column['indicator']) ?></a>
					</th>
				<?php endforeach ?>
				<?php if ($globalData['isAdmin']): ?>
					<th class="songResultCell"><?= t('songs.column.result') ?></th>
				<?php endif ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($globalData['songs'] as $song): ?>
				<tr>
					<td class="songIdCell"><?= htmlspecialchars($song['id']) ?></td>
					<td class="songArtistCell"><?= htmlspecialchars($song['artist'] ?? '') ?></td>
					<td class="songTitleCell"><?= htmlspecialchars($song['title']) ?></td>
					<td class="songNoteCell"><?= htmlspecialchars($song['objective_note'] ?? '') ?></td>
					<td class="songScoreCell"><?= htmlspecialchars($song['score'] ?? '') ?></td>
					<?php if ($globalData['isAdmin']): ?>
						<td class="songResultCell"></td>
					<?php endif ?>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>
	<script src="/modules/music/js/songs.js"></script>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
