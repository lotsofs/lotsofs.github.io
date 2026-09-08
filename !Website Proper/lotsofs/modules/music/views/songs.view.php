<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('songs.heading') ?>
</h1>

<?php if (!$globalData['songs']): ?>
	<p><?= t('songs.empty') ?></p>
<?php else: ?>
	<table id="songListTable">
		<thead>
			<tr>
				<th class="songIdCell"><?= t('songs.column.id') ?></th>
				<th class="songArtistCell"><?= t('songs.column.artist') ?></th>
				<th class="songTitleCell"><?= t('songs.column.title') ?></th>
				<th class="songNoteCell"><?= t('songs.column.note') ?></th>
				<th class="songScoreCell"><?= t('songs.column.score') ?></th>
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
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
