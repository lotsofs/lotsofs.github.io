<?php require(__MODULES__ . '/hideandseek/views/partials/head.php') ?>

<h1><?= t('login.heading') ?></h1>

<?php if ($globalData['formError']): ?>
	<p class="formError"><?= htmlspecialchars($globalData['formError']) ?></p>
<?php endif ?>

<form method="post" action="/hideandseek/login">
	<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">

	<p>
		<label for="account_name"><?= t('login.field.accountName') ?></label>
		<input type="text" id="account_name" name="account_name" value="<?= htmlspecialchars($globalData['accountName']) ?>" required>
	</p>
	<p>
		<label for="password"><?= t('login.field.password') ?></label>
		<input type="password" id="password" name="password" required>
	</p>

	<button type="submit"><?= t('login.submit') ?></button>
</form>

<?php require(__MODULES__ . '/hideandseek/views/partials/foot.php') ?>
