<?php require_once __ROOT__ . '/session.php' ?>
<?php sessionScope('music') ?>

<nav>
	<?php if (currentAccountId()): ?>
		<?php if ($globalData['isAdmin'] ?? false): ?>
			<a href="/music/add-songs" class="<?= urlIs("/music/add-songs") ? "navCurrent" : "" ?>"><?= t('nav.addSongs') ?></a>
		<?php endif ?>
		<a href="/music/songs" class="<?= urlIs("/music/songs") ? "navCurrent" : "" ?>"><?= t('nav.songs') ?></a>
		<a href="/music/artists" class="<?= urlIs("/music/artists") ? "navCurrent" : "" ?>"><?= t('nav.artists') ?></a>
		<a href="/music/albums" class="<?= urlIs("/music/albums") ? "navCurrent" : "" ?>"><?= t('nav.albums') ?></a>
		<?php if ($globalData['isAdmin'] ?? false): ?>
			<a href="/music/invites" class="<?= urlIs("/music/invites") ? "navCurrent" : "" ?>"><?= t('nav.invites') ?></a>
			<a href="/music/accounts" class="<?= urlIs("/music/accounts") ? "navCurrent" : "" ?>"><?= t('nav.accounts') ?></a>
		<?php endif ?>
		<span class="navAccount"><?= htmlspecialchars(currentAccountName()) ?></span>
		<form method="post" action="/music/logout" class="navLogout">
			<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
			<button type="submit"><?= t('nav.logout') ?></button>
		</form>
	<?php else: ?>
		<a href="/music/login" class="<?= urlIs("/music/login") ? "navCurrent" : "" ?>"><?= t('nav.login') ?></a>
		<a href="/music/register" class="<?= urlIs("/music/register") ? "navCurrent" : "" ?>"><?= t('nav.register') ?></a>
	<?php endif ?>

	<form method="post" action="/music/language" class="navLanguage">
		<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
		<input type="hidden" name="return" value="<?= htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ?>">
		<select name="lang" onchange="this.form.submit()" aria-label="<?= t('nav.language') ?>">
			<?php foreach (AVAILABLE_LOCALES as $code): ?>
				<option value="<?= $code ?>"<?= activeLocale() === $code ? ' selected' : '' ?>><?= htmlspecialchars(stringCatalogue()['language.' . $code] ?? strtoupper($code)) ?></option>
			<?php endforeach ?>
		</select>
	</form>
</nav>
