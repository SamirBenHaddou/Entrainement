<?php
// === 3. exercices.php (API pour récupérer les exercices) ===
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit(json_encode(['success' => false, 'error' => 'Non autorisé']));
}

header('Content-Type: application/json');

$project = 'mastercoach';
$configs = require 'C:/xampp/config/config.php';

if (!isset($configs[$project])) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => "Configuration du projet '$project' introuvable."]);
    exit;
}

$dbConfig = $configs[$project];

try {
    $pdo = new PDO(
        'mysql:host=' . $dbConfig['db_host'] . ';dbname=' . $dbConfig['db_name'],
        $dbConfig['db_user'],
        $dbConfig['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // --- AJOUT, MODIF, SUPPRESSION ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';
        if ($action === 'ajouter') {
            $nom = $_POST['nom'] ?? '';
            $categorie = $_POST['categorie'] ?? '';
            $description = $_POST['description'] ?? '';
            $duree = $_POST['duree'] ?? '';
            $materiel = $_POST['materiel'] ?? '';
            if (!$nom || !$categorie || !$description || !$duree) {
                echo json_encode(['success' => false, 'error' => 'Champs obligatoires manquants']);
                exit;
            }
            $stmt = $pdo->prepare('INSERT INTO exercices (nom, categorie, description, duree, materiel) VALUES (?, ?, ?, ?, ?)');
            if ($stmt->execute([$nom, $categorie, $description, $duree, $materiel])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur SQL']);
            }
            exit;
        }

        if ($action === 'modifier') {
            $id = $_POST['id'] ?? '';
            $nom = $_POST['nom'] ?? '';
            $categorie = $_POST['categorie'] ?? '';
            $description = $_POST['description'] ?? '';
            $duree = $_POST['duree'] ?? '';
            $materiel = $_POST['materiel'] ?? '';
            if (!$id || !$nom || !$categorie || !$description || !$duree) {
                echo json_encode(['success' => false, 'error' => 'Champs obligatoires manquants']);
                exit;
            }
            $stmt = $pdo->prepare('UPDATE exercices SET nom=?, categorie=?, description=?, duree=?, materiel=? WHERE id=?');
            if ($stmt->execute([$nom, $categorie, $description, $duree, $materiel, $id])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur SQL']);
            }
            exit;
        }

        if ($action === 'supprimer') {
            $id = $_POST['id'] ?? '';
            if (!$id) {
                echo json_encode(['success' => false, 'error' => 'ID manquant']);
                exit;
            }
            $stmt = $pdo->prepare('DELETE FROM exercices WHERE id=?');
            if ($stmt->execute([$id])) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Erreur SQL']);
            }
            exit;
        }
    }

    // --- LECTURE (GET) ---
    $stmt = $pdo->query('SELECT * FROM exercices ORDER BY categorie, nom');
    $exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($exercices);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Erreur base de données: ' . $e->getMessage()]);
}
?>