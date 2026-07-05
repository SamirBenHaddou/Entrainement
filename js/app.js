(() => {
  let allExercises = [];
  let selectedExercises = [];
  let allPlayers = [];
  let assignedPlayers = [];
  let currentFilter = "Toutes";
  let currentDate = document.getElementById("session-date").value;

  // Charger les exercices depuis l'API
  async function loadExercises() {
    try {
      const response = await fetch("seances.php?api=exercices");
      allExercises = await response.json();
      renderExercises();
    } catch (error) {
      console.error("Erreur lors du chargement des exercices:", error);
      document.getElementById("exercises-grid").innerHTML =
        '<div class="empty-state">Erreur lors du chargement des exercices</div>';
    }
  }

  // Charger les exercices sélectionnés pour la date courante
  async function loadSelectedExercises() {
    try {
      const response = await fetch(
        `seances.php?api=seance&date=${currentDate}`,
      );
      selectedExercises = await response.json();
      renderSelectedExercises();
      updateSummary();
    } catch (error) {
      console.error("Erreur lors du chargement de la séance:", error);
    }
  }

  async function loadPlayers() {
    try {
      const response = await fetch("seances.php?api=joueurs");
      allPlayers = await response.json();
      renderSessionPlayers();
    } catch (error) {
      console.error("Erreur lors du chargement des joueurs:", error);
      document.getElementById("session-players").innerHTML =
        '<div class="empty-state">Erreur lors du chargement des joueurs</div>';
    }
  }

  async function loadAssignedPlayers() {
    try {
      const response = await fetch(
        `seances.php?api=joueurs_seance&date=${encodeURIComponent(currentDate)}`,
      );
      assignedPlayers = await response.json();
      renderSessionPlayers();
    } catch (error) {
      console.error("Erreur lors du chargement des joueurs assignés:", error);
    }
  }

  // Affichage des exercices disponibles
  function renderExercises() {
    const grid = document.getElementById("exercises-grid");
    const filteredExercises =
      currentFilter === "Toutes"
        ? allExercises.filter(
            (ex) => !selectedExercises.some((sel) => sel.id == ex.id),
          )
        : currentFilter === "Favoris"
          ? allExercises.filter(
              (ex) =>
                Number(ex.favori) === 1 &&
                !selectedExercises.some((sel) => sel.id == ex.id),
            )
          : allExercises.filter(
              (ex) =>
                ex.categorie === currentFilter &&
                !selectedExercises.some((sel) => sel.id == ex.id),
            );

    if (filteredExercises.length === 0) {
      grid.innerHTML =
        '<div class="empty-state">Aucun exercice trouvé pour cette catégorie</div>';
      return;
    }

    grid.innerHTML = filteredExercises
      .map((exercise) => {
        return `
        <div class="exercise-card" data-id="${exercise.id}">
            <div class="card-inner">
                <div class="card-front">
                    <div class="exercise-title">${exercise.nom}</div>
                    ${Number(exercise.favori) === 1 ? '<div class="exercise-category">★ Favori</div>' : ""}
                    <div class="exercise-category">${exercise.categorie}</div>
                    <div class="form-buttons" style="margin-top: 8px;">
                      <button class="btn btn-edit" onclick="toggleExerciseFavorite(${exercise.id}); event.stopPropagation();">
                        ${Number(exercise.favori) === 1 ? "Retirer favori" : "Mettre en favori"}
                      </button>
                      <button class="btn btn-add" onclick="addExercise(${exercise.id}); event.stopPropagation();">
                        Ajouter
                      </button>
                    </div>
                </div>
                <div class="card-back">
                    <div class="exercise-details">
                        <strong>Description :</strong> ${
                          exercise.description || "—"
                        }<br>
                        <span class="duration-info"><strong>Durée :</strong> ${
                          exercise.duree || "—"
                        } min</span><br>
                        <span class="material-info"><strong>Matériel :</strong> ${
                          exercise.materiel || "—"
                        }</span>
                    </div>
                </div>
            </div>
        </div>
        `;
      })
      .join("");
  }

  // Affichage des exercices sélectionnés
  function renderSelectedExercises() {
    const ul = document.getElementById("selected-exercises");
    if (selectedExercises.length === 0) {
      ul.innerHTML = '<li class="empty-state">Aucun exercice sélectionné</li>';
      return;
    }
    ul.innerHTML = selectedExercises
      .map(
        (ex) => `
            <li class="selected-exercise-card">
            <div class="exercise-card" data-id="${ex.id}">
                <div class="card-inner">
                    <div class="card-front">
                        <div class="exercise-title">${ex.nom}</div>
                        <div class="exercise-category">${ex.categorie}</div>
                        <button class="btn btn-delete remove-btn" title="Retirer" onclick="removeExercise(${
                          ex.id
                        }); event.stopPropagation();">&times;</button>
                    </div>
                    <div class="card-back">
                        <div class="exercise-details">
                            <strong>Description :</strong> ${
                              ex.description || "—"
                            }<br>
                            <span class="duration-info"><strong>Durée :</strong> ${
                              ex.duree || "—"
                            } min</span><br>
                            <span class="material-info"><strong>Matériel :</strong> ${
                              ex.materiel || "—"
                            }</span>
                        </div>
                    </div>
                </div>
            </div>
        </li>`,
      )
      .join("");
  }

  function renderSessionPlayers() {
    const container = document.getElementById("session-players");
    if (!container) return;

    if (allPlayers.length === 0) {
      container.innerHTML =
        '<div class="empty-state team-empty">Ajoutez d\'abord des joueurs depuis la page équipe.</div>';
      return;
    }

    const assignedIds = new Set(
      assignedPlayers.map((player) => Number(player.id)),
    );
    container.innerHTML = allPlayers
      .map((player) => {
        const checked = assignedIds.has(Number(player.id)) ? "checked" : "";
        const poste = player.poste ? `<span>${player.poste}</span>` : "";
        return `
          <label class="session-player-item">
            <input type="checkbox" value="${player.id}" ${checked}>
            <div>
              <strong>${player.nom}</strong>
              ${poste}
            </div>
          </label>
        `;
      })
      .join("");
  }

  async function saveAssignedPlayers() {
    const container = document.getElementById("session-players");
    if (!container) return;

    const checkedPlayers = Array.from(
      container.querySelectorAll('input[type="checkbox"]:checked'),
    ).map((input) => Number(input.value));

    const body = new URLSearchParams();
    body.set("action", "enregistrer_joueurs_seance");
    body.set("date", currentDate);
    checkedPlayers.forEach((id) => body.append("joueurs[]", id));

    const response = await fetch("seances.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    });

    const result = await response.json();
    if (!result.success) {
      alert(result.message || "Erreur lors de la sauvegarde des joueurs.");
      return;
    }

    assignedPlayers = allPlayers.filter((player) =>
      checkedPlayers.includes(Number(player.id)),
    );
    renderSessionPlayers();
  }

  // Ajout d'un exercice à la séance
  async function addExercise(exerciceId) {
    const response = await fetch("seances.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `action=ajouter_exercice&exercice_id=${exerciceId}&date=${encodeURIComponent(
        currentDate,
      )}`,
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
      updateSummary();
    } else {
      alert(result.message || "Erreur lors de l'ajout.");
    }
  }

  async function toggleExerciseFavorite(exerciceId) {
    const response = await fetch("seances.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `action=basculer_favori_exercice&exercice_id=${exerciceId}`,
    });
    const result = await response.json();
    if (result.success) {
      await loadExercises();
    }
  }

  // Suppression d'un exercice de la séance
  async function removeExercise(exerciceId) {
    const response = await fetch("seances.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: `action=supprimer_exercice&exercice_id=${exerciceId}&date=${encodeURIComponent(
        currentDate,
      )}`,
    });
    const result = await response.json();
    if (result.success) {
      await loadSelectedExercises();
      renderExercises();
      updateSummary();
    }
  }

  // Mise à jour du résumé
  function updateSummary() {
    const total = selectedExercises.reduce(
      (sum, ex) => sum + (parseInt(ex.duree) || 0),
      0,
    );
    document.getElementById("total-duration").textContent = total;
  }

  // Gestion des filtres
  document.querySelectorAll(".filter-btn").forEach((btn) => {
    btn.addEventListener("click", function () {
      document
        .querySelectorAll(".filter-btn")
        .forEach((b) => b.classList.remove("active"));
      this.classList.add("active");
      currentFilter = this.dataset.category;
      renderExercises();
    });
  });

  // Changement de date
  document
    .getElementById("session-date")
    .addEventListener("change", function () {
      currentDate = this.value;
      loadSelectedExercises();
      loadAssignedPlayers();
    });

  document
    .getElementById("session-players")
    .addEventListener("change", function (event) {
      if (event.target.matches('input[type="checkbox"]')) {
        saveAssignedPlayers();
      }
    });

  // Délégation d'événement pour le flip sur les cartes sélectionnées
  document
    .getElementById("selected-exercises")
    .addEventListener("click", function (e) {
      const card = e.target.closest(".exercise-card");
      if (!card) return;
      if (
        e.target.classList.contains("remove-btn") ||
        e.target.classList.contains("btn-delete")
      )
        return;
      card.classList.toggle("flipped");
    });

  // ✅ Délégation pour le flip sur les cartes disponibles
  document
    .getElementById("exercises-grid")
    .addEventListener("click", function (e) {
      const card = e.target.closest(".exercise-card");
      if (!card) return;
      if (e.target.classList.contains("btn-add")) return;
      card.classList.toggle("flipped");
    });

  // Pour accès global depuis HTML inline
  window.addExercise = addExercise;
  window.removeExercise = removeExercise;
  window.toggleExerciseFavorite = toggleExerciseFavorite;

  // Initialisation
  loadExercises().then(loadSelectedExercises);
  loadPlayers().then(loadAssignedPlayers);

  // Export PDF
  document.getElementById("export-pdf").addEventListener("click", function () {
    let items = document.querySelectorAll(
      ".selected-exercise-card .exercise-card",
    );
    if (items.length === 0) {
      alert("Aucun exercice à exporter !");
      return;
    }

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();

    const dateSeance = document.getElementById("session-date").value;

    let totalDuration = 0;
    items.forEach((card) => {
      const duree = card.querySelector(".card-back .duration-info");
      if (duree) {
        const match = duree.textContent.match(/(\d+)/);
        if (match) totalDuration += parseInt(match[1]);
      }
    });

    doc.setFont("helvetica", "bold");
    doc.setFontSize(18);
    doc.text("Séance Planifiée", 10, 15);

    doc.setFontSize(14);
    doc.setFont("helvetica", "normal");
    doc.text(`Date : ${dateSeance}`, 10, 25);
    doc.text(`Durée totale : ${totalDuration} min`, 10, 32);

    let y = 42;
    items.forEach((card, idx) => {
      const title = card.querySelector(".exercise-title").textContent.trim();
      const desc = card
        .querySelector(".card-back .exercise-details")
        .textContent.trim();

      doc.setFontSize(14);
      doc.setFont("helvetica", "bold");
      doc.text(`${idx + 1}. ${title}`, 10, y);

      y += 8;
      doc.setFontSize(12);
      doc.setFont("helvetica", "normal");

      const descLines = doc.splitTextToSize(desc, 180);
      doc.text(descLines, 12, y);

      y += descLines.length * 7 + 8;

      if (y > 270) {
        doc.addPage();
        y = 20;
      }
    });

    doc.save("seance.pdf");
  });
})();
