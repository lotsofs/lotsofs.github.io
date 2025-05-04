<?php

require 'util.php';

require 'router.php';

// try {
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
// 	dd($stm->fetchAll(PDO::FETCH_ASSOC));
// } catch (PDOException $e) {
// 	http_response_code(500);
// 	dd($e);
// }