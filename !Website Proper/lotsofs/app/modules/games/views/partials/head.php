<!DOCTYPE html>
<html lang="<?= activeLocale() ?>">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<meta name="csrfToken" content="<?= htmlspecialchars(csrfToken()) ?>">
	<title><?= $pageTitle ?> - Games - LotsOfS</title>
	<link rel="stylesheet" href="<?= asset('/modules/games/css/styles.css') ?>">
	<script id="langStrings" type="application/json"><?= json_encode(stringCatalogue()) ?></script>
	<script src="<?= asset('/js/util.js') ?>"></script>
</head>
<body>
<?php /* The shell opens here and foot.php closes it, so a new view gets the nav
         and the layout without having to remember either. */ ?>
<div class="gamesLayout">
<?php require(__MODULES__ . '/games/views/partials/nav.php') ?>
<main class="gamesMain">
