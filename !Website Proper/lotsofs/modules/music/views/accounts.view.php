<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('accounts.heading') ?>
</h1>

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
			<tr>
				<td><?= htmlspecialchars($account['account_name']) ?></td>
				<td><?= $account['is_admin'] ? t('accounts.isAdmin') : t('accounts.notAdmin') ?></td>
				<td>
					<?php if ((int)$account['id'] === $globalData['accountId']): ?>
						<?= t('accounts.self') ?>
					<?php else: ?>
						<form method="post" action="/music/accounts">
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

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
