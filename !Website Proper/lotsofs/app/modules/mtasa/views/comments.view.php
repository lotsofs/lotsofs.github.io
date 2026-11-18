<?php require(__MODULES__ . '/mtasa/views/partials/head.php') ?>

<h1><?= t('comments.heading') ?></h1>

<p>
	<?php if ($globalData['includeResolved']): ?>
		<a href="/mtasa/comments"><?= t('comments.showOpen') ?></a>
	<?php else: ?>
		<a href="/mtasa/comments?resolved=1"><?= t('comments.showAll') ?></a>
	<?php endif ?>
</p>

<?php if (!$globalData['recent']): ?>
	<p><?= t($globalData['includeResolved'] ? 'comments.noneAtAll' : 'comments.noneOpen') ?></p>
<?php else: ?>
	<div class="mtasaListScroll">
		<table class="mtasaList" id="mtasaRecent">
			<thead>
				<tr>
					<th><?= t('comments.column.map') ?></th>
					<th><?= t('comments.column.field') ?></th>
					<th><?= t('comments.column.comment') ?></th>
					<th><?= t('comments.column.author') ?></th>
					<th><?= t('comments.column.replies') ?></th>
					<th><?= t('comments.column.active') ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ($globalData['recent'] as $thread): ?>
					<?php
					$mapId = (int)$thread['map_id'];
					$anchor = $thread['field'] === null ? 'comments' : 'field-' . $thread['field'];
					$body = $thread['deleted_at'] !== null ? t('comments.deleted') : $thread['body'];
					/// Cut by character, not byte: half a character makes htmlspecialchars() return nothing.
					$excerpt = preg_replace('/^(.{137}).{4,}$/su', '$1…', $body);
					?>
					<tr data-thread-id="<?= (int)$thread['id'] ?>"<?= $thread['resolved_at'] !== null ? ' class="mtasaThreadResolved"' : '' ?>>
						<td><a href="/mtasa/map?id=<?= $mapId ?>#<?= $anchor ?>"><?= $mapId ?>: <?= htmlspecialchars($thread['map_name'] ?? 'z(' . $mapId . ')') ?></a></td>
						<td><?= htmlspecialchars(mtasaThreadFieldLabel($thread['field'])) ?></td>
						<td class="mtasaExcerpt"><?= htmlspecialchars($excerpt) ?></td>
						<td><?= htmlspecialchars($thread['author_name'] ?? t('comments.deletedAccount')) ?></td>
						<td class="mtasaNumber"><?= (int)$thread['reply_count'] ?></td>
						<td><?= date('Y-m-d H:i', (int)$thread['active_at']) ?><?= $thread['resolved_at'] !== null ? ' · ' . t('comments.resolved') : '' ?></td>
					</tr>
				<?php endforeach ?>
			</tbody>
		</table>
	</div>
<?php endif ?>

<?php require(__MODULES__ . '/mtasa/views/partials/foot.php') ?>
