<?php

$dbFile = php_sapi_name() === 'cli-server' ? 'music_test.sqlite' : 'music.sqlite';

return new Database(__MODULES__ . '/music/database/' . $dbFile);
