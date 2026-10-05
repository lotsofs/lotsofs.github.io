<?php require(__MODULES__ . '/hideandseek/views/partials/head.php') ?>

<h1><?= t('hideandseek.heading') ?></h1>

<?php if (currentAccountId()): ?>
	<p><?= htmlspecialchars(t('hideandseek.welcome', ['name' => currentAccountName()])) ?></p>
<?php else: ?>
	<p><a href="/hideandseek/login"><?= t('hideandseek.signedOut') ?></a></p>
<?php endif ?>

<div class="hnsMapLayout">
	<section class="hnsMapColumn">
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
	</section>

	<section class="hnsPoiColumn">
		<h2><?= t('poi.heading') ?></h2>

		<?php /* Filled by map.js from whatever the map has actually drawn, and refilled
		         every time it settles. Empty until the map is loaded at all. */ ?>
		<div id="hnsImportedPoiTable" class="hnsPoiArea"></div>
	</section>
</div>

<?php /* Before foot.php, which closes </main> and </body>. */ ?>
<script id="hnsImportedPois" type="application/json"><?= json_encode($globalData['imported'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="<?= asset('/modules/hideandseek/js/map.js') ?>"></script>

<?php require(__MODULES__ . '/hideandseek/views/partials/foot.php') ?>
