<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('song.list.heading') ?>
</h1>

<?php if (!$globalData['songs']): ?>
	<p><?= t('song.list.empty') ?></p>
<?php else: ?>
	<p id="songEditHint"><?= $globalData['isAdmin'] ? t('song.list.editHintAdmin') : t('song.list.editHint') ?></p>
	<table id="songListTable" class="hideResultColumn"<?= $globalData['isAdmin'] ? ' data-can-edit="1"' : '' ?>>
		<thead>
			<tr>
				<?php foreach ($globalData['columns'] as $column): ?>
					<?php if (isset($column['group'])): ?>
						<?php if ($column['groupStart'] ?? false): ?>
							<th colspan="2" class="<?= $column['groupClass'] ?>"><?= htmlspecialchars($column['groupLabel']) ?></th>
						<?php endif ?>
					<?php else: ?>
						<th rowspan="2" class="<?= $column['class'] ?>" data-sort-key="<?= $column['key'] ?>" data-sort-type="<?= $column['type'] ?>" data-sort-index="<?= $column['index'] ?>">
							<a href="<?= htmlspecialchars($column['link']) ?>" title="<?= htmlspecialchars($column['title']) ?>"><?= htmlspecialchars($column['label'] . $column['indicator']) ?></a>
						</th>
					<?php endif ?>
				<?php endforeach ?>
				<th rowspan="2" class="songResultCell"><?= t('song.column.result') ?></th>
			</tr>
			<tr>
				<?php foreach ($globalData['columns'] as $column): ?>
					<?php if (isset($column['group'])): ?>
						<th class="<?= $column['class'] ?>" data-sort-key="<?= $column['key'] ?>" data-sort-type="<?= $column['type'] ?>" data-sort-index="<?= $column['index'] ?>">
							<a href="<?= htmlspecialchars($column['link']) ?>" title="<?= htmlspecialchars($column['title']) ?>"><?= htmlspecialchars($column['label'] . $column['indicator']) ?></a>
						</th>
					<?php endif ?>
				<?php endforeach ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($globalData['songs'] as $song): ?>
				<tr>
					<td class="songIdCell"><?= htmlspecialchars($song['id']) ?></td>
					<td class="songArtistCell"><?= htmlspecialchars($song['artist'] ?? '') ?></td>
					<td class="songTitleCell"><?= htmlspecialchars($song['title']) ?></td>
					<?php if ($globalData['showSharedNote']): ?>
						<td class="songNoteCell"><?= htmlspecialchars($song['objective_note'] ?? '') ?></td>
					<?php endif ?>
					<?php foreach ($globalData['raters'] as $rater): ?>
						<?php
							$score = $song['score_' . (int)$rater['id']];
							$note = $song['note_' . (int)$rater['id']] ?? '';
						?>
						<td class="<?= $rater['scoreClass'] ?>"><?= htmlspecialchars($score === null ? '' : (float)$score) ?></td>
						<td class="<?= $rater['noteClass'] ?>" title="<?= htmlspecialchars($note) ?>"><span class="ratingNoteText"><?= htmlspecialchars($note) ?></span></td>
					<?php endforeach ?>
					<td class="songResultCell"></td>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>
	<script src="/modules/music/js/songs.js"></script>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
