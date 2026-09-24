<?php require_once __MODULES__ . '/music/hue.php' ?>
<!DOCTYPE html>
<html lang="<?= activeLocale() ?>" style="--hue: <?= musicActiveHue() ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="csrfToken" content="<?= htmlspecialchars(csrfToken()) ?>">
	<title><?= $pageTitle ?> - Music - LotsOfS</title>
	<link rel="stylesheet" href="<?= asset('/modules/music/css/styles.css') ?>">
	<script id="langStrings" type="application/json"><?= json_encode(stringCatalogue()) ?></script>
	<script src="<?= asset('/js/util.js') ?>"></script>
</head>
<body>
