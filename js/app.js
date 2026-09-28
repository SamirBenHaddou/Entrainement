(() => {
  let allExercises = [];
  let selectedExercises = [];
  let allPlayers = [];
  let assignedPlayers = [];
  const urlParams = new URLSearchParams(window.location.search);
  const urlTeamId = Number(urlParams.get("equipe_id") || 0);
  let currentFilter = "Toutes";
  let currentDate = document.getElementById("session-date").value;
  const teamSelect = document.getElementById("session-team");
  let currentTeamId = Number(teamSelect?.value || urlTeamId || 0);
  let draggedExerciseId = null;
  let aiProposal = null;
  let mobileSelectedOpen = false;
  const mobileSelectedToggle = document.getElementById(
    "mobile-selected-toggle",
  );
  const mobileSelectedClose = document.getElementById("mobile-selected-close");
  const mobileSelectedCloseBottom = document.getElementById(
    "mobile-selected-close-bottom",
  );
  const mobileSelectedCount = document.getElementById("mobile-selected-count");
  const filters = {
    search: "",
    favoritesOnly: false,
    durationMax: "",
    trainingFormat: "individuel",
    sort: "favoris",
  };
  const preferredCategories = [
    "Echauffement",
    "Endurance",
    "Vitesse",
    "Agilité",
  ];

  function normalizeText(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  }

  function buildApiUrl(api, extraParams = {}) {
    const params = new URLSearchParams({
      api,
      equipe_id: String(currentTeamId),
      ...extraParams,
    });
    return `seances.php?${params.toString()}`;
  }

  function withTeamBody(body) {
    if ((!currentTeamId || currentTeamId <= 0) && teamSelect) {
      currentTeamId = Number(teamSelect.value || 0);
    }
    if (!currentTeamId || currentTeamId <= 0) {
      currentTeamId = Number(urlTeamId || 0);
    }
    body.set("equipe_id", String(currentTeamId));
    return body;
  }

  function updatePlannerUrl() {
    const params = new URLSearchParams(window.location.search);
    params.set("date", currentDate);
    params.set("equipe_id", String(currentTeamId));
    window.history.replaceState({}, "", `seances.php?${params.toString()}`);
  }

  function getExerciseCategories() {
    return Array.from(
      new Set(
        allExercises
          .map((exercise) => String(exercise.categorie || "").trim())
          .filter(Boolean),
      ),
    ).sort((first, second) => first.localeCompare(second, "fr"));
  }

  function syncCategoryControls() {
    const quickFilters = document.getElementById("quick-category-filters");
    const categories = getExerciseCategories();
    const extraCategories = categories.filter(
      (category) => !preferredCategories.includes(category),
    );

    quickFilters.innerHTML = [
      '<button class="filter-btn active" data-category="Toutes">Toutes</button>',
      '<button class="filter-btn" data-category="Favoris">Favoris</button>',
      ...preferredCategories.map(
        (category) =>
          `<button class="filter-btn" data-category="${category}">${category}</button>`,
      ),
      ...extraCategories
        .slice(0, 4)
        .map(
          (category) =>
            `<button class="filter-btn" data-category="${category}">${category}</button>`,
        ),
    ].join("");

    quickFilters.querySelectorAll(".filter-btn").forEach((button) => {
      button.classList.toggle(
        "active",
        button.dataset.category === currentFilter,
      );
    });
  }

  function getFilteredExercises() {
    const selectedIds = new Set(
      selectedExercises.map((exercise) => Number(exercise.id)),
    );
    const searchTerm = normalizeText(filters.search);
    const durationMax =
      filters.durationMax === "" ? null : Number(filters.durationMax);

    return allExercises
      .filter((exercise) => !selectedIds.has(Number(exercise.id)))
      .filter((exercise) => {
        if (currentFilter === "Favoris" && Number(exercise.favori) !== 1) {
          return false;
        }

        if (currentFilter !== "Toutes" && currentFilter !== "Favoris") {
          return exercise.categorie === currentFilter;
        }

        return true;
      })
      .filter((exercise) => {
        if (filters.favoritesOnly && Number(exercise.favori) !== 1) {
          return false;
        }

        const trainingFormat = String(
          exercise.format_entrainement || "mixte",
        ).trim();
        if (
          trainingFormat !== filters.trainingFormat &&
          trainingFormat !== "mixte"
        ) {
          return false;
        }

        if (
          durationMax !== null &&
          durationMax > 0 &&
          Number(exercise.duree) > durationMax
        ) {
          return false;
        }

        if (searchTerm === "") {
          return true;
        }

        const haystack = normalizeText(
          [
            exercise.nom,
            exercise.categorie,
            exercise.description,
            exercise.materiel,
          ].join(" "),
        );

        return haystack.includes(searchTerm);
      })
      .sort((first, second) => {
        if (filters.sort === "nom") {
          return String(first.nom).localeCompare(String(second.nom), "fr");
        }

        if (filters.sort === "duree_courte") {
          return (Number(first.duree) || 0) - (Number(second.duree) || 0);
        }

        if (filters.sort === "duree_longue") {
          return (Number(second.duree) || 0) - (Number(first.duree) || 0);
        }

        const favoriteDelta = Number(second.favori) - Number(first.favori);
        if (favoriteDelta !== 0) {
          return favoriteDelta;
        }

        return String(first.nom).localeCompare(String(second.nom), "fr");
      });
  }

  function updateResultsSummary(filteredExercises) {
    const summary = document.getElementById("exercise-results-summary");
    if (!summary) return;

    const total = allExercises.length;
    const displayed = filteredExercises.length;
    const selected = selectedExercises.length;
    const activeFilters = [];

    if (currentFilter !== "Toutes") {
      activeFilters.push(`raccourci: ${currentFilter}`);
    }
    if (filters.search) {
      activeFilters.push(`recherche: ${filters.search}`);
    }
    if (filters.favoritesOnly) {
      activeFilters.push("favoris uniquement");
    }
    activeFilters.push(`format: ${filters.trainingFormat} + mixte`);
    if (filters.durationMax) {
      activeFilters.push(`duree <= ${filters.durationMax} min`);
    }

    const suffix =
      activeFilters.length > 0
        ? ` Filtres actifs: ${activeFilters.join(" | ")}.`
        : "";
    summary.textContent = `${displayed} exercice(s) disponibles sur ${total}. ${selected} deja ajoute(s) a la seance.${suffix}`;
  }

  // Charger les exercices depuis l'API
  async function loadExercises() {
    try {
      const response = await fetch(buildApiUrl("exercices"));
      allExercises = await response.json();
      syncCategoryControls();
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
        buildApiUrl("seance", { date: currentDate }),
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
      const response = await fetch(buildApiUrl("joueurs"));
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
        buildApiUrl("joueurs_seance", { date: currentDate }),
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
    const filteredExercises = getFilteredExercises();
    updateResultsSummary(filteredExercises);

    if (filteredExercises.length === 0) {
      grid.innerHTML =
        '<div class="empty-state">Aucun exercice ne correspond aux filtres actuels</div>';
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
                    <div class="exercise-category">${formatTrainingType(exercise.format_entrainement)}</div>
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
                        <span><strong>Type :</strong> ${formatTrainingType(
                          exercise.format_entrainement,
                        )}</span><br>
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
      updateMobileSelectedUi();
      return;
    }
    ul.innerHTML = selectedExercises
      .map(
        (ex) => `
            <li class="selected-exercise-card" draggable="true" data-seance-exercice-id="${ex.seance_exercice_id}" data-exercice-id="${ex.id}">
            <div class="exercise-card" data-id="${ex.id}">
                <div class="card-inner">
                    <div class="card-front">
                        <div class="selected-exercise-meta">
                            <span class="drag-handle" title="Glisser pour réordonner">⇅</span>
                            <span class="exercise-order">${Number(ex.ordre) || 0}</span>
                        </div>
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
    updateMobileSelectedUi();
  }

  function isMobileViewport() {
    return window.matchMedia("(max-width: 768px)").matches;
  }

  function updateMobileSelectedUi() {
    if (mobileSelectedCount) {
      mobileSelectedCount.textContent = String(selectedExercises.length);
    }

    if (!mobileSelectedToggle) {
      return;
    }

    mobileSelectedToggle.setAttribute(
      "aria-label",
      `Voir la seance (${selectedExercises.length} exercice(s))`,
    );
    mobileSelectedToggle.setAttribute(
      "aria-expanded",
      mobileSelectedOpen ? "true" : "false",
    );
  }

  function setMobileSelectedOpen(shouldOpen) {
    const canUseDrawer = isMobileViewport() && !!mobileSelectedToggle;

    mobileSelectedOpen = canUseDrawer ? Boolean(shouldOpen) : false;
    document.body.classList.toggle("mobile-selected-open", mobileSelectedOpen);
    updateMobileSelectedUi();
  }

  function initMobileSelectedDrawer() {
    if (!mobileSelectedToggle || !mobileSelectedClose) {
      return;
    }

    mobileSelectedToggle.addEventListener("click", function () {
      setMobileSelectedOpen(true);
    });

    mobileSelectedClose.addEventListener("click", function () {
      setMobileSelectedOpen(false);
    });

    if (mobileSelectedCloseBottom) {
      mobileSelectedCloseBottom.addEventListener("click", function () {
        setMobileSelectedOpen(false);
      });
    }

    window.addEventListener("resize", function () {
      if (!isMobileViewport()) {
        setMobileSelectedOpen(false);
      } else {
        updateMobileSelectedUi();
      }
    });

    setMobileSelectedOpen(false);
  }

  async function persistSelectedExercisesOrder() {
    const list = document.getElementById("selected-exercises");
    const orderedIds = Array.from(
      list.querySelectorAll(".selected-exercise-card[data-seance-exercice-id]"),
    ).map((item) => Number(item.dataset.seanceExerciceId));

    if (orderedIds.length <= 1) {
      return;
    }

    const body = new URLSearchParams();
    body.set("action", "reordonner_exercices");
    body.set("date", currentDate);
    orderedIds.forEach((id) => body.append("ordered_ids[]", id));
    withTeamBody(body);

    const response = await fetch("seances.php", {
      method: "POST",
      headers: { "Content-Type": "application/x-www-form-urlencoded" },
      body: body.toString(),
    });
    const result = await response.json();

    if (!result.success) {
      alert(result.message || "Erreur lors du reordonnancement.");
      await loadSelectedExercises();
      return;
    }

    selectedExercises = orderedIds
      .map((orderedId, index) => {
        const exercise = selectedExercises.find(
          (item) => Number(item.seance_exercice_id) === orderedId,
        );
        if (!exercise) return null;
        return { ...exercise, ordre: index + 1 };
      })
      .filter(Boolean);

    renderSelectedExercises();
    updateSummary();
  }

  function moveSelectedExerciseBefore(draggedId, targetId) {
    if (!draggedId || !targetId || draggedId === targetId) {
      return false;
    }

    const fromIndex = selectedExercises.findIndex(
      (exercise) => Number(exercise.seance_exercice_id) === draggedId,
    );
    const targetIndex = selectedExercises.findIndex(
      (exercise) => Number(exercise.seance_exercice_id) === targetId,
    );

    if (fromIndex === -1 || targetIndex === -1) {
      return false;
    }

    const reordered = [...selectedExercises];
    const [movedExercise] = reordered.splice(fromIndex, 1);
    const insertIndex = fromIndex < targetIndex ? targetIndex - 1 : targetIndex;
    reordered.splice(insertIndex, 0, movedExercise);

    selectedExercises = reordered.map((exercise, index) => ({
      ...exercise,
      ordre: index + 1,
    }));
    renderSelectedExercises();
    updateSummary();
    return true;
  }

  function renderSessionPlayers() {
    const container = document.getElementById("session-players");
    if (!container) return;

    if (allPlayers.length === 0) {
      container.innerHTML =
        '<div class="empty-state team-empty">Ajoutez d\'abord des joueurs depuis la page équipe.</div>';
      updateSessionPlayersToggle();
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
    updateSessionPlayersToggle();
  }

  function updateSessionPlayersToggle() {
    const button = document.getElementById("toggle-all-session-players");
    const container = document.getElementById("session-players");
    if (!button || !container) return;

    const checkboxes = Array.from(
      container.querySelectorAll('input[type="checkbox"]'),
    );
    const allSelected =
      checkboxes.length > 0 && checkboxes.every((checkbox) => checkbox.checked);

    button.disabled = checkboxes.length === 0;
    button.textContent = allSelected
      ? "Tout désélectionner"
      : "Tout sélectionner";
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
    withTeamBody(body);

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
      )}&equipe_id=${encodeURIComponent(String(currentTeamId))}`,
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
      if (isMobileViewport()) {
        setMobileSelectedOpen(true);
      }
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
      )}&equipe_id=${encodeURIComponent(String(currentTeamId))}`,
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
    updateResultsSummary(getFilteredExercises());
  }

  function renderAiProposalPreview() {
    const preview = document.getElementById("ai-session-preview");
    const feedback = document.getElementById("ai-session-feedback");
    const applyBtn = document.getElementById("apply-ai-session");

    if (!preview || !feedback || !applyBtn) {
      return;
    }

    if (!aiProposal || !Array.isArray(aiProposal.exercises)) {
      preview.innerHTML = "";
      feedback.textContent = "";
      applyBtn.disabled = true;
      return;
    }

    preview.innerHTML = aiProposal.exercises
      .map((exercise, index) => {
        const duration = Number(exercise.duree) || 0;
        const category = exercise.categorie || "Sans categorie";
        return `<li>${index + 1}. ${exercise.nom} <small>(${category}, ${duration} min)</small></li>`;
      })
      .join("");

    const notes = aiProposal.notes ? ` ${aiProposal.notes}` : "";
    feedback.textContent = `Proposition automatique: ${aiProposal.exercises.length} exercice(s), ${aiProposal.total_duration} min.${notes}`;
    applyBtn.disabled = aiProposal.exercises.length === 0;
  }

  async function generateAiSessionProposal() {
    const feedback = document.getElementById("ai-session-feedback");
    const generateBtn = document.getElementById("generate-ai-session");

    if (!feedback || !generateBtn) {
      return;
    }

    feedback.textContent = "Generation automatique en cours...";
    generateBtn.disabled = true;

    const countEchauffement =
      document.getElementById("auto-count-echauffement")?.value || "3";
    const countVitesse =
      document.getElementById("auto-count-vitesse")?.value || "2";
    const countEndurance =
      document.getElementById("auto-count-endurance")?.value || "2";
    const countAgilite =
      document.getElementById("auto-count-agilite")?.value || "2";
    const formatSouhaite =
      document.getElementById("auto-format-souhaite")?.value || "individuel";

    const body = new URLSearchParams();
    body.set("action", "proposer_seance_ia");
    body.set("date", currentDate);
    body.set("count_echauffement", countEchauffement);
    body.set("count_vitesse", countVitesse);
    body.set("count_endurance", countEndurance);
    body.set("count_agilite", countAgilite);
    body.set("format_souhaite", formatSouhaite);
    withTeamBody(body);

    try {
      const response = await fetch("seances.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
      });
      const result = await response.json();

      if (!result.success) {
        aiProposal = null;
        renderAiProposalPreview();
        feedback.textContent =
          result.message ||
          "Impossible de generer une proposition automatique.";
        return;
      }

      aiProposal = {
        exercise_ids: result.exercise_ids || [],
        exercises: result.exercises || [],
        total_duration: Number(result.total_duration) || 0,
        notes: result.notes || "",
      };
      renderAiProposalPreview();
    } catch (error) {
      aiProposal = null;
      renderAiProposalPreview();
      feedback.textContent = "Erreur reseau lors de la generation automatique.";
    } finally {
      generateBtn.disabled = false;
    }
  }

  async function applyAiSessionProposal() {
    const feedback = document.getElementById("ai-session-feedback");
    const applyBtn = document.getElementById("apply-ai-session");
    if (!feedback || !aiProposal || !Array.isArray(aiProposal.exercise_ids)) {
      return;
    }

    if (aiProposal.exercise_ids.length === 0) {
      feedback.textContent = "Aucune proposition a inserer.";
      return;
    }

    const body = new URLSearchParams();
    body.set("action", "inserer_proposition_ia");
    body.set("date", currentDate);
    aiProposal.exercise_ids.forEach((id) => body.append("exercise_ids[]", id));
    withTeamBody(body);
    feedback.textContent = "Insertion de la proposition en cours...";
    if (applyBtn) {
      applyBtn.disabled = true;
    }

    try {
      const response = await fetch("seances.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: body.toString(),
      });

      const raw = await response.text();
      let result;
      try {
        result = JSON.parse(raw);
      } catch (error) {
        const preview = String(raw || "")
          .replace(/\s+/g, " ")
          .trim()
          .slice(0, 160);
        feedback.textContent = `Reponse serveur invalide lors de l'insertion.${preview ? ` Détail: ${preview}` : ""}`;
        console.error("Insertion proposition: reponse non JSON", raw);
        return;
      }

      if (!result.success) {
        feedback.textContent =
          result.message || "Erreur lors de l'insertion de la proposition.";
        return;
      }

      if ((result.inserted_count || 0) === 0) {
        feedback.textContent =
          result.message ||
          "Aucun exercice insere: cette proposition est deja presente pour l'equipe selectionnee.";
        return;
      }

      feedback.textContent = `${result.inserted_count || 0} exercice(s) ajoute(s) depuis la proposition automatique.`;
      aiProposal = null;
      renderAiProposalPreview();
      await loadSelectedExercises();
      renderExercises();
      updateSummary();
    } catch (error) {
      feedback.textContent =
        "Erreur reseau lors de l'insertion de la proposition.";
      console.error("Insertion proposition: erreur reseau", error);
    } finally {
      if (applyBtn) {
        applyBtn.disabled = false;
      }
    }
  }

  function formatTrainingType(value) {
    if (value === "individuel") {
      return "Individuel";
    }
    if (value === "groupe") {
      return "En groupe";
    }
    return "Mixte";
  }

  function resetFilters() {
    filters.search = "";
    filters.favoritesOnly = false;
    filters.durationMax = "";
    filters.trainingFormat = "individuel";
    filters.sort = "favoris";
    currentFilter = "Toutes";

    document.getElementById("exercise-search").value = "";
    document.getElementById("exercise-training-format-select").value =
      "individuel";
    document.getElementById("exercise-sort-select").value = "favoris";
    document.getElementById("exercise-duration-max").value = "";
    document.getElementById("exercise-favorites-only").checked = false;
    syncCategoryControls();
    renderExercises();
  }

  // Changement de date
  document
    .getElementById("session-date")
    .addEventListener("change", function () {
      currentDate = this.value;
      updatePlannerUrl();
      loadSelectedExercises();
      loadAssignedPlayers();
    });

  teamSelect?.addEventListener("change", function () {
    currentTeamId = Number(this.value || 0);
    aiProposal = null;
    renderAiProposalPreview();
    updatePlannerUrl();
    loadPlayers();
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

  document
    .getElementById("toggle-all-session-players")
    .addEventListener("click", async function () {
      const checkboxes = Array.from(
        document.querySelectorAll('#session-players input[type="checkbox"]'),
      );
      const shouldSelectAll = checkboxes.some((checkbox) => !checkbox.checked);

      checkboxes.forEach((checkbox) => {
        checkbox.checked = shouldSelectAll;
      });
      await saveAssignedPlayers();
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

  document
    .getElementById("selected-exercises")
    .addEventListener("dragstart", function (event) {
      const item = event.target.closest(".selected-exercise-card");
      if (!item) return;

      draggedExerciseId = Number(item.dataset.seanceExerciceId);
      item.classList.add("dragging");
      if (event.dataTransfer) {
        event.dataTransfer.effectAllowed = "move";
      }
    });

  document
    .getElementById("selected-exercises")
    .addEventListener("dragend", function (event) {
      const item = event.target.closest(".selected-exercise-card");
      if (item) {
        item.classList.remove("dragging");
      }
      document
        .querySelectorAll(".selected-exercise-card.drag-over")
        .forEach((card) => card.classList.remove("drag-over"));
      draggedExerciseId = null;
    });

  document
    .getElementById("selected-exercises")
    .addEventListener("dragover", function (event) {
      const item = event.target.closest(".selected-exercise-card");
      if (!item || draggedExerciseId === null) return;

      event.preventDefault();
      if (event.dataTransfer) {
        event.dataTransfer.dropEffect = "move";
      }
      document
        .querySelectorAll(".selected-exercise-card.drag-over")
        .forEach((card) => {
          if (card !== item) {
            card.classList.remove("drag-over");
          }
        });
      if (Number(item.dataset.seanceExerciceId) !== draggedExerciseId) {
        item.classList.add("drag-over");
      }
    });

  document
    .getElementById("selected-exercises")
    .addEventListener("dragleave", function (event) {
      const item = event.target.closest(".selected-exercise-card");
      if (item) {
        item.classList.remove("drag-over");
      }
    });

  document
    .getElementById("selected-exercises")
    .addEventListener("drop", async function (event) {
      const item = event.target.closest(".selected-exercise-card");
      if (!item || draggedExerciseId === null) return;

      event.preventDefault();
      const targetId = Number(item.dataset.seanceExerciceId);
      document
        .querySelectorAll(".selected-exercise-card.drag-over")
        .forEach((card) => card.classList.remove("drag-over"));

      const moved = moveSelectedExerciseBefore(draggedExerciseId, targetId);
      draggedExerciseId = null;
      if (moved) {
        await persistSelectedExercisesOrder();
      }
    });

  // ✅ Délégation pour le flip sur les cartes disponibles
  document
    .getElementById("exercises-grid")
    .addEventListener("click", function (e) {
      const card = e.target.closest(".exercise-card");
      if (!card) return;
      if (e.target.closest("button")) return;
      card.classList.toggle("flipped");
    });

  document
    .getElementById("quick-category-filters")
    .addEventListener("click", function (event) {
      const button = event.target.closest(".filter-btn");
      if (!button) return;

      currentFilter = button.dataset.category;
      this.querySelectorAll(".filter-btn").forEach((item) => {
        item.classList.toggle("active", item === button);
      });
      renderExercises();
    });

  document
    .getElementById("exercise-search")
    .addEventListener("input", function () {
      filters.search = this.value.trim();
      renderExercises();
    });

  document
    .getElementById("exercise-training-format-select")
    .addEventListener("change", function () {
      filters.trainingFormat = this.value;
      renderExercises();
    });

  document
    .getElementById("exercise-sort-select")
    .addEventListener("change", function () {
      filters.sort = this.value;
      renderExercises();
    });

  document
    .getElementById("exercise-duration-max")
    .addEventListener("input", function () {
      filters.durationMax = this.value.trim();
      renderExercises();
    });

  document
    .getElementById("exercise-favorites-only")
    .addEventListener("change", function () {
      filters.favoritesOnly = this.checked;
      renderExercises();
    });

  document
    .getElementById("reset-exercise-filters")
    .addEventListener("click", resetFilters);

  document
    .getElementById("generate-ai-session")
    ?.addEventListener("click", generateAiSessionProposal);

  document
    .getElementById("apply-ai-session")
    ?.addEventListener("click", applyAiSessionProposal);

  // Pour accès global depuis HTML inline
  window.addExercise = addExercise;
  window.removeExercise = removeExercise;
  window.toggleExerciseFavorite = toggleExerciseFavorite;
  initMobileSelectedDrawer();

  // Initialisation
  updatePlannerUrl();
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
