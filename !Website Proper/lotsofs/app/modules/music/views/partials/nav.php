<?php require_once __ROOT__ . '/session.php' ?>
<?php sessionScope('music') ?>

<nav>
	<?php if (currentAccountId()): ?>
		<a href="/music/songs" class="<?= urlIs("/music/songs") ? "navCurrent" : "" ?>"><?= t('nav.songs') ?></a>
		<a href="/music/artists" class="<?= urlIs("/music/artists") ? "navCurrent" : "" ?>"><?= t('nav.artists') ?></a>
		<a href="/music/albums" class="<?= urlIs("/music/albums") ? "navCurrent" : "" ?>"><?= t('nav.albums') ?></a>
		<?php if ($globalData['isAdmin'] ?? false): ?>
			<a href="/music/add-songs" class="<?= urlIs("/music/add-songs") ? "navCurrent" : "" ?>"><?= t('nav.addSongs') ?></a>
			<a href="/music/invites" class="<?= urlIs("/music/invites") ? "navCurrent" : "" ?>"><?= t('nav.invites') ?></a>
			<a href="/music/accounts" class="<?= urlIs("/music/accounts") ? "navCurrent" : "" ?>"><?= t('nav.accounts') ?></a>
		<?php endif ?>
	<?php else: ?>
		<a href="/music/login" class="<?= urlIs("/music/login") ? "navCurrent" : "" ?>"><?= t('nav.login') ?></a>
		<a href="/music/register" class="<?= urlIs("/music/register") ? "navCurrent" : "" ?>"><?= t('nav.register') ?></a>
	<?php endif ?>

	<div class="navRight">
		<details class="navLanguage">
			<summary class="navMenuButton" aria-label="<?= t('nav.language') ?>">🌐&#xFE0E;</summary>
			<form method="post" action="/music/language" class="navLanguageMenu">
				<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
				<input type="hidden" name="return" value="<?= htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ?>">
				<?php foreach (AVAILABLE_LOCALES as $code): ?>
					<button type="submit" name="lang" value="<?= $code ?>" class="navLanguageOption<?= activeLocale() === $code ? ' navLanguageOptionActive' : '' ?>">
						<?= htmlspecialchars(stringCatalogue()['language.' . $code] ?? strtoupper($code)) ?>
					</button>
				<?php endforeach ?>
			</form>
		</details>

		<?php if (currentAccountId()): ?>
			<details class="navAccountMenu">
				<summary class="navMenuButton"><?= htmlspecialchars(currentAccountName()) ?></summary>
				<form method="post" action="/music/logout" class="navLogout">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
					<button type="submit"><?= t('nav.logout') ?></button>
				</form>
			</details>
		<?php endif ?>
	</div>
</nav>
