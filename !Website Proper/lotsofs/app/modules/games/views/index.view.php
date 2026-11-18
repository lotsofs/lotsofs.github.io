<?php require(__MODULES__ . '/games/views/partials/head.php') ?>

<h1><?= t('games.heading') ?></h1>

<p><?= htmlspecialchars(t('games.welcome', ['name' => currentAccountName()])) ?></p>

<ul>
	<li><a href="/games/aoe2"><?= t('aoe2.heading') ?></a></li>
	<li><a href="/games/sims2"><?= t('sims2.heading') ?></a></li>
</ul>

<?php require(__MODULES__ . '/games/views/partials/foot.php') ?>
