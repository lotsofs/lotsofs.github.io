<?php require(__MODULES__ . '/hideandseek/views/partials/head.php') ?>

<h1><?= t('accounts.heading') ?></h1>

<table id="accountTable">
	<thead>
		<tr>
			<th><?= t('accounts.column.name') ?></th>
			<th><?= t('accounts.column.admin') ?></th>
			<th><?= t('accounts.column.action') ?></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($globalData['accounts'] as $account): ?>
			<?php $isSelf = (int)$account['id'] === $globalData['accountId'] ?>
			<tr>
				<td><?= htmlspecialchars($account['account_name']) ?><?= $isSelf ? ' (' . t('accounts.self') . ')' : '' ?></td>
				<td><?= $account['is_admin'] ? t('accounts.isAdmin') : t('accounts.notAdmin') ?></td>
				<td>
					<?php if (!$isSelf): ?>
						<form method="post" action="/hideandseek/accounts">
							<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
							<input type="hidden" name="account_id" value="<?= (int)$account['id'] ?>">
							<input type="hidden" name="action" value="<?= $account['is_admin'] ? 'demote' : 'promote' ?>">
							<button type="submit"><?= $account['is_admin'] ? t('accounts.demote') : t('accounts.promote') ?></button>
						</form>
					<?php endif ?>
				</td>
			</tr>
		<?php endforeach ?>
	</tbody>
</table>

<h2 id="invites"><?= t('invites.heading') ?></h2>

<form method="post" action="/hideandseek/accounts">
	<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
	<input type="hidden" name="action" value="invite">
	<button type="submit"><?= t('invites.create') ?></button>
</form>

<?php if (!$globalData['invites']): ?>
	<p><?= t('invites.empty') ?></p>
<?php else: ?>
	<table id="inviteTable" data-tooltip-titles>
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
					<td><?= htmlspecialchars(hnsFormatInviteCode($invite['code'])) ?></td>
					<td title="<?= htmlspecialchars($invite['created_at']) ?>"><?= htmlspecialchars(substr($invite['created_at'], 0, 10)) ?></td>
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
							<form method="post" action="/hideandseek/accounts">
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

<?php require(__MODULES__ . '/hideandseek/views/partials/foot.php') ?>
