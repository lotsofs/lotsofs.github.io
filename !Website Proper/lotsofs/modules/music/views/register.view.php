<?php require(__MODULES__ . '/music/views/partials/head.php') ?>

<?php require(__MODULES__ . '/music/views/partials/nav.php') ?>

<h1>
	<?= t('register.heading') ?>
</h1>

<?php if (!$globalData['inviteRequired']): ?>
	<p><?= t('register.firstAccount') ?></p>
<?php endif ?>

<?php if ($globalData['formError']): ?>
	<p class="formError"><?= htmlspecialchars($globalData['formError']) ?></p>
<?php endif ?>

<form method="post" action="/music/register">
	<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

	<?php if ($globalData['inviteRequired']): ?>
		<p>
			<label for="invite_code"><?= t('register.field.inviteCode') ?></label>
			<input type="text" id="invite_code" name="invite_code" value="<?= htmlspecialchars($globalData['inviteCode']) ?>" required>
		</p>
	<?php endif ?>

	<p>
		<label for="account_name"><?= t('register.field.accountName') ?></label>
		<input type="text" id="account_name" name="account_name" value="<?= htmlspecialchars($globalData['accountName']) ?>" required>
	</p>
	<p>
		<label for="password"><?= t('register.field.password') ?></label>
		<input type="password" id="password" name="password" required>
	</p>
	<p>
		<label for="password_confirm"><?= t('register.field.passwordConfirm') ?></label>
		<input type="password" id="password_confirm" name="password_confirm" required>
	</p>

	<button type="submit"><?= t('register.submit') ?></button>
</form>

<?php require(__MODULES__ . '/music/views/partials/foot.php') ?>
