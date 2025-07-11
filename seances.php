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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }

        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            text-align: center;
            color: white;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header h1 {
            font-size: 2rem;
            margin: 0;
        }

        .home-btn {
            background: #4ecdc4;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .home-btn:hover {
            background: #45b7aa;
            transform: translateY(-2px);
        }

        .date-selector {
            text-align: center;
            margin: 20px 0;
        }

        .date-selector input {
            padding: 10px 15px;
            border: none;
            border-radius: 25px;
            background: white;
            font-size: 16px;
            margin: 0 10px;
        }

        .filters {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 30px 0;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 25px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 14px;
        }

        .filter-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .filter-btn.active {
            background: #ff6b6b;
            color: white;
        }

        .main-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            max-width: 1400px;
            margin: 0 auto;
        }

        .exercises-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 15px;
        }

        .section-title {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
        }

        .exercises-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }

        .exercise-card {
            perspective: 1000px;
            height: 200px;
            cursor: pointer;
        }

        .card-inner {
            position: relative;
            width: 100%;
            height: 100%;
            text-align: center;
            transition: transform 0.6s;
            transform-style: preserve-3d;
        }

        .exercise-card.flipped .card-inner {
            transform: rotateY(180deg);
        }

        .card-front, .card-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            border-radius: 15px;
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .card-front {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 50%, #fecfef 100%);
            color: #333;
        }

        .card-back {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            transform: rotateY(180deg);
            color: #333;
        }

        .exercise-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 10px;
            text-align: center;
        }

        .exercise-category {
            background: rgba(255, 255, 255, 0.3);
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            margin-bottom: 15px;
        }

        .add-btn {
            background: #4ecdc4;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
            cursor: pointer;
            font-size: 14px;
            transition: all 0.3s ease;
            margin-top: auto;
        }

        .add-btn:hover {
            background: #45b7aa;
            transform: translateY(-2px);
        }

        .add-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .exercise-details {
            font-size: 0.9rem;
            line-height: 1.4;
            text-align: left;
            overflow-y: auto;
        }

        .selected-section {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            padding: 25px;
            border-radius: 15px;
            height: fit-content;
        }

        .selected-exercises {
            list-style: none;
        }

        .selected-exercise {
            background: rgba(255, 255, 255, 0.2);
            margin-bottom: 10px;
            padding: 15px;
            border-radius: 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: white;
        }

        .remove-btn {
            background: #ff6b6b;
            color: white;
            border: none;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .remove-btn:hover {
            background: #ff5252;
            transform: scale(1.1);
        }

        .summary {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            color: white;
        }

        .loading {
            text-align: center;
            color: white;
            padding: 20px;
        }

        @media (max-width: 768px) {
            .main-container {
                grid-template-columns: 1fr;
            }
            
            .exercises-grid {
                grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            }
            
            .header h1 {
                font-size: 1.5rem;
            }

            .header {
                flex-direction: column;
                gap: 15px;
            }
        }

        .duration-info {
            font-size: 0.8rem;
            margin: 5px 0;
        }

        .material-info {
            font-size: 0.8rem;
            color: #666;
        }

        .empty-state {
            text-align: center;
            color: white;
            padding: 40px;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🏃‍♂️ Planificateur d'Entraînement</h1>
        <a href="home.php" class="home-btn">🏠 Accueil</a>
    </div>

    <div class="date-selector">
        <label for="session-date" style="color: white; margin-right: 10px;">Date de la séance:</label>
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
                            <button class="add-btn" ${isSelected ? 'disabled' : ''} onclick="addExercise(${exercise.id}); event.stopPropagation();">
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
                        <span style="font-size:0.9em;opacity:0.7;"> (${ex.categorie})</span>
                    </span>
                    <button class="remove-btn" title="Retirer" onclick="removeExercise(${ex.id}); event.stopPropagation();">&times;</button>
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