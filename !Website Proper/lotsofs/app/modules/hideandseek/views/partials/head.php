<!DOCTYPE html>
<html lang="<?= activeLocale() ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="csrfToken" content="<?= htmlspecialchars(csrfToken()) ?>">
	<title><?= $pageTitle ?> - Hide and Seek - LotsOfS</title>
	<?php /* Declared, or the browser asks for /favicon.ico and gets the site one. */ ?>
	<link rel="icon" href="<?= asset('/modules/hideandseek/favicon.ico') ?>">
	<link rel="stylesheet" href="<?= asset('/modules/hideandseek/css/styles.css') ?>">
	<?php /* Only where there is a map: it is 15KB nothing else on the site uses. */ ?>
	<?php if ($globalData['mapPage'] ?? false): ?>
		<link rel="stylesheet" href="<?= asset('/modules/hideandseek/vendor/leaflet/leaflet.css') ?>">
	<?php endif ?>
	<script id="langStrings" type="application/json"><?= json_encode(stringCatalogue()) ?></script>
	<script src="<?= asset('/js/util.js') ?>"></script>
</head>
<body>
<?php /* The shell opens here and foot.php closes it, so a new view gets the nav
         and the layout without having to remember either. */ ?>
<div class="hnsLayout">
<?php require(__MODULES__ . '/hideandseek/views/partials/nav.php') ?>
<main class="hnsMain">
