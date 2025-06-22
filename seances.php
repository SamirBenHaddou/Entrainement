<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); // page de connexion
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=entrainement', 'root', '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die('Erreur base de données : ' . $e->getMessage());
}

$categories = ['Toutes', 'Échauffement', 'Endurance', 'Vitesse', 'Agilité', 'Précision'];

$date_seance = $_GET['date'] ?? date('Y-m-d');

$stmt = $pdo->prepare('SELECT * FROM seances WHERE date_seance = ?');
$stmt->execute([$date_seance]);
$seance = $stmt->fetch();

if (!$seance) {
    $stmt = $pdo->prepare('INSERT INTO seances (date_seance) VALUES (?)');
    $stmt->execute([$date_seance]);
    $seance_id = $pdo->lastInsertId();
} else {
    $seance_id = $seance['id'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['ajouter_exercice'])) {
        $exercice_id = intval($_POST['exercice_id']);
        $stmt = $pdo->prepare('SELECT 1 FROM seance_exercices WHERE seance_id = ? AND exercice_id = ?');
        $stmt->execute([$seance_id, $exercice_id]);
        if (!$stmt->fetch()) {
            $stmt = $pdo->prepare('INSERT INTO seance_exercices (seance_id, exercice_id) VALUES (?, ?)');
            $stmt->execute([$seance_id, $exercice_id]);
        }
    } elseif (isset($_POST['supprimer_exercice'])) {
        $exercice_id = intval($_POST['exercice_id']);
        $stmt = $pdo->prepare('DELETE FROM seance_exercices WHERE seance_id = ? AND exercice_id = ?');
        $stmt->execute([$seance_id, $exercice_id]);
    }
    header("Location: seances.php?date=" . urlencode($date_seance));
    exit;
}

$categorie_filter = $_GET['categorie'] ?? 'Toutes';

if ($categorie_filter === 'Toutes') {
    $stmt = $pdo->query('SELECT * FROM exercices ORDER BY categorie, nom');
} else {
    $stmt = $pdo->prepare('SELECT * FROM exercices WHERE categorie = ? ORDER BY nom');
    $stmt->execute([$categorie_filter]);
}
$exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('
    SELECT e.*
    FROM exercices e
    JOIN seance_exercices se ON e.id = se.exercice_id
    WHERE se.seance_id = ?
    ORDER BY e.nom
');
$stmt->execute([$seance_id]);
$exercices_selectionnes = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <title>Gestion des séances - <?= htmlspecialchars($date_seance) ?></title>
    <link rel="stylesheet" href="css/style.css" />
</head>
<body>
<header class="header">
    <h1>Séance du <?= htmlspecialchars($date_seance) ?></h1>
</header>

<main>
    <form method="get" class="form-date">
        <label for="date_seance">Choisissez la date :</label>
        <input
            type="date"
            id="date_seance"
            name="date"
            value="<?= htmlspecialchars($date_seance) ?>"
            onchange="this.form.submit()"
        />
    </form>

    <nav class="filters">
        <?php foreach ($categories as $cat): ?>
            <a
              href="?date=<?= urlencode($date_seance) ?>&categorie=<?= urlencode($cat) ?>"
              class="<?= $cat === $categorie_filter ? 'active' : '' ?>"
            >
              <?= htmlspecialchars($cat) ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <section>
        <h2>Exercices disponibles</h2>
        <div class="cards-container">
            <?php foreach ($exercices as $ex): ?>
                <div class="card" tabindex="0" onclick="this.classList.toggle('flipped')">
                    <div class="card-inner">
                        <div class="card-front">
                            <?= htmlspecialchars($ex['nom']) ?><br>
                            <small><em><?= htmlspecialchars($ex['categorie']) ?></em></small>
                            <form method="post" class="form-ajout-exercice">
                                <input type="hidden" name="exercice_id" value="<?= $ex['id'] ?>" />
                                <button type="submit" name="ajouter_exercice">Ajouter</button>
                            </form>
                        </div>
                        <div class="card-back">
                            <strong>Détails :</strong><br />
                            <?= nl2br(htmlspecialchars($ex['description'])) ?><br />
                            Durée : <?= htmlspecialchars($ex['duree']) ?><br />
                            Matériel : <?= htmlspecialchars($ex['materiel']) ?><br />
                            Âge min : <?= htmlspecialchars($ex['age_min']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section>
        <h2>Exercices sélectionnés pour cette séance</h2>
        <?php if (empty($exercices_selectionnes)): ?>
            <p>Aucun exercice sélectionné.</p>
        <?php else: ?>
            <ul class="selection-list">
                <?php foreach ($exercices_selectionnes as $ex): ?>
                    <li>
                        <?= htmlspecialchars($ex['nom']) ?> (<?= htmlspecialchars($ex['categorie']) ?>)
                        <form method="post" class="form-supprimer-exercice">
                            <input type="hidden" name="exercice_id" value="<?= $ex['id'] ?>" />
                            <button type="submit" name="supprimer_exercice" title="Retirer">×</button>
                        </form>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</main>

</body>
</html>
