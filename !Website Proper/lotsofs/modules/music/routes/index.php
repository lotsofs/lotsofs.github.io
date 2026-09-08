<?php

require_once __ROOT__ . '/session.php';
sessionScope('music');

stringCatalogue("music");

$pageTitle = t("page.music.title");

require __MODULES__ . "/music/views/index.view.php";
