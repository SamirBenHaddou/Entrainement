<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Non autorisé');
}

$pdo = new PDO('mysql:host=localhost;dbname=entrainement', 'root', 'BeagroupSamir!');

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $jour = $_GET['jour'] ?? '';
    $stmt = $pdo->prepare('
        SELECT e.* FROM seances s
        JOIN exercices e ON s.exercice_id = e.id
        WHERE s.jour = ? AND s.utilisateur_id = ?
    ');
    $stmt->execute([$jour, $user_id]);
    $exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($exercices);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $jour = $data['jour'] ?? '';
    $exercices = $data['exercices'] ?? [];

    // Supprimer anciennes séances pour ce jour/utilisateur
    $stmt = $pdo->prepare('DELETE FROM seances WHERE jour = ? AND utilisateur_id = ?');
    $stmt->execute([$jour, $user_id]);

    // Insérer les nouveaux
    if (count($exercices) > 0) {
        $stmt = $pdo->prepare('INSERT INTO seances (utilisateur_id, jour, exercice_id) VALUES (?, ?, ?)');
        foreach ($exercices as $idEx) {
            $stmt->execute([$user_id, $jour, $idEx]);
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['status' => 'ok']);
    exit;
}
