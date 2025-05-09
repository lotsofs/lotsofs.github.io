<?php

class Database {
    public $connection;
    
    public function __construct($config, $username='', $password='') {
        $dsn = "sqlite:database/{$config['dbname']}";
        $pdo = new PDO($dsn, $username, $password);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec("PRAGMA foreign_keys = ON");
        $this->connection = $pdo;
    }
    
    public function query($query, $params = []) {
        try {
            $stm = $this->connection->prepare($query);
            $stm->execute($params);

            return $stm;
        }
        catch (PDOException $e) {
    	    http_response_code(500);
    	    dd($e);
        }
    }
}