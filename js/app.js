(() => {
  const categories = [
    "Toutes",
    "Échauffement",
    "Endurance",
    "Vitesse",
    "Agilité",
    "Précision",
  ];
  const jours = [
    "Lundi",
    "Mardi",
    "Mercredi",
    "Jeudi",
    "Vendredi",
    "Samedi",
    "Dimanche",
  ];

  let exercices = [];
  let selection = [];
  let flipped = {};
  let categorieFiltre = "Toutes";
  let jour = "Lundi";

  const app = document.getElementById("app");

  function fetchExercices() {
    fetch("exercices.php")
      .then((res) => res.json())
      .then((data) => {
        exercices = data;
        render();
      });
  }

  function fetchSeance() {
    fetch(`seances.php?jour=${jour}`)
      .then((res) => res.json())
      .then((data) => {
        selection = data;
        flipped = {};
        render();
      });
  }

  function saveSeance() {
    const ids = selection.map((e) => e.id);
    fetch("seances.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({ jour, exercices: ids }),
    });
  }

  function toggleFlip(id) {
    flipped[id] = !flipped[id];
    render();
  }

  function addExercice(id) {
    const ex = exercices.find((e) => e.id === id);
    if (ex && !selection.find((s) => s.id === id)) {
      selection.push(ex);
      saveSeance();
      render();
    }
  }

  function removeExercice(id) {
    selection = selection.filter((e) => e.id !== id);
    saveSeance();
    render();
  }

  function setCategorie(cat) {
    categorieFiltre = cat;
    render();
  }

  function setJour(j) {
    jour = j;
    fetchSeance();
  }

  function render() {
    app.innerHTML = `
      <div>
        <label>Jour: 
          <select id="select-jour">
            ${jours
              .map(
                (j) =>
                  `<option value="${j}" ${
                    j === jour ? "selected" : ""
                  }>${j}</option>`
              )
              .join("")}
          </select>
        </label>

        <div class="filters">
          ${categories
            .map(
              (cat) =>
                `<button data-cat="${cat}" class="${
                  categorieFiltre === cat ? "active" : ""
                }">${cat}</button>`
            )
            .join("")}
        </div>

        <div>
          ${exercices
            .filter(
              (e) =>
                categorieFiltre === "Toutes" || e.categorie === categorieFiltre
            )
            .map(
              (e) => `
              <div class="card ${flipped[e.id] ? "flipped" : ""}" data-id="${
                e.id
              }">
                <div class="front">
                  <h3>${e.nom}</h3>
                  <p>${e.categorie}</p>
                </div>
                <div class="back">
                  <p><strong>Description:</strong> ${e.description}</p>
                  <p><strong>Durée:</strong> ${e.duree}</p>
                  <p><strong>Matériel:</strong> ${e.materiel}</p>
                </div>
              </div>
            `
            )
            .join("")}
        </div>

        <h2>Séance du ${jour}</h2>
        <ul class="selection-list">
          ${selection
            .map(
              (e) =>
                `<li>${e.nom} <button data-remove="${e.id}">&times;</button></li>`
            )
            .join("")}
        </ul>
      </div>
    `;

    document
      .getElementById("select-jour")
      .addEventListener("change", (e) => setJour(e.target.value));

    document.querySelectorAll(".filters button").forEach((btn) => {
      btn.onclick = () => setCategorie(btn.getAttribute("data-cat"));
    });

    document.querySelectorAll(".card").forEach((card) => {
      const id = parseInt(card.getAttribute("data-id"));
      card.onclick = () => toggleFlip(id);
      card.ondblclick = () => addExercice(id);
    });

    document.querySelectorAll(".selection-list button").forEach((btn) => {
      const id = parseInt(btn.getAttribute("data-remove"));
      btn.onclick = () => removeExercice(id);
    });
  }

  fetchExercices();
  fetchSeance();
})();
