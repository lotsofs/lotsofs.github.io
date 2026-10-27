<?php

require_once __MODULES__ . '/basstabs/tabs.php';

$pageTitle = "Bass Tabs";

$globalData['songs'] = bassTabSongs();

require __MODULES__ . '/basstabs/views/index.view.php';
