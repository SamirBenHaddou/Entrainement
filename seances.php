<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// === 4. seances.php (Interface principale améliorée) ===
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

try {
    $pdo = new PDO('mysql:host=localhost;dbname=entrainement', 'root', 'BeagroupSamir!', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die('Erreur base de données : ' . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$date_seance = $_GET['date'] ?? date('Y-m-d');

// API endpoints pour AJAX
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    if ($_GET['api'] === 'exercices') {
        $stmt = $pdo->query('SELECT * FROM exercices ORDER BY categorie, nom');
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
}

// Gestion des actions POST via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'ajouter_exercice') {
        $exercice_id = intval($_POST['exercice_id']);
        $date = $_POST['date'];

        // Créer ou récupérer la séance
        $stmt = $pdo->prepare('SELECT id FROM seances WHERE date_seance = ? AND user_id = ?');
        $stmt->execute([$date, $user_id]);
        $seance = $stmt->fetch();

        if (!$seance) {
            $stmt = $pdo->prepare('INSERT INTO seances (date_seance, user_id) VALUES (?, ?)');
            $stmt->execute([$date, $user_id]);
            $seance_id = $pdo->lastInsertId();
        } else {
            $seance_id = $seance['id'];
        }

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
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planificateur d'Entraînement - <?= htmlspecialchars($date_seance) ?></title>
     <link rel="stylesheet" href="css/style.css" />
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
            <h2 class="section-title">Séance Planifiée</h2>
            <div class="summary">
                <strong>Résumé:</strong><br>
                <span id="exercise-count">0</span> exercices sélectionnés<br>
                Durée totale estimée: <span id="total-duration">0</span> min
            </div>
            <ul class="selected-exercises" id="selected-exercises">
                <!-- Les exercices sélectionnés apparaîtront ici -->
            </ul>
        </div>
    </div>

    <script>
        let allExercises = [];
        let selectedExercises = [];
        let currentFilter = 'Toutes';
        let currentDate = document.getElementById('session-date').value;

        // Charger les exercices depuis l'API
        async function loadExercises() {
            try {
                const response = await fetch('seances.php?api=exercices');
                allExercises = await response.json();
                renderExercises();
            } catch (error) {
                console.error('Erreur lors du chargement des exercices:', error);
                document.getElementById('exercises-grid').innerHTML = 
                    '<div class="empty-state">Erreur lors du chargement des exercices</div>';
            }
        }

        // Charger les exercices sélectionnés pour la date courante
        async function loadSelectedExercises() {
            try {
                const response = await fetch(`seances.php?api=seance&date=${currentDate}`);
                selectedExercises = await response.json();
                renderSelectedExercises();
                updateSummary();
            } catch (error) {
                console.error('Erreur lors du chargement de la séance:', error);
            }
        }

        function renderExercises() {
            const grid = document.getElementById('exercises-grid');
            const filteredExercises = currentFilter === 'Toutes' 
                ? allExercises 
                : allExercises.filter(ex => ex.categorie === currentFilter);

            if (filteredExercises.length === 0) {
                grid.innerHTML = '<div class="empty-state">Aucun exercice trouvé pour cette catégorie</div>';
                return;
            }

            grid.innerHTML = filteredExercises.map(exercise => {
                const isSelected = selectedExercises.some(sel => sel.id == exercise.id);
                return `
                <div class="exercise-card${isSelected ? ' selected' : ''}" data-id="${exercise.id}">
                    <div class="card-inner">
                        <div class="card-front">
                            <div class="exercise-title">${exercise.nom}</div>
                            <div class="exercise-category">${exercise.categorie}</div>
                            <button class="btn btn-add" ${isSelected ? 'disabled' : ''} onclick="addExercise(${exercise.id}); event.stopPropagation();">
                                ${isSelected ? 'Ajouté' : 'Ajouter'}
                            </button>
                        </div>
                        <div class="card-back">
                            <div class="exercise-details">
                                <strong>Description :</strong> ${exercise.description || '—'}<br>
                                <span class="duration-info"><strong>Durée :</strong> ${exercise.duree || '—'} min</span><br>
                                <span class="material-info"><strong>Matériel :</strong> ${exercise.materiel || '—'}</span>
                            </div>
                        </div>
                    </div>
                </div>
                `;
            }).join('');

            // Ajoute le flip au clic sur la carte
            document.querySelectorAll('.exercise-card').forEach(card => {
                card.addEventListener('click', function(e) {
                    if (!e.target.classList.contains('add-btn')) {
                        this.classList.toggle('flipped');
                    }
                });
            });
        }

        // Ajout d'un exercice à la séance
        async function addExercise(exerciceId) {
            const response = await fetch('seances.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=ajouter_exercice&exercice_id=${exerciceId}&date=${encodeURIComponent(currentDate)}`
            });
            const text = await response.text();
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                alert("Erreur serveur :\n" + text);
                return;
            }
            if (result.success) {
                await loadSelectedExercises();
                renderExercises();
            } else {
                alert(result.message || "Erreur lors de l'ajout.");
            }
        }

        // Suppression d'un exercice de la séance
        async function removeExercise(exerciceId) {
            const response = await fetch('seances.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=supprimer_exercice&exercice_id=${exerciceId}&date=${encodeURIComponent(currentDate)}`
            });
            const result = await response.json();
            if (result.success) {
                await loadSelectedExercises();
                renderExercises();
            }
        }

        // Affichage des exercices sélectionnés
        function renderSelectedExercises() {
            const ul = document.getElementById('selected-exercises');
            if (selectedExercises.length === 0) {
                ul.innerHTML = '<li class="empty-state">Aucun exercice sélectionné</li>';
                return;
            }
            ul.innerHTML = selectedExercises.map(ex =>
    `<li class="selected-exercise">
        <span>
            <strong>${ex.nom}</strong>
            <span class="selected-category"> (${ex.categorie})</span>
        </span>
        <button class="btn btn-delete remove-btn" title="Retirer" onclick="removeExercise(${ex.id}); event.stopPropagation();">&times;</button>
    </li>`
).join('');
        }

        // Mise à jour du résumé
        function updateSummary() {
            document.getElementById('exercise-count').textContent = selectedExercises.length;
            // Additionne les durées (en supposant que ex.duree est un nombre ou chaîne convertible)
            const total = selectedExercises.reduce((sum, ex) => sum + (parseInt(ex.duree) || 0), 0);
            document.getElementById('total-duration').textContent = total;
        }

        // Gestion des filtres
        document.querySelectorAll('.filter-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFilter = this.dataset.category;
                renderExercises();
            });
        });

        // Changement de date
        document.getElementById('session-date').addEventListener('change', function() {
            currentDate = this.value;
            loadSelectedExercises();
        });

        // Pour accès global depuis HTML inline
        window.addExercise = addExercise;
        window.removeExercise = removeExercise;

        // Initialisation
        loadExercises().then(loadSelectedExercises);
    </script>
</body>
</html>