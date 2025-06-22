<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Non autorisé');
}

header('Content-Type: application/json');
$pdo = new PDO('mysql:host=localhost;dbname=entrainement', 'root', 'BeagroupSamir!');
$stmt = $pdo->query('SELECT * FROM exercices WHERE age = 10'); // filtrage âge 10 ans ici
$exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($exercices);
