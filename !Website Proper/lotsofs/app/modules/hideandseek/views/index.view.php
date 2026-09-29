<?php require(__MODULES__ . '/hideandseek/views/partials/head.php') ?>

<h1><?= t('hideandseek.heading') ?></h1>

<?php if (currentAccountId()): ?>
	<p><?= htmlspecialchars(t('hideandseek.welcome', ['name' => currentAccountName()])) ?></p>
<?php else: ?>
	<p><a href="/hideandseek/login"><?= t('hideandseek.signedOut') ?></a></p>
<?php endif ?>

<h2><?= t('map.heading') ?></h2>

<?php /* Empty on purpose: map.js fills it with the placeholder, then with the
         map itself once somebody asks for it. */ ?>
<div
	id="hnsMap"
	class="hnsMap"
	data-lat="<?= htmlspecialchars((string)$globalData['mapCentre']['lat']) ?>"
	data-lng="<?= htmlspecialchars((string)$globalData['mapCentre']['lng']) ?>"
	data-zoom="<?= (int)$globalData['mapCentre']['zoom'] ?>"
></div>

<p class="hnsMapHint"><?= t('map.hint') ?></p>

<?php /* Before foot.php, which closes </main> and </body>. */ ?>
<script src="<?= asset('/modules/hideandseek/vendor/leaflet/leaflet.js') ?>"></script>
<script src="<?= asset('/modules/hideandseek/js/map.js') ?>"></script>

<?php require(__MODULES__ . '/hideandseek/views/partials/foot.php') ?>
