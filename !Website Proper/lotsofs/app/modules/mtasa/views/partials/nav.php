<?php require_once __ROOT__ . '/session.php' ?>
<?php sessionScope('mtasa') ?>

<nav class="mtasaNav">
	<a class="mtasaNavBrand" href="/mtasa"><?= t('maps.heading') ?></a>

	<a href="/mtasa" class="mtasaNavLink<?= urlIs("/mtasa") ? " mtasaNavCurrent" : "" ?>"><?= t('nav.maps') ?></a>
	<a href="/mtasa/comments" class="mtasaNavLink<?= urlIs("/mtasa/comments") ? " mtasaNavCurrent" : "" ?>"><?= t('nav.comments') ?></a>
	<?php if ($globalData['isAdmin'] ?? false): ?>
		<a href="/mtasa/accounts" class="mtasaNavLink<?= urlIs("/mtasa/accounts") ? " mtasaNavCurrent" : "" ?>"><?= t('nav.accounts') ?></a>
	<?php endif ?>

	<?php /* One Account button either way: signed out it holds the two ways in,
	         signed in it holds the way out. */ ?>
	<details class="mtasaNavAccount">
		<summary class="mtasaNavLink mtasaNavMenuButton"><?= htmlspecialchars(currentAccountId() ? currentAccountName() : t('nav.account')) ?></summary>
		<div class="mtasaNavMenu">
			<?php if (currentAccountId()): ?>
				<form method="post" action="/mtasa/logout">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
					<button type="submit" class="mtasaNavMenuItem"><?= t('nav.logout') ?></button>
				</form>
			<?php else: ?>
				<a href="/mtasa/login" class="mtasaNavMenuItem<?= urlIs("/mtasa/login") ? " mtasaNavCurrent" : "" ?>"><?= t('nav.login') ?></a>
				<a href="/mtasa/register" class="mtasaNavMenuItem<?= urlIs("/mtasa/register") ? " mtasaNavCurrent" : "" ?>"><?= t('nav.register') ?></a>
			<?php endif ?>

			<?php if (count(moduleLocales('mtasa')) > 1): ?>
				<form method="post" action="/mtasa/language" class="mtasaNavLanguage">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
					<input type="hidden" name="return" value="<?= htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ?>">
					<?php foreach (moduleLocales('mtasa') as $code): ?>
						<button type="submit" name="lang" value="<?= $code ?>" class="mtasaNavMenuItem<?= activeLocale() === $code ? ' mtasaNavCurrent' : '' ?>">
							<?= htmlspecialchars(stringCatalogue()['language.' . $code] ?? strtoupper($code)) ?>
						</button>
					<?php endforeach ?>
				</form>
			<?php endif ?>
		</div>
	</details>
</nav>
