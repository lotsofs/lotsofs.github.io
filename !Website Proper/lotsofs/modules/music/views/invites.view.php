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
				<th><?= t('invites.column.action') ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ($globalData['invites'] as $invite): ?>
				<tr>
					<td><?= htmlspecialchars(formatInviteCode($invite['code'])) ?></td>
					<td><?= htmlspecialchars($invite['created_at']) ?></td>
					<td>
						<?php if ($invite['used_at']): ?>
							<?= htmlspecialchars(t('invites.usedBy', ['name' => $invite['used_by'] ?? ''])) ?>
						<?php elseif ($invite['revoked_at']): ?>
							<?= t('invites.revoked') ?>
						<?php else: ?>
							<?= t('invites.unused') ?>
						<?php endif ?>
					</td>
					<td>
						<?php if (!$invite['used_at'] && !$invite['revoked_at']): ?>
							<form method="post" action="/music/invites">
								<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
								<input type="hidden" name="action" value="revoke">
								<input type="hidden" name="invite_id" value="<?= (int)$invite['id'] ?>">
								<button type="submit"><?= t('invites.revoke') ?></button>
							</form>
						<?php endif ?>
					</td>
				</tr>
			<?php endforeach ?>
		</tbody>
	</table>
<?php endif ?>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
