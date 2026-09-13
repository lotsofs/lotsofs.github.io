<?php

$appRoot = is_dir('/home/lotsofs/app') ? '/home/lotsofs/app' : __DIR__ . '/../app';

require $appRoot . '/util.php';

require $appRoot . '/classes/Database.php';

require $appRoot . '/router.php';
