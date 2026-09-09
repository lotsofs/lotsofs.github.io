<?php

class Database {
    public $pdo;
    
    public function __construct($dbFilePath, $username='', $password='') {
        $dsn = "sqlite:{$dbFilePath}";
        $connection = new PDO($dsn, $username, $password);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec("PRAGMA foreign_keys = ON");
        // sqlite allows one writer at a time, so wait for it instead of failing outright
        $connection->exec("PRAGMA busy_timeout = 5000");
        $this->pdo = $connection;
    }
    
    public function query($query, $params = []) {
        $stm = $this->pdo->prepare($query);
        $stm->execute($params);

        return $stm;
    }

    public function selectAllFromTable($tableName) {
        return $this->query("SELECT * FROM " . $tableName)->fetchAll();
    }

    public function execSQL($sql) {
        $this->pdo->exec($sql);
    }
}