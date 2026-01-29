<?php
$host = "localhost";
$port = "3307";      
$db   = "UniversityLibrary";
$user = "root";
$pass = "";             

try {
  $pdo = new PDO("mysql:host=localhost;port=3307;dbname=$db;charset=utf8mb4", $user, $pass);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  die("DB connection failed: " . $e->getMessage());
}
