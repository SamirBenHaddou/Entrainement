<?php
// === 3. exercices.php (API pour récupérer les exercices) ===
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Non autorisé');
}

header('Content-Type: application/json');

try {
    $pdo = new PDO('mysql:host=localhost;dbname=entrainement', 'root', 'BeagroupSamir!', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    // Récupérer tous les exercices sans filtre d'âge fixe
    $stmt = $pdo->query('SELECT * FROM exercices ORDER BY categorie, nom');
    $exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($exercices);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Erreur base de données: ' . $e->getMessage()]);
}
?>