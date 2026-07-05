<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// === 4. seances.php (Interface principale améliorée) ===
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$configs = require(__DIR__ . "/../config/config.php");
$db = $configs['mastercoach'];

try {
    $pdo = new PDO("mysql:host={$db['db_host']};dbname={$db['db_name']};charset=utf8mb4", $db['db_user'], $db['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die('Erreur base de données : ' . $e->getMessage());
}

try {
    $pdo->exec('ALTER TABLE exercices ADD COLUMN favori TINYINT(1) NOT NULL DEFAULT 0');
} catch (Exception $e) {
}

$user_id = $_SESSION['user_id'];
$date_seance = $_GET['date'] ?? date('Y-m-d');

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS joueurs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        nom VARCHAR(120) NOT NULL,
        poste VARCHAR(80) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_joueurs_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

try {
    $pdo->exec('ALTER TABLE joueurs MODIFY poste VARCHAR(255) DEFAULT NULL');
} catch (Exception $e) {
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS seance_joueurs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        seance_id INT NOT NULL,
        joueur_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uniq_seance_joueur (seance_id, joueur_id),
        INDEX idx_seance_joueurs_joueur (joueur_id),
        CONSTRAINT fk_seance_joueurs_seance
            FOREIGN KEY (seance_id) REFERENCES seances(id)
            ON DELETE CASCADE,
        CONSTRAINT fk_seance_joueurs_joueur
            FOREIGN KEY (joueur_id) REFERENCES joueurs(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

function getOrCreateSeanceId(PDO $pdo, string $date, int $userId): int
{
    $stmt = $pdo->prepare('SELECT id FROM seances WHERE date_seance = ? AND user_id = ?');
    $stmt->execute([$date, $userId]);
    $seance = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($seance) {
        return (int) $seance['id'];
    }

    $stmt = $pdo->prepare('INSERT INTO seances (date_seance, user_id) VALUES (?, ?)');
    $stmt->execute([$date, $userId]);

    return (int) $pdo->lastInsertId();
}

// API endpoints pour AJAX
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    if ($_GET['api'] === 'exercices') {
        $stmt = $pdo->query('SELECT *, COALESCE(favori, 0) AS favori FROM exercices ORDER BY favori DESC, categorie, nom');
        $exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($exercices);
        exit;
    }
    
    if ($_GET['api'] === 'seance' && isset($_GET['date'])) {
        $stmt = $pdo->prepare('
            SELECT e.*, se.id as seance_exercice_id, se.ordre
            FROM exercices e
            JOIN seance_exercices se ON e.id = se.exercice_id
            JOIN seances s ON se.seance_id = s.id
            WHERE s.date_seance = ? AND s.user_id = ?
            ORDER BY se.ordre ASC
        ');
        $stmt->execute([$_GET['date'], $user_id]);
        $exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($exercices);
        exit;
    }

    if ($_GET['api'] === 'joueurs') {
        $stmt = $pdo->prepare('SELECT id, nom, poste FROM joueurs WHERE user_id = ? ORDER BY nom ASC');
        $stmt->execute([$user_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($_GET['api'] === 'joueurs_seance' && isset($_GET['date'])) {
        $stmt = $pdo->prepare(
            'SELECT j.id, j.nom, j.poste
             FROM joueurs j
             JOIN seance_joueurs sj ON sj.joueur_id = j.id
             JOIN seances s ON s.id = sj.seance_id
             WHERE s.date_seance = ? AND s.user_id = ?
             ORDER BY j.nom ASC'
        );
        $stmt->execute([$_GET['date'], $user_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
}

// Gestion des actions POST via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'ajouter_exercice') {
        $exercice_id = intval($_POST['exercice_id']);
        $date = $_POST['date'];

        $seance_id = getOrCreateSeanceId($pdo, $date, (int) $user_id);

        // Vérifier si l'exercice n'est pas déjà ajouté
        $stmt = $pdo->prepare('SELECT 1 FROM seance_exercices WHERE seance_id = ? AND exercice_id = ?');
        $stmt->execute([$seance_id, $exercice_id]);

        if (!$stmt->fetch()) {
            // Trouver le prochain ordre
            $stmt = $pdo->prepare('SELECT MAX(ordre) AS max_ordre FROM seance_exercices WHERE seance_id = ?');
            $stmt->execute([$seance_id]);
            $maxOrdre = $stmt->fetchColumn();
            $ordre = $maxOrdre !== false ? intval($maxOrdre) + 1 : 1;

            $stmt = $pdo->prepare('INSERT INTO seance_exercices (seance_id, exercice_id, ordre) VALUES (?, ?, ?)');
            $stmt->execute([$seance_id, $exercice_id, $ordre]);
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Exercice déjà ajouté']);
            exit;
        }
    }
    
    if ($_POST['action'] === 'supprimer_exercice') {
        $exercice_id = intval($_POST['exercice_id']);
        $date = $_POST['date'];
        
        $stmt = $pdo->prepare('
            DELETE se FROM seance_exercices se
            JOIN seances s ON se.seance_id = s.id
            WHERE s.date_seance = ? AND s.user_id = ? AND se.exercice_id = ?
        ');
        $stmt->execute([$date, $user_id, $exercice_id]);
        
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'basculer_favori_exercice') {
        $exercice_id = intval($_POST['exercice_id'] ?? 0);
        if ($exercice_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Exercice invalide']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE exercices SET favori = CASE WHEN favori = 1 THEN 0 ELSE 1 END WHERE id = ?');
        $stmt->execute([$exercice_id]);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'enregistrer_joueurs_seance') {
        $date = $_POST['date'] ?? '';
        $joueurs = $_POST['joueurs'] ?? [];

        if ($date === '' || !is_array($joueurs)) {
            echo json_encode(['success' => false, 'message' => 'Donnees invalides']);
            exit;
        }

        $seanceId = getOrCreateSeanceId($pdo, $date, (int) $user_id);
        $joueurIds = array_values(array_unique(array_filter(array_map('intval', $joueurs), static function ($value) {
            return $value > 0;
        })));

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('DELETE sj FROM seance_joueurs sj JOIN seances s ON s.id = sj.seance_id WHERE sj.seance_id = ? AND s.user_id = ?');
            $stmt->execute([$seanceId, $user_id]);

            if (count($joueurIds) > 0) {
                $placeholders = implode(',', array_fill(0, count($joueurIds), '?'));
                $params = array_merge([$user_id], $joueurIds);
                $stmt = $pdo->prepare("SELECT id FROM joueurs WHERE user_id = ? AND id IN ($placeholders)");
                $stmt->execute($params);
                $joueursValides = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

                $insertStmt = $pdo->prepare('INSERT INTO seance_joueurs (seance_id, joueur_id) VALUES (?, ?)');
                foreach ($joueursValides as $joueurId) {
                    $insertStmt->execute([$seanceId, $joueurId]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde des joueurs']);
            exit;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="f7070317-bfa5-464f-bf91-24cf10f1ad59" type="text/javascript" async></script>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planificateur d'Entraînement - <?= htmlspecialchars($date_seance) ?></title>
     <link rel="stylesheet" href="css/style.css" />
     <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-ZK321HQVXR"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-ZK321HQVXR');
</script>
</head>

<body>
    <div class="header">
  <h1>🏃‍♂️ Planificateur d'Entraînement</h1>
  <a href="home.php" class="home-btn">🏠 Accueil</a>
</div>

    <div class="date-selector centered">
    <label for="session-date">Date de la séance :</label>
    <input type="date" id="session-date" value="<?= htmlspecialchars($date_seance) ?>">
</div>

    <div class="filters">
        <button class="filter-btn active" data-category="Toutes">Toutes</button>
        <button class="filter-btn" data-category="Favoris">Favoris</button>
        <button class="filter-btn" data-category="Echauffement">Echauffement</button>
        <button class="filter-btn" data-category="Endurance">Endurance</button>
        <button class="filter-btn" data-category="Vitesse">Vitesse</button>
        <button class="filter-btn" data-category="Agilité">Agilité</button>
    </div>

    <div class="main-container">
        <div class="exercises-section">
            <h2 class="section-title">Exercices Disponibles</h2>
            <div class="exercises-grid" id="exercises-grid">
                <div class="loading">Chargement des exercices...</div>
            </div>
        </div>

        <div class="selected-section">
            <div class="summary">
  Durée totale estimée: <span id="total-duration">0</span> min
</div>
            <div class="team-assignment" id="team-assignment">
                <h3>Joueurs presents a la seance</h3>
                <div id="session-players" class="session-players-list">
                    <div class="loading">Chargement des joueurs...</div>
                </div>
            </div>
            <ul class="selected-exercises" id="selected-exercises">
                <!-- Les exercices sélectionnés apparaîtront ici -->
            </ul>
            <button id="export-pdf" class="btn btn-add" style="margin:20px auto 0 auto;display:block;">Exporter en PDF</button>
        </div>
    </div>
    <script src="js/app.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</body>
</html>