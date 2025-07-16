<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des exercices</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="header">
  <h1>🏋️‍♂️ Gestion des Exercices</h1>
  <a href="home.php" class="home-btn">🏠 Accueil</a>
</div>

    <div class="main-container">
        <div class="form-section">
            <h2 class="section-title">Ajouter / Modifier</h2>
            <form id="ex-form" class="add-form">
                <input type="hidden" id="ex-id" value="">
                
                <label for="ex-nom">Nom de l'exercice</label>
                <input type="text" id="ex-nom" required>
                
                <label for="ex-categorie">Catégorie</label>
                <input type="text" id="ex-categorie" required>
                
                <label for="ex-description">Description</label>
                <textarea id="ex-description" required></textarea>
                
                <label for="ex-duree">Durée (minutes)</label>
                <input type="number" id="ex-duree" min="1" required>
                
                <label for="ex-materiel">Matériel nécessaire</label>
                <input type="text" id="ex-materiel">
                
                <div class="form-buttons">
                    <button type="submit" class="btn btn-add" id="ex-submit">Ajouter</button>
                    <button type="button" class="btn btn-cancel" id="ex-cancel">Annuler</button>
                </div>
            </form>
        </div>

        <div class="exercises-section">
            <h2 class="section-title">Mes Exercices</h2>
            
            <!-- Filtres par catégorie -->
            <div class="filters">
                <button class="filter-btn active" onclick="filterExercises('all')">Tous</button>
                <button class="filter-btn" onclick="filterExercises('Echauffement')">Echauffement</button>
                <button class="filter-btn" onclick="filterExercises('Endurance')">Endurance</button>
                <button class="filter-btn" onclick="filterExercises('Vitesse')">Vitesse</button>
                <button class="filter-btn" onclick="filterExercises('Agilité')">Agilité</button>
            </div>
            
            <div class="exercises-grid" id="exercises">
                <div class="exercise-card">...</div>
                <div class="exercise-card">...</div>
                <!-- etc. -->
            </div>
        </div>
    </div>

    <script>
        let currentFilter = 'all';
        let allExercises = [];

        // Filtrage des exercices
        function filterExercises(category) {
            currentFilter = category;
            
            // Mise à jour des boutons de filtre
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filtrage et affichage
            const filteredExercises = category === 'all' 
                ? allExercises 
                : allExercises.filter(ex => ex.categorie === category);
            
            displayExercises(filteredExercises);
        }

        // Affichage des exercices
        function displayExercises(exercises) {
            const grid = document.getElementById('exercises');
            if (exercises.length === 0) {
                grid.innerHTML = '<div class="empty-state">Aucun exercice trouvé pour cette catégorie.</div>';
                return;
            }
            
            grid.innerHTML = exercises.map(ex =>
                `<div class="exercise-card" onclick="this.classList.toggle('flipped')">
                    <div class="card-inner">
                        <div class="card-front">
                            <div class="exercise-title">${ex.nom}</div>
                            <div class="exercise-category">${ex.categorie}</div>
                            <div class="exercise-duration">${ex.duree} min</div>
                            <div class="card-actions">
                                <button class="btn btn-edit" onclick="editExerciseFromBtn(this)" 
                                    data-id="${ex.id}"
                                    data-nom="${escapeQuotes(ex.nom)}"
                                    data-categorie="${escapeQuotes(ex.categorie)}"
                                    data-description="${escapeQuotes(ex.description)}"
                                    data-duree="${escapeQuotes(ex.duree)}"
                                    data-materiel="${escapeQuotes(ex.materiel)}"
                                >Modifier</button>
                                <button class="btn btn-delete" onclick="deleteExercise('${ex.id}')">Supprimer</button>
                            </div>
                        </div>
                        <div class="card-back">
                            <div class="exercise-details">
                                <div class="detail-item">
                                    <strong>Description :</strong><br>
                                    ${ex.description || 'Aucune description'}
                                </div>
                                <div class="detail-item">
                                    <strong>Durée :</strong> ${ex.duree} minutes
                                </div>
                                <div class="detail-item">
                                    <strong>Matériel :</strong><br>
                                    ${ex.materiel || 'Aucun matériel requis'}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>`
            ).join('');
        }

        // Chargement et affichage des exercices
        function loadExercises() {
            fetch('api_exercices.php')
                .then(res => res.json())
                .then(data => {
                    if (data.error) {
                        document.getElementById('exercises').innerHTML = `<div class="empty-state">${data.error}</div>`;
                        return;
                    }
                    allExercises = data;
                    displayExercises(allExercises);
                    console.log('Données reçues de l\'API :', data);
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    document.getElementById('exercises').innerHTML = '<div class="empty-state">Erreur lors du chargement des exercices.</div>';
                });
        }

        // Ajout ou modification d'un exercice
        document.getElementById('ex-form').onsubmit = function(e) {
            e.preventDefault();
            const id = document.getElementById('ex-id').value;
            const nom = document.getElementById('ex-nom').value;
            const categorie = document.getElementById('ex-categorie').value;
            const description = document.getElementById('ex-description').value;
            const duree = document.getElementById('ex-duree').value;
            const materiel = document.getElementById('ex-materiel').value;
            const formData = new URLSearchParams({
                id, nom, categorie, description, duree, materiel,
                action: id ? 'modifier' : 'ajouter'
            });
            fetch('api_exercices.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    resetForm();
                    loadExercises();
                } else {
                    alert(data.error || "Erreur lors de l'enregistrement.");
                }
            });
        };

        // Pré-remplir le formulaire pour modification
        function editExercise(id, nom, categorie, description, duree, materiel) {
            document.getElementById('ex-id').value = id;
            document.getElementById('ex-nom').value = nom;
            document.getElementById('ex-categorie').value = categorie;
            document.getElementById('ex-description').value = description;
            document.getElementById('ex-duree').value = duree;
            document.getElementById('ex-materiel').value = materiel;
            document.getElementById('ex-submit').textContent = "Modifier";
            document.getElementById('ex-cancel').style.display = "block";
        }

        // Annuler la modification
        document.getElementById('ex-cancel').onclick = resetForm;
        function resetForm() {
            document.getElementById('ex-form').reset();
            document.getElementById('ex-id').value = "";
            document.getElementById('ex-submit').textContent = "Ajouter";
            document.getElementById('ex-cancel').style.display = "none";
        }

        // Suppression d'un exercice
        function deleteExercise(id) {
            if (!confirm("Supprimer cet exercice ?")) return;
            fetch('api_exercices.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({id, action: 'supprimer'})
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadExercises();
                } else {
                    alert(data.error || "Erreur lors de la suppression.");
                }
            });
        }

        // Sécurité pour les quotes dans les attributs HTML
        function escapeQuotes(str) {
            return String(str).replace(/'/g, "\\'").replace(/"/g, '&quot;');
        }

        // Échappement des caractères HTML
        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, "&amp;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#39;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;");
        }

        // Initialisation au chargement de la page
        document.addEventListener('DOMContentLoaded', function() {
            loadExercises();
            // Masquer le bouton annuler par défaut
            document.getElementById('ex-cancel').style.display = "none";
        });

        function editExerciseFromBtn(btn) {
            editExercise(
                btn.getAttribute('data-id'),
                btn.getAttribute('data-nom'),
                btn.getAttribute('data-categorie'),
                btn.getAttribute('data-description'),
                btn.getAttribute('data-duree'),
                btn.getAttribute('data-materiel')
            );
        }
    </script>
</body>
</html>