<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$configs = require(__DIR__ . '/../config/config.php');
$db = $configs['mastercoach'];

try {
    $pdo = new PDO(
        "mysql:host={$db['db_host']};dbname={$db['db_name']};charset=utf8mb4",
        $db['db_user'],
        $db['db_pass'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Exception $e) {
    die('Erreur base de donnees : ' . $e->getMessage());
}

$userId = (int) $_SESSION['user_id'];

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS equipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        nom VARCHAR(120) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_equipes_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS saisons (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        equipe_id INT NOT NULL,
        nom VARCHAR(120) NOT NULL,
        date_debut DATE DEFAULT NULL,
        date_fin DATE DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_saisons_user_equipe (user_id, equipe_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS joueurs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        equipe_id INT DEFAULT NULL,
        nom VARCHAR(120) NOT NULL,
        poste VARCHAR(80) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_joueurs_user (user_id),
        INDEX idx_joueurs_equipe (equipe_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

try {
    $pdo->exec('ALTER TABLE joueurs ADD COLUMN equipe_id INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueurs ADD INDEX idx_joueurs_equipe (equipe_id)');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueurs MODIFY poste VARCHAR(255) DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueurs ADD COLUMN points_forts TEXT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueurs ADD COLUMN points_faibles TEXT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueurs ADD COLUMN commentaire_joueur TEXT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueur_seances ADD COLUMN saison_id INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueur_seances ADD INDEX idx_joueur_seances_saison (saison_id)');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueur_matchs ADD COLUMN saison_id INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueur_matchs ADD INDEX idx_joueur_matchs_saison (saison_id)');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueur_matchs ADD COLUMN match_id INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueur_matchs ADD INDEX idx_joueur_matchs_match (match_id)');
} catch (Exception $e) {
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS joueur_seances (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        joueur_id INT NOT NULL,
        saison_id INT DEFAULT NULL,
        date_seance DATE NOT NULL,
        intitule VARCHAR(150) NOT NULL,
        commentaire TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_joueur_seances_user (user_id),
        INDEX idx_joueur_seances_joueur (joueur_id),
        INDEX idx_joueur_seances_saison (saison_id),
        CONSTRAINT fk_joueur_seances_joueur
            FOREIGN KEY (joueur_id) REFERENCES joueurs(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS joueur_matchs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        joueur_id INT NOT NULL,
        saison_id INT DEFAULT NULL,
        match_id INT DEFAULT NULL,
        date_match DATE NOT NULL,
        adversaire VARCHAR(120) DEFAULT NULL,
        buts INT NOT NULL DEFAULT 0,
        passes_decisives INT NOT NULL DEFAULT 0,
        matchs_joues INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_joueur_matchs_user (user_id),
        INDEX idx_joueur_matchs_joueur (joueur_id),
        INDEX idx_joueur_matchs_saison (saison_id),
        INDEX idx_joueur_matchs_match (match_id),
        CONSTRAINT fk_joueur_matchs_joueur
            FOREIGN KEY (joueur_id) REFERENCES joueurs(id)
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS equipe_matchs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        equipe_id INT NOT NULL,
        saison_id INT DEFAULT NULL,
        date_match DATE NOT NULL,
        adversaire VARCHAR(120) DEFAULT NULL,
        score_equipe INT DEFAULT NULL,
        score_adverse INT DEFAULT NULL,
        statut VARCHAR(20) NOT NULL DEFAULT "planifie",
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_equipe_matchs_user_equipe (user_id, equipe_id),
        INDEX idx_equipe_matchs_saison (saison_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

try {
    $pdo->exec('ALTER TABLE equipe_matchs ADD COLUMN score_equipe INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE equipe_matchs ADD COLUMN score_adverse INT DEFAULT NULL');
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

$positionOptions = [
    'Gardien',
    'Defenseur central',
    'Arriere droit',
    'Arriere gauche',
    'Piston droit',
    'Piston gauche',
    'Milieu defensif',
    'Milieu relayeur',
    'Milieu offensif',
    'Ailier droit',
    'Ailier gauche',
    'Second attaquant',
    'Avant-centre',
];

function redirect_with_status(string $status): void
{
    header('Location: equipe.php?status=' . urlencode($status));
    exit;
}

function redirect_with_status_and_team(string $status, ?int $teamId = null): void
{
    $url = 'equipe.php?status=' . urlencode($status);
    if ($teamId !== null && $teamId > 0) {
        $url .= '&equipe_id=' . $teamId;
    }

    header('Location: ' . $url);
    exit;
}

function redirect_with_context(string $status, ?int $teamId = null, ?int $seasonId = null): void
{
    $url = 'equipe.php?status=' . urlencode($status);
    if ($teamId !== null && $teamId > 0) {
        $url .= '&equipe_id=' . $teamId;
    }
    if ($seasonId !== null && $seasonId > 0) {
        $url .= '&saison_id=' . $seasonId;
    }

    header('Location: ' . $url);
    exit;
}

function normalize_positions(array $positions, array $positionOptions): ?string
{
    $cleanPositions = [];

    foreach ($positions as $position) {
        $position = trim((string) $position);
        if ($position === '' || !in_array($position, $positionOptions, true)) {
            continue;
        }
        $cleanPositions[] = $position;
    }

    $cleanPositions = array_values(array_unique($cleanPositions));

    return count($cleanPositions) > 0 ? implode(', ', $cleanPositions) : null;
}

function extract_positions_from_post(array $source, array $positionOptions): ?string
{
    if (isset($source['postes']) && is_array($source['postes'])) {
        return normalize_positions($source['postes'], $positionOptions);
    }

    if (isset($source['poste'])) {
        $legacyPositions = preg_split('/\s*,\s*/', trim((string) $source['poste']));
        if (is_array($legacyPositions)) {
            return normalize_positions($legacyPositions, $positionOptions);
        }
    }

    return null;
}

function parse_positions(?string $positions): array
{
    if ($positions === null || trim($positions) === '') {
        return [];
    }

    $values = preg_split('/\s*,\s*/', $positions);

    return is_array($values) ? array_values(array_filter($values)) : [];
}

function render_position_badges(?string $positions): string
{
    $postes = parse_positions($positions);

    if (count($postes) === 0) {
        return '<span class="badge-empty">Non renseigné</span>';
    }

    $badges = array_map(static fn($poste) => '<span class="position-badge">' . htmlspecialchars($poste) . '</span>', $postes);

    return implode(' ', $badges);
}

$teamsStmt = $pdo->prepare('SELECT id, nom FROM equipes WHERE user_id = ? ORDER BY nom ASC');
$teamsStmt->execute([$userId]);
$equipes = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($equipes) === 0) {
    $createDefaultTeamStmt = $pdo->prepare('INSERT INTO equipes (user_id, nom) VALUES (?, ?)');
    $createDefaultTeamStmt->execute([$userId, 'Equipe principale']);

    $teamsStmt->execute([$userId]);
    $equipes = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$selectedTeamId = isset($_GET['equipe_id']) ? (int) $_GET['equipe_id'] : (int) ($equipes[0]['id'] ?? 0);
$knownTeamIds = array_map(static fn(array $team) => (int) $team['id'], $equipes);

if ($selectedTeamId <= 0 || !in_array($selectedTeamId, $knownTeamIds, true)) {
    $selectedTeamId = (int) ($equipes[0]['id'] ?? 0);
}

if ($selectedTeamId > 0) {
    $migratePlayersStmt = $pdo->prepare('UPDATE joueurs SET equipe_id = ? WHERE user_id = ? AND equipe_id IS NULL');
    $migratePlayersStmt->execute([$selectedTeamId, $userId]);
}

$saisonsStmt = $pdo->prepare('SELECT id, nom, date_debut, date_fin FROM saisons WHERE user_id = ? AND equipe_id = ? ORDER BY created_at DESC, id DESC');
$saisonsStmt->execute([$userId, $selectedTeamId]);
$saisons = $saisonsStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($saisons) === 0 && $selectedTeamId > 0) {
    $year = (int) date('Y');
    $defaultSeasonName = 'Saison ' . $year . '-' . ($year + 1);

    $createDefaultSeasonStmt = $pdo->prepare(
        'INSERT INTO saisons (user_id, equipe_id, nom, date_debut, date_fin) VALUES (?, ?, ?, ?, ?)'
    );
    $createDefaultSeasonStmt->execute([
        $userId,
        $selectedTeamId,
        $defaultSeasonName,
        date('Y-07-01'),
        date('Y-m-d', strtotime('+1 year', strtotime(date('Y-06-30')))),
    ]);

    $saisonsStmt->execute([$userId, $selectedTeamId]);
    $saisons = $saisonsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$knownSeasonIds = array_map(static fn(array $saison) => (int) $saison['id'], $saisons);
$selectedSeasonId = isset($_GET['saison_id']) ? (int) $_GET['saison_id'] : 0;

if ($selectedSeasonId > 0 && !in_array($selectedSeasonId, $knownSeasonIds, true)) {
    $selectedSeasonId = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $postedTeamId = isset($_POST['equipe_id']) ? (int) $_POST['equipe_id'] : $selectedTeamId;
    $postedSeasonId = isset($_POST['saison_id']) ? (int) $_POST['saison_id'] : $selectedSeasonId;

    if (!in_array($postedTeamId, $knownTeamIds, true)) {
        $postedTeamId = $selectedTeamId;
    }

    $postSeasonsStmt = $pdo->prepare('SELECT id FROM saisons WHERE user_id = ? AND equipe_id = ?');
    $postSeasonsStmt->execute([$userId, $postedTeamId]);
    $postSeasonIds = array_map(static fn(array $row) => (int) $row['id'], $postSeasonsStmt->fetchAll(PDO::FETCH_ASSOC));

    if ($postedSeasonId > 0 && !in_array($postedSeasonId, $postSeasonIds, true)) {
        $postedSeasonId = 0;
    }

    if ($action === 'ajouter_equipe') {
        $nomEquipe = trim($_POST['nom_equipe'] ?? '');

        if ($nomEquipe === '') {
            redirect_with_context('equipe_invalide', $selectedTeamId, $selectedSeasonId);
        }

        $stmt = $pdo->prepare('INSERT INTO equipes (user_id, nom) VALUES (?, ?)');
        $stmt->execute([$userId, $nomEquipe]);

        redirect_with_context('equipe_ajoutee', (int) $pdo->lastInsertId(), null);
    }

    if ($action === 'ajouter_saison') {
        $nomSaison = trim($_POST['nom_saison'] ?? '');
        $dateDebut = trim($_POST['date_debut'] ?? '');
        $dateFin = trim($_POST['date_fin'] ?? '');

        if ($nomSaison === '') {
            redirect_with_context('saison_invalide', $postedTeamId, $postedSeasonId);
        }

        $insertSaisonStmt = $pdo->prepare(
            'INSERT INTO saisons (user_id, equipe_id, nom, date_debut, date_fin) VALUES (?, ?, ?, ?, ?)'
        );
        $insertSaisonStmt->execute([
            $userId,
            $postedTeamId,
            $nomSaison,
            $dateDebut !== '' ? $dateDebut : null,
            $dateFin !== '' ? $dateFin : null,
        ]);

        redirect_with_context('saison_ajoutee', $postedTeamId, (int) $pdo->lastInsertId());
    }

    if ($action === 'modifier_equipe') {
        $nomEquipe = trim($_POST['nom_equipe'] ?? '');
        $equipeId = (int) ($_POST['equipe_id'] ?? 0);

        if ($nomEquipe === '' || !in_array($equipeId, $knownTeamIds, true)) {
            redirect_with_context('equipe_invalide', $selectedTeamId, $selectedSeasonId);
        }

        $stmt = $pdo->prepare('UPDATE equipes SET nom = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$nomEquipe, $equipeId, $userId]);

        redirect_with_context($stmt->rowCount() > 0 ? 'equipe_modifiee' : 'equipe_introuvable', $equipeId, $selectedSeasonId);
    }

    if ($action === 'supprimer_equipe') {
        $equipeId = (int) ($_POST['equipe_id'] ?? 0);

        if (!in_array($equipeId, $knownTeamIds, true) || count($equipes) <= 1) {
            redirect_with_context('equipe_non_supprimable', $selectedTeamId, $selectedSeasonId);
        }

        $deleteStatsStmt = $pdo->prepare(
            'DELETE jm FROM joueur_matchs jm
             JOIN joueurs j ON j.id = jm.joueur_id
             WHERE j.user_id = ? AND j.equipe_id = ?'
        );
        $deleteStatsStmt->execute([$userId, $equipeId]);

        $deleteSeancesMemoireStmt = $pdo->prepare(
            'DELETE js FROM joueur_seances js
             JOIN joueurs j ON j.id = js.joueur_id
             WHERE j.user_id = ? AND j.equipe_id = ?'
        );
        $deleteSeancesMemoireStmt->execute([$userId, $equipeId]);

        $deleteSeancesPlanifieesStmt = $pdo->prepare(
            'DELETE sj FROM seance_joueurs sj
             JOIN joueurs j ON j.id = sj.joueur_id
             WHERE j.user_id = ? AND j.equipe_id = ?'
        );
        $deleteSeancesPlanifieesStmt->execute([$userId, $equipeId]);

        $deleteMatchsEquipeStmt = $pdo->prepare('DELETE FROM equipe_matchs WHERE user_id = ? AND equipe_id = ?');
        $deleteMatchsEquipeStmt->execute([$userId, $equipeId]);

        $deleteSaisonsStmt = $pdo->prepare('DELETE FROM saisons WHERE user_id = ? AND equipe_id = ?');
        $deleteSaisonsStmt->execute([$userId, $equipeId]);

        $deleteJoueursStmt = $pdo->prepare('DELETE FROM joueurs WHERE user_id = ? AND equipe_id = ?');
        $deleteJoueursStmt->execute([$userId, $equipeId]);

        $deleteEquipeStmt = $pdo->prepare('DELETE FROM equipes WHERE id = ? AND user_id = ?');
        $deleteEquipeStmt->execute([$equipeId, $userId]);

        $refreshTeamsStmt = $pdo->prepare('SELECT id FROM equipes WHERE user_id = ? ORDER BY nom ASC LIMIT 1');
        $refreshTeamsStmt->execute([$userId]);
        $nextTeamId = (int) $refreshTeamsStmt->fetchColumn();

        redirect_with_context('equipe_supprimee', $nextTeamId > 0 ? $nextTeamId : null, null);
    }

    if ($action === 'modifier_saison') {
        $saisonId = (int) ($_POST['saison_id'] ?? 0);
        $nomSaison = trim($_POST['nom_saison'] ?? '');
        $dateDebut = trim($_POST['date_debut'] ?? '');
        $dateFin = trim($_POST['date_fin'] ?? '');

        if ($nomSaison === '' || ($saisonId > 0 && !in_array($saisonId, $postSeasonIds, true))) {
            redirect_with_context('saison_invalide', $postedTeamId, $postedSeasonId);
        }

        $stmt = $pdo->prepare(
            'UPDATE saisons
             SET nom = ?, date_debut = ?, date_fin = ?
             WHERE id = ? AND user_id = ? AND equipe_id = ?'
        );
        $stmt->execute([
            $nomSaison,
            $dateDebut !== '' ? $dateDebut : null,
            $dateFin !== '' ? $dateFin : null,
            $saisonId,
            $userId,
            $postedTeamId,
        ]);

        redirect_with_context($stmt->rowCount() > 0 ? 'saison_modifiee' : 'saison_introuvable', $postedTeamId, $saisonId);
    }

    if ($action === 'supprimer_saison') {
        $saisonId = (int) ($_POST['saison_id'] ?? 0);

        if ($saisonId <= 0 || !in_array($saisonId, $postSeasonIds, true) || count($postSeasonIds) <= 1) {
            redirect_with_context('saison_non_supprimable', $postedTeamId, $postedSeasonId);
        }

        $deleteMatchsEquipeSaisonStmt = $pdo->prepare('DELETE FROM equipe_matchs WHERE user_id = ? AND equipe_id = ? AND saison_id = ?');
        $deleteMatchsEquipeSaisonStmt->execute([$userId, $postedTeamId, $saisonId]);

        $deleteMatchsSaisonStmt = $pdo->prepare(
            'DELETE jm FROM joueur_matchs jm
             JOIN joueurs j ON j.id = jm.joueur_id
             WHERE jm.user_id = ? AND j.equipe_id = ? AND jm.saison_id = ?'
        );
        $deleteMatchsSaisonStmt->execute([$userId, $postedTeamId, $saisonId]);

        $deleteSeancesSaisonStmt = $pdo->prepare(
            'DELETE js FROM joueur_seances js
             JOIN joueurs j ON j.id = js.joueur_id
             WHERE js.user_id = ? AND j.equipe_id = ? AND js.saison_id = ?'
        );
        $deleteSeancesSaisonStmt->execute([$userId, $postedTeamId, $saisonId]);

        $deleteSaisonStmt = $pdo->prepare('DELETE FROM saisons WHERE id = ? AND user_id = ? AND equipe_id = ?');
        $deleteSaisonStmt->execute([$saisonId, $userId, $postedTeamId]);

        $fallbackSeasonStmt = $pdo->prepare('SELECT id FROM saisons WHERE user_id = ? AND equipe_id = ? ORDER BY created_at DESC, id DESC LIMIT 1');
        $fallbackSeasonStmt->execute([$userId, $postedTeamId]);
        $nextSeasonId = (int) $fallbackSeasonStmt->fetchColumn();

        redirect_with_context('saison_supprimee', $postedTeamId, $nextSeasonId > 0 ? $nextSeasonId : null);
    }

    if ($action === 'ajouter_joueur') {
        $nom = trim($_POST['nom'] ?? '');
        $poste = extract_positions_from_post($_POST, $positionOptions);
        $pointsForts = trim($_POST['points_forts'] ?? '');
        $pointsFaibles = trim($_POST['points_faibles'] ?? '');
        $commentaireJoueur = trim($_POST['commentaire_joueur'] ?? '');

        if ($nom === '') {
            redirect_with_context('joueur_invalide', $postedTeamId, $postedSeasonId);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO joueurs (user_id, equipe_id, nom, poste, points_forts, points_faibles, commentaire_joueur)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $postedTeamId,
            $nom,
            $poste,
            $pointsForts !== '' ? $pointsForts : null,
            $pointsFaibles !== '' ? $pointsFaibles : null,
            $commentaireJoueur !== '' ? $commentaireJoueur : null,
        ]);
        redirect_with_context('joueur_ajoute', $postedTeamId, $postedSeasonId);
    }

    if ($action === 'mettre_a_jour_joueur') {
        $joueurId = (int) ($_POST['joueur_id'] ?? 0);
        $poste = extract_positions_from_post($_POST, $positionOptions);
        $pointsForts = trim($_POST['points_forts'] ?? '');
        $pointsFaibles = trim($_POST['points_faibles'] ?? '');
        $commentaireJoueur = trim($_POST['commentaire_joueur'] ?? '');

        $stmt = $pdo->prepare(
            'UPDATE joueurs
             SET poste = ?, points_forts = ?, points_faibles = ?, commentaire_joueur = ?
             WHERE id = ? AND user_id = ? AND equipe_id = ?'
        );
        $stmt->execute([
            $poste,
            $pointsForts !== '' ? $pointsForts : null,
            $pointsFaibles !== '' ? $pointsFaibles : null,
            $commentaireJoueur !== '' ? $commentaireJoueur : null,
            $joueurId,
            $userId,
            $postedTeamId,
        ]);

        redirect_with_context($stmt->rowCount() > 0 ? 'joueur_mis_a_jour' : 'joueur_introuvable', $postedTeamId, $postedSeasonId);
    }

    if ($action === 'enregistrer_seance') {
        $joueurId = (int) ($_POST['joueur_id'] ?? 0);
        $dateSeance = $_POST['date_seance'] ?? '';
        $intitule = trim($_POST['intitule'] ?? '');
        $commentaire = trim($_POST['commentaire'] ?? '');

        if ($joueurId <= 0 || $dateSeance === '' || $intitule === '') {
            redirect_with_context('seance_invalide', $postedTeamId, $postedSeasonId);
        }

        $stmt = $pdo->prepare('SELECT id FROM joueurs WHERE id = ? AND user_id = ? AND equipe_id = ?');
        $stmt->execute([$joueurId, $userId, $postedTeamId]);
        if (!$stmt->fetchColumn()) {
            redirect_with_context('joueur_introuvable', $postedTeamId, $postedSeasonId);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO joueur_seances (user_id, joueur_id, saison_id, date_seance, intitule, commentaire)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$userId, $joueurId, $postedSeasonId > 0 ? $postedSeasonId : null, $dateSeance, $intitule, $commentaire !== '' ? $commentaire : null]);
        redirect_with_context('seance_ajoutee', $postedTeamId, $postedSeasonId);
    }

    if ($action === 'enregistrer_match') {
        $joueurId = (int) ($_POST['joueur_id'] ?? 0);
        $dateMatch = $_POST['date_match'] ?? '';
        $adversaire = trim($_POST['adversaire'] ?? '');
        $buts = max(0, (int) ($_POST['buts'] ?? 0));
        $passes = max(0, (int) ($_POST['passes_decisives'] ?? 0));
        $matchsJoues = max(1, (int) ($_POST['matchs_joues'] ?? 1));

        if ($joueurId <= 0 || $dateMatch === '') {
            redirect_with_context('match_invalide', $postedTeamId, $postedSeasonId);
        }

        $stmt = $pdo->prepare('SELECT id FROM joueurs WHERE id = ? AND user_id = ? AND equipe_id = ?');
        $stmt->execute([$joueurId, $userId, $postedTeamId]);
        if (!$stmt->fetchColumn()) {
            redirect_with_context('joueur_introuvable', $postedTeamId, $postedSeasonId);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO joueur_matchs (user_id, joueur_id, saison_id, date_match, adversaire, buts, passes_decisives, matchs_joues)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $joueurId,
            $postedSeasonId > 0 ? $postedSeasonId : null,
            $dateMatch,
            $adversaire !== '' ? $adversaire : null,
            $buts,
            $passes,
            $matchsJoues,
        ]);
        redirect_with_context('match_ajoute', $postedTeamId, $postedSeasonId);
    }

    if ($action === 'supprimer_joueur') {
        $joueurId = (int) ($_POST['joueur_id'] ?? 0);

        $stmt = $pdo->prepare('DELETE FROM joueurs WHERE id = ? AND user_id = ? AND equipe_id = ?');
        $stmt->execute([$joueurId, $userId, $postedTeamId]);

        redirect_with_context($stmt->rowCount() > 0 ? 'joueur_supprime' : 'joueur_introuvable', $postedTeamId, $postedSeasonId);
    }

    if ($action === 'supprimer_seance_memoire') {
        $seanceId = (int) ($_POST['seance_id'] ?? 0);

        $stmt = $pdo->prepare(
            'DELETE js FROM joueur_seances js
             JOIN joueurs j ON j.id = js.joueur_id
             WHERE js.id = ? AND js.user_id = ? AND j.equipe_id = ?'
        );
        $stmt->execute([$seanceId, $userId, $postedTeamId]);

        redirect_with_context($stmt->rowCount() > 0 ? 'seance_supprimee' : 'seance_introuvable', $postedTeamId, $postedSeasonId);
    }

    if ($action === 'supprimer_seance_planifiee') {
        $assignationId = (int) ($_POST['assignation_id'] ?? 0);

        $stmt = $pdo->prepare(
            'DELETE sj FROM seance_joueurs sj
             JOIN joueurs j ON j.id = sj.joueur_id
             JOIN seances s ON s.id = sj.seance_id
             WHERE sj.id = ? AND s.user_id = ? AND j.equipe_id = ?'
        );
        $stmt->execute([$assignationId, $userId, $postedTeamId]);

        redirect_with_context($stmt->rowCount() > 0 ? 'seance_supprimee' : 'seance_introuvable', $postedTeamId, $postedSeasonId);
    }

    if ($action === 'supprimer_match') {
        $matchId = (int) ($_POST['match_id'] ?? 0);

        $stmt = $pdo->prepare(
            'DELETE jm FROM joueur_matchs jm
             JOIN joueurs j ON j.id = jm.joueur_id
             WHERE jm.id = ? AND jm.user_id = ? AND j.equipe_id = ?'
        );
        $stmt->execute([$matchId, $userId, $postedTeamId]);

        redirect_with_context($stmt->rowCount() > 0 ? 'match_supprime' : 'match_introuvable', $postedTeamId, $postedSeasonId);
    }

    if ($action === 'supprimer_match_equipe') {
        $matchId = (int) ($_POST['match_id'] ?? 0);

        $deleteStatsStmt = $pdo->prepare('DELETE FROM joueur_matchs WHERE user_id = ? AND match_id = ?');
        $deleteStatsStmt->execute([$userId, $matchId]);

        $deleteMatchStmt = $pdo->prepare(
            'DELETE FROM equipe_matchs
             WHERE id = ? AND user_id = ? AND equipe_id = ?' . ($postedSeasonId > 0 ? ' AND saison_id = ?' : '')
        );
        if ($postedSeasonId > 0) {
            $deleteMatchStmt->execute([$matchId, $userId, $postedTeamId, $postedSeasonId]);
        } else {
            $deleteMatchStmt->execute([$matchId, $userId, $postedTeamId]);
        }

        redirect_with_context($deleteMatchStmt->rowCount() > 0 ? 'match_supprime' : 'match_introuvable', $postedTeamId, $postedSeasonId);
    }
}

$statusMessages = [
    'equipe_ajoutee' => ['success', 'Nouvelle equipe creee avec succes.'],
    'equipe_modifiee' => ['success', 'Equipe modifiee avec succes.'],
    'equipe_supprimee' => ['success', 'Equipe supprimee avec succes.'],
    'equipe_invalide' => ['error', 'Le nom de l\'equipe est obligatoire.'],
    'equipe_introuvable' => ['error', 'Equipe introuvable.'],
    'equipe_non_supprimable' => ['error', 'Impossible de supprimer cette equipe (gardez au moins une equipe).'],
    'saison_ajoutee' => ['success', 'Nouvelle saison creee avec succes.'],
    'saison_modifiee' => ['success', 'Saison modifiee avec succes.'],
    'saison_supprimee' => ['success', 'Saison supprimee avec succes.'],
    'saison_invalide' => ['error', 'Le nom de la saison est obligatoire.'],
    'saison_introuvable' => ['error', 'Saison introuvable.'],
    'saison_non_supprimable' => ['error', 'Impossible de supprimer cette saison (gardez au moins une saison).'],
    'joueur_ajoute' => ['success', 'Joueur ajoute avec succes.'],
    'joueur_mis_a_jour' => ['success', 'Notes du joueur mises a jour.'],
    'joueur_supprime' => ['success', 'Joueur supprime avec succes.'],
    'joueur_invalide' => ['error', 'Le nom du joueur est obligatoire.'],
    'joueur_introuvable' => ['error', 'Le joueur selectionne est introuvable.'],
    'seance_ajoutee' => ['success', 'Seance enregistree pour le joueur.'],
    'seance_supprimee' => ['success', 'Seance supprimee avec succes.'],
    'seance_invalide' => ['error', 'La seance doit contenir un joueur, une date et un intitule.'],
    'seance_introuvable' => ['error', 'La seance selectionnee est introuvable.'],
    'match_ajoute' => ['success', 'Statistiques du match enregistrees.'],
    'match_invalide' => ['error', 'Le match doit contenir un joueur et une date valides.'],
    'match_supprime' => ['success', 'Statistiques du match supprimees.'],
    'match_introuvable' => ['error', 'Le match selectionne est introuvable.'],
];

$flash = null;
if (isset($_GET['status'], $statusMessages[$_GET['status']])) {
    $flash = $statusMessages[$_GET['status']];
}

$stmt = $pdo->prepare(
    'SELECT id, nom, poste, points_forts, points_faibles, commentaire_joueur
     FROM joueurs
    WHERE user_id = ? AND equipe_id = ?
     ORDER BY nom ASC'
);
$stmt->execute([$userId, $selectedTeamId]);
$joueurs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$seasonSqlFilterSeances = $selectedSeasonId > 0 ? ' AND saison_id = :season_id_seances' : '';
$seasonSqlFilterMatchs = $selectedSeasonId > 0 ? ' AND saison_id = :season_id_matchs' : '';
$seancesExpr = $selectedSeasonId > 0
    ? 'COALESCE(js_stats.seances_effectuees, 0)'
    : 'COALESCE(js_stats.seances_effectuees, 0) + COALESCE(sj_stats.seances_planifiees, 0)';

$statsStmt = $pdo->prepare(
    'SELECT
        j.id,
        j.nom,
        j.poste,
        ' . $seancesExpr . ' AS seances_effectuees,
        COALESCE(jm_stats.matchs_joues, 0) AS matchs_joues,
        COALESCE(jm_stats.buts, 0) AS buts,
        COALESCE(jm_stats.passes_decisives, 0) AS passes_decisives
     FROM joueurs j
     LEFT JOIN (
        SELECT joueur_id, user_id, COUNT(*) AS seances_effectuees
        FROM joueur_seances
        WHERE 1 = 1' . $seasonSqlFilterSeances . '
        GROUP BY joueur_id, user_id
     ) js_stats ON js_stats.joueur_id = j.id AND js_stats.user_id = j.user_id
      LEFT JOIN (
          SELECT sj.joueur_id, s.user_id, COUNT(*) AS seances_planifiees
          FROM seance_joueurs sj
          JOIN seances s ON s.id = sj.seance_id
          GROUP BY sj.joueur_id, s.user_id
      ) sj_stats ON sj_stats.joueur_id = j.id AND sj_stats.user_id = j.user_id
     LEFT JOIN (
        SELECT
            joueur_id,
            user_id,
            SUM(matchs_joues) AS matchs_joues,
            SUM(buts) AS buts,
            SUM(passes_decisives) AS passes_decisives
        FROM joueur_matchs
        WHERE 1 = 1' . $seasonSqlFilterMatchs . '
        GROUP BY joueur_id, user_id
     ) jm_stats ON jm_stats.joueur_id = j.id AND jm_stats.user_id = j.user_id
    WHERE j.user_id = :stats_user_id AND j.equipe_id = :stats_team_id
     ORDER BY j.nom ASC'
);
$statsStmt->bindValue(':stats_user_id', $userId, PDO::PARAM_INT);
$statsStmt->bindValue(':stats_team_id', $selectedTeamId, PDO::PARAM_INT);
if ($selectedSeasonId > 0) {
    $statsStmt->bindValue(':season_id_seances', $selectedSeasonId, PDO::PARAM_INT);
    $statsStmt->bindValue(':season_id_matchs', $selectedSeasonId, PDO::PARAM_INT);
}
$statsStmt->execute();
$statistiques = $statsStmt->fetchAll(PDO::FETCH_ASSOC);

$resume = [
    'joueurs' => count($statistiques),
    'seances' => 0,
    'matchs' => 0,
    'gagnes' => 0,
    'nuls' => 0,
    'perdus' => 0,
    'buts' => 0,
    'buts_par_match' => '0.00',
    'encaisses' => 0,
    'passes' => 0,
];

$teamMatchCountStmt = $pdo->prepare(
    'SELECT COUNT(*)
     FROM (
        SELECT
            COALESCE(
                CASE WHEN jm.match_id IS NOT NULL AND jm.match_id > 0 THEN CONCAT("M", jm.match_id) END,
                CONCAT("D", DATE_FORMAT(jm.date_match, "%Y-%m-%d"), "|", COALESCE(TRIM(jm.adversaire), ""))
            ) AS match_key
        FROM joueur_matchs jm
        JOIN joueurs j ON j.id = jm.joueur_id
        WHERE jm.user_id = :user_id
          AND j.equipe_id = :team_id' . ($selectedSeasonId > 0 ? ' AND jm.saison_id = :season_id' : '') . '
        GROUP BY match_key
     ) AS unique_matchs'
);
$teamMatchCountStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$teamMatchCountStmt->bindValue(':team_id', $selectedTeamId, PDO::PARAM_INT);
if ($selectedSeasonId > 0) {
    $teamMatchCountStmt->bindValue(':season_id', $selectedSeasonId, PDO::PARAM_INT);
}
$teamMatchCountStmt->execute();
$resume['matchs'] = (int) $teamMatchCountStmt->fetchColumn();

$goalsConcededStmt = $pdo->prepare(
    'SELECT COALESCE(SUM(score_adverse), 0)
     FROM equipe_matchs
     WHERE user_id = :user_id
       AND equipe_id = :team_id
       AND statut = "joue"' . ($selectedSeasonId > 0 ? ' AND saison_id = :season_id' : '')
);
$goalsConcededStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$goalsConcededStmt->bindValue(':team_id', $selectedTeamId, PDO::PARAM_INT);
if ($selectedSeasonId > 0) {
    $goalsConcededStmt->bindValue(':season_id', $selectedSeasonId, PDO::PARAM_INT);
}
$goalsConcededStmt->execute();
$resume['encaisses'] = (int) $goalsConcededStmt->fetchColumn();

$matchResultsStmt = $pdo->prepare(
    'SELECT
        COALESCE(SUM(CASE WHEN score_equipe > score_adverse THEN 1 ELSE 0 END), 0) AS gagnes,
                COALESCE(SUM(CASE WHEN score_equipe = score_adverse THEN 1 ELSE 0 END), 0) AS nuls,
        COALESCE(SUM(CASE WHEN score_equipe < score_adverse THEN 1 ELSE 0 END), 0) AS perdus
     FROM equipe_matchs
     WHERE user_id = :user_id
       AND equipe_id = :team_id
       AND statut = "joue"
       AND score_equipe IS NOT NULL
       AND score_adverse IS NOT NULL' . ($selectedSeasonId > 0 ? ' AND saison_id = :season_id' : '')
);
$matchResultsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$matchResultsStmt->bindValue(':team_id', $selectedTeamId, PDO::PARAM_INT);
if ($selectedSeasonId > 0) {
    $matchResultsStmt->bindValue(':season_id', $selectedSeasonId, PDO::PARAM_INT);
}
$matchResultsStmt->execute();
$matchResults = $matchResultsStmt->fetch(PDO::FETCH_ASSOC) ?: ['gagnes' => 0, 'nuls' => 0, 'perdus' => 0];
$resume['gagnes'] = (int) ($matchResults['gagnes'] ?? 0);
$resume['nuls'] = (int) ($matchResults['nuls'] ?? 0);
$resume['perdus'] = (int) ($matchResults['perdus'] ?? 0);

$topScorer = ['nom' => 'Aucun', 'valeur' => 0];
$topPasser = ['nom' => 'Aucun', 'valeur' => 0];
$topScorerNames = [];
$topPasserNames = [];

foreach ($statistiques as $statistique) {
    $resume['seances'] += (int) $statistique['seances_effectuees'];
    $resume['buts'] += (int) $statistique['buts'];
    $resume['passes'] += (int) $statistique['passes_decisives'];

    $currentGoals = (int) $statistique['buts'];
    $currentAssists = (int) $statistique['passes_decisives'];
    $playerName = trim((string) ($statistique['nom'] ?? ''));

    if ($currentGoals > $topScorer['valeur']) {
        $topScorer['valeur'] = $currentGoals;
        $topScorerNames = $playerName !== '' ? [$playerName] : [];
    } elseif ($currentGoals > 0 && $currentGoals === $topScorer['valeur'] && $playerName !== '') {
        $topScorerNames[] = $playerName;
    }

    if ($currentAssists > $topPasser['valeur']) {
        $topPasser['valeur'] = $currentAssists;
        $topPasserNames = $playerName !== '' ? [$playerName] : [];
    } elseif ($currentAssists > 0 && $currentAssists === $topPasser['valeur'] && $playerName !== '') {
        $topPasserNames[] = $playerName;
    }
}

if ($resume['matchs'] > 0) {
    $resume['buts_par_match'] = number_format($resume['buts'] / $resume['matchs'], 2, '.', '');
}

if ($topScorer['valeur'] > 0 && count($topScorerNames) > 0) {
    $topScorer['nom'] = implode(', ', array_values(array_unique($topScorerNames)));
}

if ($topPasser['valeur'] > 0 && count($topPasserNames) > 0) {
    $topPasser['nom'] = implode(', ', array_values(array_unique($topPasserNames)));
}

$seancesSql =
    'SELECT historique.id, historique.type_source, historique.date_seance, historique.intitule, historique.commentaire, historique.nom
     FROM (
        SELECT js.id, "memoire" AS type_source, js.date_seance, js.intitule, js.commentaire, j.nom, js.created_at
        FROM joueur_seances js
        JOIN joueurs j ON j.id = js.joueur_id
        WHERE js.user_id = :user_id AND j.equipe_id = :team_id';

if ($selectedSeasonId > 0) {
    $seancesSql .= ' AND js.saison_id = :season_id';
}

if ($selectedSeasonId <= 0) {
    $seancesSql .= '

        UNION ALL

        SELECT
            sj.id,
            "planifiee" AS type_source,
            s.date_seance,
            "Seance planifiee",
            CONCAT(COALESCE(ex_stats.nb_exercices, 0), " exercice(s) planifie(s)"),
            j.nom,
            sj.created_at
        FROM seance_joueurs sj
        JOIN joueurs j ON j.id = sj.joueur_id
        JOIN seances s ON s.id = sj.seance_id
        LEFT JOIN (
            SELECT seance_id, COUNT(*) AS nb_exercices
            FROM seance_exercices
            GROUP BY seance_id
        ) ex_stats ON ex_stats.seance_id = s.id
        WHERE s.user_id = :user_id_planifie AND j.equipe_id = :team_id_planifie';
}

$seancesSql .= '
     ) historique
     ORDER BY historique.date_seance DESC, historique.created_at DESC
     LIMIT 12';

$seancesStmt = $pdo->prepare($seancesSql);
$seancesStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$seancesStmt->bindValue(':team_id', $selectedTeamId, PDO::PARAM_INT);
if ($selectedSeasonId > 0) {
    $seancesStmt->bindValue(':season_id', $selectedSeasonId, PDO::PARAM_INT);
} else {
    $seancesStmt->bindValue(':user_id_planifie', $userId, PDO::PARAM_INT);
    $seancesStmt->bindValue(':team_id_planifie', $selectedTeamId, PDO::PARAM_INT);
}
$seancesStmt->execute();
$dernieresSeances = $seancesStmt->fetchAll(PDO::FETCH_ASSOC);

$matchsStmt = $pdo->prepare(
    'SELECT
        em.id,
        em.date_match,
        em.adversaire,
        em.score_equipe,
        em.score_adverse,
        em.statut,
        COALESCE(SUM(jm.buts), 0) AS buts,
        COALESCE(SUM(jm.passes_decisives), 0) AS passes_decisives,
        COALESCE(COUNT(DISTINCT jm.joueur_id), 0) AS joueurs_ayant_stats
     FROM equipe_matchs em
     LEFT JOIN joueur_matchs jm ON jm.match_id = em.id AND jm.user_id = em.user_id
     WHERE em.user_id = :user_id
       AND em.equipe_id = :team_id' . ($selectedSeasonId > 0 ? ' AND em.saison_id = :season_id' : '') . '
    GROUP BY em.id, em.date_match, em.adversaire, em.score_equipe, em.score_adverse, em.statut
     ORDER BY em.date_match DESC, em.id DESC
     LIMIT 12'
);
$matchsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$matchsStmt->bindValue(':team_id', $selectedTeamId, PDO::PARAM_INT);
if ($selectedSeasonId > 0) {
    $matchsStmt->bindValue(':season_id', $selectedSeasonId, PDO::PARAM_INT);
}
$matchsStmt->execute();
$derniersMatchs = $matchsStmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="f7070317-bfa5-464f-bf91-24cf10f1ad59" type="text/javascript" async></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Suivi d'equipe - MasterCoach</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="header">
        <h1>Equipe et Statistiques</h1>
        <a href="home.php" class="home-btn">Accueil</a>
    </div>

    <?php if ($flash !== null): ?>
        <div class="team-flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <section class="team-panel team-panel-wide team-top-controls">
        <h2 class="section-title">Equipe et saison actives</h2>
        <div class="team-top-controls-grid">
            <form method="GET" class="add-form team-inline-form">
                <div>
                    <label for="equipe-id">Selectionner une equipe</label>
                    <select id="equipe-id" name="equipe_id" onchange="this.form.submit()">
                        <?php foreach ($equipes as $equipe): ?>
                            <option value="<?= (int) $equipe['id'] ?>" <?= (int) $equipe['id'] === $selectedTeamId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($equipe['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="saison_id" value="<?= $selectedSeasonId ?>">
            </form>

            <form method="GET" class="add-form team-inline-form">
                <div>
                    <label for="saison-id">Selectionner une saison</label>
                    <select id="saison-id" name="saison_id" onchange="this.form.submit()">
                        <option value="0" <?= $selectedSeasonId === 0 ? 'selected' : '' ?>>Toutes les saisons</option>
                        <?php foreach ($saisons as $saison): ?>
                            <option value="<?= (int) $saison['id'] ?>" <?= (int) $saison['id'] === $selectedSeasonId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($saison['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="equipe_id" value="<?= $selectedTeamId ?>">
            </form>

            <div class="add-form team-inline-form">
                <label>Gestion des equipes</label>
                <p>Creation, modification et suppression des equipes depuis une page dediee.</p>
                <a href="equipes.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>" class="btn btn-edit">Ouvrir la gestion equipes</a>
            </div>

            <div class="add-form team-inline-form">
                <label>Gestion des saisons</label>
                <p>Creation, modification et suppression des saisons depuis une page dediee.</p>
                <a href="saisons.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>" class="btn btn-edit">Ouvrir la gestion saisons</a>
            </div>
        </div>
    </section>

    <section class="team-summary-grid">
        <article class="team-summary-card">
            <span class="team-summary-label">Joueurs</span>
            <strong><?= $resume['joueurs'] ?></strong>
        </article>
        <article class="team-summary-card">
            <span class="team-summary-label">Seances</span>
            <strong><?= $resume['seances'] ?></strong>
        </article>
        <article class="team-summary-card">
            <span class="team-summary-label">Matchs joues</span>
            <strong><?= $resume['matchs'] ?></strong>
        </article>
        <article class="team-summary-card">
            <span class="team-summary-label">Matchs gagnes</span>
            <strong><?= $resume['gagnes'] ?></strong>
        </article>
        <article class="team-summary-card">
            <span class="team-summary-label">Matchs nuls</span>
            <strong><?= $resume['nuls'] ?></strong>
        </article>
        <article class="team-summary-card">
            <span class="team-summary-label">Matchs perdus</span>
            <strong><?= $resume['perdus'] ?></strong>
        </article>
        <article class="team-summary-card">
            <span class="team-summary-label">Buts</span>
            <strong><?= $resume['buts'] ?></strong>
        </article>
        <article class="team-summary-card">
            <span class="team-summary-label">Buts par match</span>
            <strong><?= htmlspecialchars($resume['buts_par_match']) ?></strong>
        </article>
        <article class="team-summary-card">
            <span class="team-summary-label">Buts encaisses</span>
            <strong><?= $resume['encaisses'] ?></strong>
        </article>
        <article class="team-summary-card">
            <span class="team-summary-label">Passes decisives</span>
            <strong><?= $resume['passes'] ?></strong>
        </article>
        <article class="team-summary-card team-summary-leader-card">
            <span class="team-summary-label">Meilleur buteur <?= $selectedSeasonId > 0 ? '(saison)' : '(equipe)' ?></span>
            <strong><?= htmlspecialchars($topScorer['nom']) ?></strong>
            <em><?= (int) $topScorer['valeur'] ?> but(s)</em>
        </article>
        <article class="team-summary-card team-summary-leader-card">
            <span class="team-summary-label">Meilleur passeur <?= $selectedSeasonId > 0 ? '(saison)' : '(equipe)' ?></span>
            <strong><?= htmlspecialchars($topPasser['nom']) ?></strong>
            <em><?= (int) $topPasser['valeur'] ?> passe(s)</em>
        </article>
    </section>

    <div class="team-layout">
        <section class="team-panel team-panel-wide">
            <h2 class="section-title">Actions rapides</h2>
            <div class="form-buttons">
                <a href="joueur_ajout.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>" class="btn btn-add">Ajouter un joueur</a>
            </div>
        </section>

        <section class="team-panel team-panel-wide">
            <h2 class="section-title">Effectif et statistiques <?= $selectedSeasonId > 0 ? 'de la saison active' : 'cumulees' ?></h2>
            <?php if (count($statistiques) === 0): ?>
                <div class="empty-state team-empty">Aucun joueur enregistre pour le moment.</div>
            <?php else: ?>
                <div class="position-filter-section">
                    <span class="position-filter-label">Filtrer par poste:</span>
                    <div class="position-filter-buttons">
                        <button class="position-filter-btn active" data-position="all">Tous</button>
                        <?php foreach ($positionOptions as $positionOption): ?>
                            <button class="position-filter-btn" data-position="<?= htmlspecialchars($positionOption) ?>"><?= htmlspecialchars($positionOption) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="team-table-wrapper">
                    <table class="team-table">
                        <thead>
                            <tr>
                                <th>Joueur</th>
                                <th>Postes</th>
                                <th>Seances</th>
                                <th>Matchs joues</th>
                                <th>Buts</th>
                                <th>Buts / match</th>
                                <th>Passes decisives</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($statistiques as $stat): ?>
                                <tr class="player-row" data-postes="<?= htmlspecialchars($stat['poste'] ?? '') ?>">
                                    <td data-label="Joueur"><?= htmlspecialchars($stat['nom']) ?></td>
                                    <td data-label="Postes"><?php echo render_position_badges($stat['poste']); ?></td>
                                    <td data-label="Seances"><?= (int) $stat['seances_effectuees'] ?></td>
                                    <td data-label="Matchs joues"><?= (int) $stat['matchs_joues'] ?></td>
                                    <td data-label="Buts"><?= (int) $stat['buts'] ?></td>
                                    <td data-label="Buts / match"><?= (int) $stat['matchs_joues'] > 0 ? number_format(((int) $stat['buts']) / ((int) $stat['matchs_joues']), 2, '.', '') : '0.00' ?></td>
                                    <td data-label="Passes decisives"><?= (int) $stat['passes_decisives'] ?></td>
                                    <td data-label="Actions">
                                        <div class="team-inline-actions">
                                            <a href="joueur.php?id=<?= (int) $stat['id'] ?>&equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>" class="btn btn-edit team-action-btn">Profil</a>
                                            <form method="POST" class="inline-action-form" onsubmit="return confirm('Supprimer ce joueur et toutes ses donnees ?');">
                                                <input type="hidden" name="action" value="supprimer_joueur">
                                                <input type="hidden" name="equipe_id" value="<?= $selectedTeamId ?>">
                                                <input type="hidden" name="saison_id" value="<?= $selectedSeasonId ?>">
                                                <input type="hidden" name="joueur_id" value="<?= (int) $stat['id'] ?>">
                                                <button type="submit" class="btn btn-delete team-action-btn">Supprimer</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="team-panel">
            <h2 class="section-title">Ajouter des statistiques de match</h2>
            <p>Le formulaire de saisie de match a ete deplace dans une page dediee pour alleger cette vue.</p>
            <a href="match_ajout.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>" class="btn btn-add">Ouvrir la page match</a>
        </section>

        <section class="team-panel">
            <h2 class="section-title">Dernieres seances <?= $selectedSeasonId > 0 ? 'de la saison active' : 'memorisees' ?></h2>
            <?php if (count($dernieresSeances) === 0): ?>
                <div class="empty-state team-empty">Aucune seance memorisee pour le moment.</div>
            <?php else: ?>
                <div class="team-feed">
                    <?php foreach ($dernieresSeances as $seance): ?>
                        <article class="team-feed-card">
                            <strong><?= htmlspecialchars($seance['nom']) ?></strong>
                            <span><?= htmlspecialchars($seance['date_seance']) ?> - <?= htmlspecialchars($seance['intitule']) ?></span>
                            <p><?= htmlspecialchars($seance['commentaire'] ?: 'Aucun commentaire.') ?></p>
                            <form method="POST" class="inline-action-form" onsubmit="return confirm('Supprimer cette seance ?');">
                                <input type="hidden" name="action" value="<?= $seance['type_source'] === 'planifiee' ? 'supprimer_seance_planifiee' : 'supprimer_seance_memoire' ?>">
                                <input type="hidden" name="equipe_id" value="<?= $selectedTeamId ?>">
                                <input type="hidden" name="saison_id" value="<?= $selectedSeasonId ?>">
                                <input type="hidden" name="<?= $seance['type_source'] === 'planifiee' ? 'assignation_id' : 'seance_id' ?>" value="<?= (int) $seance['id'] ?>">
                                <button type="submit" class="btn btn-delete team-action-btn">Supprimer</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="team-panel">
            <h2 class="section-title">Derniers matchs <?= $selectedSeasonId > 0 ? 'de la saison active' : 'saisis' ?></h2>
            <?php if (count($derniersMatchs) === 0): ?>
                <div class="empty-state team-empty">Aucune statistique de match enregistree.</div>
            <?php else: ?>
                <div class="team-feed">
                    <?php foreach ($derniersMatchs as $match): ?>
                        <article class="team-feed-card">
                            <strong>
                                <?= htmlspecialchars($match['date_match']) ?>
                                <?php if (!empty($match['adversaire'])): ?>
                                    - <?= htmlspecialchars($match['adversaire']) ?>
                                <?php endif; ?>
                            </strong>
                            <span>
                                Statut: <?= htmlspecialchars($match['statut'] === 'joue' ? 'Joue' : 'Planifie') ?>
                            </span>
                            <?php if ($match['score_equipe'] !== null && $match['score_adverse'] !== null): ?>
                                <p>Score: <?= (int) $match['score_equipe'] ?> - <?= (int) $match['score_adverse'] ?></p>
                            <?php endif; ?>
                            <p>
                                <?= (int) $match['buts'] ?> but(s),
                                <?= (int) $match['passes_decisives'] ?> passe(s) decisive(s),
                                <?= (int) $match['joueurs_ayant_stats'] ?> joueur(s) renseigne(s)
                            </p>
                            <div class="form-buttons" style="justify-content: flex-start;">
                                <a href="match_ajout.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>&match_id=<?= (int) $match['id'] ?>" class="btn btn-edit">Modifier</a>
                            </div>
                            <form method="POST" class="inline-action-form" onsubmit="return confirm('Supprimer ce match pour l\'equipe ?');">
                                <input type="hidden" name="action" value="supprimer_match_equipe">
                                <input type="hidden" name="equipe_id" value="<?= $selectedTeamId ?>">
                                <input type="hidden" name="saison_id" value="<?= $selectedSeasonId ?>">
                                <input type="hidden" name="match_id" value="<?= (int) $match['id'] ?>">
                                <button type="submit" class="btn btn-delete team-action-btn">Supprimer</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <script>
        document.querySelectorAll('.position-filter-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                document.querySelectorAll('.position-filter-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const selectedPosition = this.dataset.position;
                const rows = document.querySelectorAll('.player-row');
                
                rows.forEach(row => {
                    const postes = row.dataset.postes;
                    
                    if (selectedPosition === 'all') {
                        row.style.display = '';
                    } else {
                        const postesList = postes.split(',').map(p => p.trim());
                        row.style.display = postesList.includes(selectedPosition) ? '' : 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>