<?php

require 'util.php';
require 'router.php';

// try {

// Based on if its running in this test environment, use test_Db or not. 
// 	if (php_sapi_name() === 'cli-server') {


// 	$db = new PDO('sqlite:database/test_db.sqlite');
// 	$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 	$stm = $db->prepare("
// 		CREATE TABLE IF NOT EXISTS tests (
// 			id INTEGER PRIMARY KEY AUTOINCREMENT,
// 			text TEXT NOT NULL,
// 			number INTEGER NOT NULL
// 		)
// 	");
// 	$stm->execute();

// 	$stm = $db->prepare("select * from tests");
// 	$stm->execute();
// } catch (PDOException $e) {
// 	http_response_code(500);
// 	dd($e);
// }