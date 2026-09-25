<?php require_once __MODULES__ . '/music/format.php' ?>
<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('audit.heading') ?>
</h1>

<?php if (!$globalData['auditEntries']): ?>
	<p><?= t('audit.empty') ?></p>
<?php else: ?>
	<?php $noValue = htmlspecialchars(t('audit.noValue')) ?>
	<table id="auditTable">
		<thead>
			<tr>
				<th class="auditWhenCell"><?= t('audit.column.when') ?></th>
				<th class="auditWhoCell"><?= t('audit.column.who') ?></th>
				<th class="auditSongCell"><?= t('audit.column.song') ?></th>
				<th class="auditFieldCell"><?= t('audit.column.field') ?></th>
				<th class="auditValueCell"><?= t('audit.column.from') ?></th>
				<th class="auditValueCell"><?= t('audit.column.to') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($globalData['auditEntries'] as $entry): ?>
				<?php $song = musicSongLabel($entry['artists'], $entry['song_title']) ?>
				<tr data-audit-id="<?= (int)$entry['id'] ?>">
					<td class="auditWhenCell"><?= htmlspecialchars(date('Y-m-d H:i:s', (int)$entry['created_at'])) ?></td>
					<td class="auditWhoCell"><?= htmlspecialchars($entry['account_name']) ?></td>
					<td class="auditSongCell" title="<?= htmlspecialchars($song) ?>"><?= htmlspecialchars($song) ?></td>
					<td class="auditFieldCell"><?= $entry['field'] === 'score' ? t('audit.field.score') : t('audit.field.note') ?></td>
					<td class="auditValueCell" title="<?= htmlspecialchars($entry['previous_value'] ?? '') ?>"><?= $entry['previous_value'] === null ? $noValue : htmlspecialchars($entry['previous_value']) ?></td>
					<td class="auditValueCell" title="<?= htmlspecialchars($entry['value'] ?? '') ?>"><?= $entry['value'] === null ? $noValue : htmlspecialchars($entry['value']) ?></td>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>

	<p class="auditPaging">
		<?php if (!$globalData['auditIsFirstPage']): ?>
			<a href="/music/audit"><?= t('audit.newest') ?></a>
		<?php endif ?>
		<?php if ($globalData['auditHasOlder']): ?>
			<a href="/music/audit?before=<?= (int)$globalData['auditOldestId'] ?>"><?= t('audit.older') ?></a>
		<?php endif ?>
	</p>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
