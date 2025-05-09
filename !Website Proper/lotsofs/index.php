<?php

require 'util.php';
require 'router.php';

require 'classes/Database.php';

$config = require('config.php');
$db_config = php_sapi_name() === 'cli-server' ? $config['database_test'] : $config['database'];

$db = new Database($db_config);
$id = "1"; // To be dynamically assigned later

$query = "CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL UNIQUE
)";
$db->query($query);

$query = "CREATE TABLE IF NOT EXISTS tests (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    text TEXT NOT NULL,
    user_id INTEGER NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT
)";
$db->query($query);

$query = "SELECT * FROM tests WHERE id = :id";
$params = [':id' => $id];
dd($db->query($query, $params)->fetchAll(PDO::FETCH_ASSOC));


