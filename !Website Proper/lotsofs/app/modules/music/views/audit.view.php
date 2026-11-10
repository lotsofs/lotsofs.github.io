<?php require_once __MODULES__ . '/music/editAudit.php' ?>
<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('audit.heading') ?>
</h1>

<?php if (!$globalData['auditEntries']): ?>
	<p><?= t('audit.empty') ?></p>
<?php else: ?>
	<?php $noValue = htmlspecialchars(t('audit.noValue')) ?>
	<?php $fieldLabels = musicAuditFieldLabels() ?>
	<table id="auditTable">
		<thead>
			<tr>
				<th class="auditWhenCell"><?= t('audit.column.when') ?></th>
				<th class="auditWhoCell"><?= t('audit.column.who') ?></th>
				<th class="auditSubjectCell"><?= t('audit.column.subject') ?></th>
				<th class="auditFieldCell"><?= t('audit.column.field') ?></th>
				<th class="auditValueCell"><?= t('audit.column.from') ?></th>
				<th class="auditValueCell"><?= t('audit.column.to') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($globalData['auditEntries'] as $entry): ?>
				<?php $previous = musicAuditValueText($entry['field'], $entry['previous_value']) ?>
				<?php $value = musicAuditValueText($entry['field'], $entry['value']) ?>
				<tr data-audit-id="<?= (int)$entry['id'] ?>" data-audit-source="<?= htmlspecialchars($entry['source']) ?>">
					<td class="auditWhenCell"><?= htmlspecialchars(musicDateTime($entry['created_at'])) ?></td>
					<td class="auditWhoCell"><?= htmlspecialchars($entry['account_name'] ?? '') ?></td>
					<td class="auditSubjectCell" title="<?= htmlspecialchars($entry['subject']) ?>"><?= htmlspecialchars($entry['subject']) ?></td>
					<td class="auditFieldCell"><?= htmlspecialchars($fieldLabels[$entry['field']] ?? $entry['field']) ?></td>
					<td class="auditValueCell" title="<?= htmlspecialchars($previous ?? '') ?>"><?= $previous === null ? $noValue : htmlspecialchars($previous) ?></td>
					<td class="auditValueCell" title="<?= htmlspecialchars($value ?? '') ?>"><?= $value === null ? $noValue : htmlspecialchars($value) ?></td>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>

	<p class="auditPaging">
		<?php if (!$globalData['auditIsFirstPage']): ?>
			<a href="/music/audit"><?= t('audit.newest') ?></a>
		<?php endif ?>
		<?php if ($globalData['auditHasOlder']): ?>
			<a href="/music/audit?before=<?= htmlspecialchars($globalData['auditOlderCursor']) ?>"><?= t('audit.older') ?></a>
		<?php endif ?>
	</p>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
