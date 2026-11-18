<?php require_once __ROOT__ . '/session.php' ?>
<?php sessionScope('games') ?>

<nav class="gamesNav">
	<a class="gamesNavBrand" href="/games"><?= t('games.heading') ?></a>

	<?php if (currentAccountId()): ?>
		<a href="/games" class="gamesNavLink<?= urlIs("/games") ? " gamesNavCurrent" : "" ?>"><?= t('nav.home') ?></a>
		<a href="/games/aoe2" class="gamesNavLink<?= urlIs("/games/aoe2") ? " gamesNavCurrent" : "" ?>"><?= t('nav.aoe2') ?></a>
		<a href="/games/sims2" class="gamesNavLink<?= urlIs("/games/sims2") ? " gamesNavCurrent" : "" ?>"><?= t('nav.sims2') ?></a>
		<?php if ($globalData['isAdmin'] ?? false): ?>
			<a href="/games/accounts" class="gamesNavLink<?= urlIs("/games/accounts") ? " gamesNavCurrent" : "" ?>"><?= t('nav.accounts') ?></a>
		<?php endif ?>
	<?php endif ?>

	<?php /* One Account button either way: signed out it holds the two ways in,
	         signed in it holds the way out. */ ?>
	<details class="gamesNavAccount">
		<summary class="gamesNavLink gamesNavMenuButton"><?= htmlspecialchars(currentAccountId() ? currentAccountName() : t('nav.account')) ?></summary>
		<div class="gamesNavMenu">
			<?php if (currentAccountId()): ?>
				<form method="post" action="/games/logout">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
					<button type="submit" class="gamesNavMenuItem"><?= t('nav.logout') ?></button>
				</form>
			<?php else: ?>
				<a href="/games/login" class="gamesNavMenuItem<?= urlIs("/games/login") ? " gamesNavCurrent" : "" ?>"><?= t('nav.login') ?></a>
				<a href="/games/register" class="gamesNavMenuItem<?= urlIs("/games/register") ? " gamesNavCurrent" : "" ?>"><?= t('nav.register') ?></a>
			<?php endif ?>

			<?php if (count(moduleLocales('games')) > 1): ?>
				<form method="post" action="/games/language" class="gamesNavLanguage">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
					<input type="hidden" name="return" value="<?= htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ?>">
					<?php foreach (moduleLocales('games') as $code): ?>
						<button type="submit" name="lang" value="<?= $code ?>" class="gamesNavMenuItem<?= activeLocale() === $code ? ' gamesNavCurrent' : '' ?>">
							<?= htmlspecialchars(stringCatalogue()['language.' . $code] ?? strtoupper($code)) ?>
						</button>
					<?php endforeach ?>
				</form>
			<?php endif ?>
		</div>
	</details>
</nav>
