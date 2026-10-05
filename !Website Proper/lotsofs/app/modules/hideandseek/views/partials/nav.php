<?php require_once __ROOT__ . '/session.php' ?>
<?php sessionScope('hideandseek') ?>

<nav class="hnsNav">
	<a class="hnsNavBrand" href="/hideandseek"><?= t('hideandseek.heading') ?></a>

	<?php if (currentAccountId()): ?>
		<a href="/hideandseek" class="hnsNavLink<?= urlIs("/hideandseek") ? " hnsNavCurrent" : "" ?>"><?= t('nav.home') ?></a>
		<a href="/hideandseek/game-maps" class="hnsNavLink<?= urlIs("/hideandseek/game-maps") ? " hnsNavCurrent" : "" ?>"><?= t('nav.gameMaps') ?></a>
		<a href="/hideandseek/import-pois" class="hnsNavLink<?= urlIs("/hideandseek/import-pois") ? " hnsNavCurrent" : "" ?>"><?= t('nav.importPois') ?></a>
		<?php if ($globalData['isAdmin'] ?? false): ?>
			<a href="/hideandseek/accounts" class="hnsNavLink<?= urlIs("/hideandseek/accounts") ? " hnsNavCurrent" : "" ?>"><?= t('nav.accounts') ?></a>
		<?php endif ?>
	<?php endif ?>

	<?php /* Last in the flow with the rest of the buttons, not pushed to the far
	         end. One Account button either way: signed out it holds the two ways
	         in, signed in it holds the way out. */ ?>
	<details class="hnsNavAccount">
		<summary class="hnsNavLink hnsNavMenuButton"><?= htmlspecialchars(currentAccountId() ? currentAccountName() : t('nav.account')) ?></summary>
		<div class="hnsNavMenu">
			<?php if (currentAccountId()): ?>
				<form method="post" action="/hideandseek/logout">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
					<button type="submit" class="hnsNavMenuItem"><?= t('nav.logout') ?></button>
				</form>
			<?php else: ?>
				<a href="/hideandseek/login" class="hnsNavMenuItem<?= urlIs("/hideandseek/login") ? " hnsNavCurrent" : "" ?>"><?= t('nav.login') ?></a>
				<a href="/hideandseek/register" class="hnsNavMenuItem<?= urlIs("/hideandseek/register") ? " hnsNavCurrent" : "" ?>"><?= t('nav.register') ?></a>
			<?php endif ?>

			<?php if (count(moduleLocales('hideandseek')) > 1): ?>
				<form method="post" action="/hideandseek/language" class="hnsNavLanguage">
					<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
					<input type="hidden" name="return" value="<?= htmlspecialchars(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ?>">
					<?php foreach (moduleLocales('hideandseek') as $code): ?>
						<button type="submit" name="lang" value="<?= $code ?>" class="hnsNavMenuItem<?= activeLocale() === $code ? ' hnsNavCurrent' : '' ?>">
							<?= htmlspecialchars(stringCatalogue()['language.' . $code] ?? strtoupper($code)) ?>
						</button>
					<?php endforeach ?>
				</form>
			<?php endif ?>
		</div>
	</details>
</nav>
