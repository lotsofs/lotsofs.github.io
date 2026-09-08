<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('invites.heading') ?>
</h1>

<form method="post" action="/music/invites">
	<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
	<button type="submit"><?= t('invites.create') ?></button>
</form>

<?php if (!$globalData['invites']): ?>
	<p><?= t('invites.empty') ?></p>
<?php else: ?>
	<table id="inviteTable">
		<thead>
			<tr>
				<th><?= t('invites.column.code') ?></th>
				<th><?= t('invites.column.created') ?></th>
				<th><?= t('invites.column.used') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($globalData['invites'] as $invite): ?>
				<tr>
					<td><?= htmlspecialchars($invite['code']) ?></td>
					<td><?= htmlspecialchars($invite['created_at']) ?></td>
					<td>
						<?= $invite['used_at']
							? htmlspecialchars(t('invites.usedBy', ['name' => $invite['used_by'] ?? '']))
							: t('invites.unused') ?>
					</td>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
