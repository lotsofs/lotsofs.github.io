<?php require_once __ROOT__ . '/session.php' ?>
<?php sessionScope('music') ?>

<nav>
	<?php if (currentAccountId()): ?>
		<a href="/music/add-songs" class="<?= urlIs("/music/add-songs") ? "navCurrent" : "" ?>"><?= t('nav.addSongs') ?></a>
		<a href="/music/songs" class="<?= urlIs("/music/songs") ? "navCurrent" : "" ?>"><?= t('nav.songs') ?></a>
		<a href="/music/invites" class="<?= urlIs("/music/invites") ? "navCurrent" : "" ?>"><?= t('nav.invites') ?></a>
		<span class="navAccount"><?= htmlspecialchars(currentAccountName()) ?></span>
		<form method="post" action="/music/logout" class="navLogout">
			<input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
			<button type="submit"><?= t('nav.logout') ?></button>
		</form>
	<?php else: ?>
		<a href="/music/login" class="<?= urlIs("/music/login") ? "navCurrent" : "" ?>"><?= t('nav.login') ?></a>
		<a href="/music/register" class="<?= urlIs("/music/register") ? "navCurrent" : "" ?>"><?= t('nav.register') ?></a>
	<?php endif ?>
</nav>
