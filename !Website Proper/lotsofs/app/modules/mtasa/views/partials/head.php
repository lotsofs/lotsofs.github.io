<!DOCTYPE html>
<html lang="<?= activeLocale() ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<?php /* Readable by link, kept out of search results (the owner's choice). */ ?>
	<meta name="robots" content="noindex">
	<meta name="csrfToken" content="<?= htmlspecialchars(csrfToken()) ?>">
	<title><?= $pageTitle ?> - MTA:SA Maps - LotsOfS</title>
	<link rel="stylesheet" href="<?= asset('/modules/mtasa/css/styles.css') ?>">
	<script id="langStrings" type="application/json"><?= json_encode(stringCatalogue()) ?></script>
	<script src="<?= asset('/js/util.js') ?>"></script>
</head>
<body>
<?php /* The shell opens here and foot.php closes it. */ ?>
<div class="mtasaLayout">
<?php require(__MODULES__ . '/mtasa/views/partials/nav.php') ?>
<main class="mtasaMain">
