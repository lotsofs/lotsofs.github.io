<?php

class Database {
    public $pdo;
    
    public function __construct($dbFilePath, $username='', $password='') {
        $dsn = "sqlite:{$dbFilePath}";
        $connection = new PDO($dsn, $username, $password);
        $connection->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec("PRAGMA foreign_keys = ON");
        $this->pdo = $connection;
    }
    
    public function query($query, $params = []) {
        try {
            $stm = $this->pdo->prepare($query);
            $stm->execute($params);

            return $stm;
        }
        catch (PDOException $e) {
    	    http_response_code(500);
    	    // TODO: This won't do anything with ajax
            dd($e);
        }
    }

    public function selectAllFromTable($tableName) {
        try {
            $query = "SELECT * FROM ".$tableName;
            $stm = $this->query($query);
            return $stm->fetchAll();
        }
        catch (PDOException $e) {
            http_response_code(500);
            dd($e);
        }
    }

    public function execSQL($sql) {
        try {
            $this->pdo->exec($sql);
            return true;
        }
        catch (PDOException $e) {
            echo $e->getMessage();
            return false;
        }
    }
}