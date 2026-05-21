<?php
$pdo = new PDO("mysql:host=10.0.2.4;dbname=doordash_db;charset=utf8mb4", "doordashuser", "Sai@123abc", [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
?>