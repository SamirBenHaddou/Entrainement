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
        poste VARCHAR(255) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_joueurs_user (user_id),
        INDEX idx_joueurs_equipe (equipe_id)
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
        INDEX idx_joueur_matchs_match (match_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

try {
    $pdo->exec('ALTER TABLE joueurs ADD COLUMN equipe_id INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueur_matchs ADD COLUMN saison_id INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueur_matchs ADD COLUMN match_id INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE equipe_matchs ADD COLUMN score_equipe INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE equipe_matchs ADD COLUMN score_adverse INT DEFAULT NULL');
} catch (Exception $e) {
}

$teamsStmt = $pdo->prepare('SELECT id, nom FROM equipes WHERE user_id = ? ORDER BY nom ASC');
$teamsStmt->execute([$userId]);
$equipes = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($equipes) === 0) {
    $stmt = $pdo->prepare('INSERT INTO equipes (user_id, nom) VALUES (?, ?)');
    $stmt->execute([$userId, 'Equipe principale']);
    $teamsStmt->execute([$userId]);
    $equipes = $teamsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$selectedTeamId = isset($_GET['equipe_id']) ? (int) $_GET['equipe_id'] : (int) ($equipes[0]['id'] ?? 0);
$knownTeamIds = array_map(static fn(array $team) => (int) $team['id'], $equipes);
if ($selectedTeamId <= 0 || !in_array($selectedTeamId, $knownTeamIds, true)) {
    $selectedTeamId = (int) ($equipes[0]['id'] ?? 0);
}

$saisonsStmt = $pdo->prepare('SELECT id, nom FROM saisons WHERE user_id = ? AND equipe_id = ? ORDER BY created_at DESC, id DESC');
$saisonsStmt->execute([$userId, $selectedTeamId]);
$saisons = $saisonsStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($saisons) === 0) {
    $year = (int) date('Y');
    $defaultSeasonName = 'Saison ' . $year . '-' . ($year + 1);
    $createSeasonStmt = $pdo->prepare(
        'INSERT INTO saisons (user_id, equipe_id, nom, date_debut, date_fin) VALUES (?, ?, ?, ?, ?)'
    );
    $createSeasonStmt->execute([
        $userId,
        $selectedTeamId,
        $defaultSeasonName,
        date('Y-07-01'),
        date('Y-m-d', strtotime('+1 year', strtotime(date('Y-06-30')))),
    ]);

    $saisonsStmt->execute([$userId, $selectedTeamId]);
    $saisons = $saisonsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$selectedSeasonId = isset($_GET['saison_id']) ? (int) $_GET['saison_id'] : (int) ($saisons[0]['id'] ?? 0);
$knownSeasonIds = array_map(static fn(array $saison) => (int) $saison['id'], $saisons);
if ($selectedSeasonId <= 0 || !in_array($selectedSeasonId, $knownSeasonIds, true)) {
    $selectedSeasonId = (int) ($saisons[0]['id'] ?? 0);
}

function redirect_match_page(string $status, int $teamId, int $seasonId, ?int $matchId = null): void
{
    $url = 'match_ajout.php?equipe_id=' . $teamId . '&saison_id=' . $seasonId . '&status=' . urlencode($status);
    if ($matchId !== null && $matchId > 0) {
        $url .= '&match_id=' . $matchId;
    }
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];
    $teamId = (int) ($_POST['equipe_id'] ?? $selectedTeamId);
    $seasonId = (int) ($_POST['saison_id'] ?? $selectedSeasonId);

    if (!in_array($teamId, $knownTeamIds, true)) {
        $teamId = $selectedTeamId;
    }

    $postSeasonsStmt = $pdo->prepare('SELECT id FROM saisons WHERE user_id = ? AND equipe_id = ?');
    $postSeasonsStmt->execute([$userId, $teamId]);
    $postSeasonIds = array_map(static fn(array $row) => (int) $row['id'], $postSeasonsStmt->fetchAll(PDO::FETCH_ASSOC));
    if ($seasonId <= 0 || !in_array($seasonId, $postSeasonIds, true)) {
        $seasonId = (int) ($postSeasonIds[0] ?? 0);
    }

    if ($action === 'creer_match') {
        $dateMatch = trim((string) ($_POST['date_match'] ?? ''));
        $adversaire = trim((string) ($_POST['adversaire'] ?? ''));
        $scoreEquipeRaw = trim((string) ($_POST['score_equipe'] ?? ''));
        $scoreAdverseRaw = trim((string) ($_POST['score_adverse'] ?? ''));
        $scoreEquipe = $scoreEquipeRaw !== '' ? max(0, (int) $scoreEquipeRaw) : null;
        $scoreAdverse = $scoreAdverseRaw !== '' ? max(0, (int) $scoreAdverseRaw) : null;
        $statutMatch = ($scoreEquipe !== null && $scoreAdverse !== null) ? 'joue' : 'planifie';

        if ($dateMatch === '') {
            redirect_match_page('match_invalide', $teamId, $seasonId);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO equipe_matchs (user_id, equipe_id, saison_id, date_match, adversaire, score_equipe, score_adverse, statut)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $teamId,
            $seasonId,
            $dateMatch,
            $adversaire !== '' ? $adversaire : null,
            $scoreEquipe,
            $scoreAdverse,
            $statutMatch,
        ]);

        redirect_match_page('match_planifie', $teamId, $seasonId, (int) $pdo->lastInsertId());
    }

    if ($action === 'supprimer_match') {
        $matchId = (int) ($_POST['match_id'] ?? 0);

        $stmt = $pdo->prepare('DELETE FROM joueur_matchs WHERE user_id = ? AND match_id = ?');
        $stmt->execute([$userId, $matchId]);

        $stmt = $pdo->prepare('DELETE FROM equipe_matchs WHERE id = ? AND user_id = ? AND equipe_id = ?');
        $stmt->execute([$matchId, $userId, $teamId]);

        redirect_match_page($stmt->rowCount() > 0 ? 'match_supprime' : 'match_introuvable', $teamId, $seasonId);
    }

    if ($action === 'enregistrer_stats_match') {
        $matchId = (int) ($_POST['match_id'] ?? 0);
        $dateMatch = trim((string) ($_POST['date_match'] ?? ''));
        $adversaire = trim((string) ($_POST['adversaire'] ?? ''));
        $statut = isset($_POST['match_joue']) ? 'joue' : 'planifie';
        $scoreEquipeRaw = trim((string) ($_POST['score_equipe'] ?? ''));
        $scoreAdverseRaw = trim((string) ($_POST['score_adverse'] ?? ''));
        $scoreEquipe = $scoreEquipeRaw !== '' ? max(0, (int) $scoreEquipeRaw) : null;
        $scoreAdverse = $scoreAdverseRaw !== '' ? max(0, (int) $scoreAdverseRaw) : null;

        if ($matchId <= 0 || $dateMatch === '') {
            redirect_match_page('match_invalide', $teamId, $seasonId, $matchId > 0 ? $matchId : null);
        }

        if ($statut === 'joue' && ($scoreEquipe === null || $scoreAdverse === null)) {
            redirect_match_page('score_invalide', $teamId, $seasonId, $matchId);
        }

        if ($statut !== 'joue') {
            $scoreEquipe = null;
            $scoreAdverse = null;
        }

        $checkMatchStmt = $pdo->prepare(
            'SELECT id FROM equipe_matchs
             WHERE id = ? AND user_id = ? AND equipe_id = ? AND saison_id = ?'
        );
        $checkMatchStmt->execute([$matchId, $userId, $teamId, $seasonId]);
        if (!$checkMatchStmt->fetchColumn()) {
            redirect_match_page('match_introuvable', $teamId, $seasonId);
        }

        $updateMatchStmt = $pdo->prepare(
            'UPDATE equipe_matchs
             SET date_match = ?, adversaire = ?, score_equipe = ?, score_adverse = ?, statut = ?
             WHERE id = ? AND user_id = ? AND equipe_id = ? AND saison_id = ?'
        );
        $updateMatchStmt->execute([
            $dateMatch,
            $adversaire !== '' ? $adversaire : null,
            $scoreEquipe,
            $scoreAdverse,
            $statut,
            $matchId,
            $userId,
            $teamId,
            $seasonId,
        ]);

        $deleteExistingStatsStmt = $pdo->prepare('DELETE FROM joueur_matchs WHERE user_id = ? AND match_id = ?');
        $deleteExistingStatsStmt->execute([$userId, $matchId]);

        $joueurIds = array_map('intval', $_POST['joueur_ids'] ?? []);
        $joueurIds = array_values(array_unique(array_filter($joueurIds, static fn(int $id): bool => $id > 0)));

        $checkJoueurStmt = $pdo->prepare('SELECT id FROM joueurs WHERE id = ? AND user_id = ? AND equipe_id = ?');
        $insertStatStmt = $pdo->prepare(
            'INSERT INTO joueur_matchs (user_id, joueur_id, saison_id, match_id, date_match, adversaire, buts, passes_decisives, matchs_joues)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1)'
        );

        foreach ($joueurIds as $joueurId) {
            $checkJoueurStmt->execute([$joueurId, $userId, $teamId]);
            if (!$checkJoueurStmt->fetchColumn()) {
                continue;
            }

            $buts = max(0, (int) ($_POST['buts'][$joueurId] ?? 0));
            $passes = max(0, (int) ($_POST['passes_decisives'][$joueurId] ?? 0));

            $insertStatStmt->execute([
                $userId,
                $joueurId,
                $seasonId,
                $matchId,
                $dateMatch,
                $adversaire !== '' ? $adversaire : null,
                $buts,
                $passes,
            ]);
        }

        redirect_match_page('stats_enregistrees', $teamId, $seasonId, $matchId);
    }
}

$joueursStmt = $pdo->prepare('SELECT id, nom FROM joueurs WHERE user_id = ? AND equipe_id = ? ORDER BY nom ASC');
$joueursStmt->execute([$userId, $selectedTeamId]);
$joueurs = $joueursStmt->fetchAll(PDO::FETCH_ASSOC);

$matchsStmt = $pdo->prepare(
    'SELECT id, date_match, adversaire, score_equipe, score_adverse, statut
     FROM equipe_matchs
     WHERE user_id = ? AND equipe_id = ? AND saison_id = ?
     ORDER BY date_match DESC, id DESC'
);
$matchsStmt->execute([$userId, $selectedTeamId, $selectedSeasonId]);
$matchs = $matchsStmt->fetchAll(PDO::FETCH_ASSOC);

$selectedMatchId = isset($_GET['match_id']) ? (int) $_GET['match_id'] : 0;
$selectedMatch = null;
foreach ($matchs as $matchRow) {
    if ((int) $matchRow['id'] === $selectedMatchId) {
        $selectedMatch = $matchRow;
        break;
    }
}
if ($selectedMatch === null && count($matchs) > 0) {
    $selectedMatch = $matchs[0];
    $selectedMatchId = (int) $selectedMatch['id'];
}

$statsByPlayer = [];
if ($selectedMatchId > 0) {
    $statsStmt = $pdo->prepare(
        'SELECT jm.joueur_id, jm.buts, jm.passes_decisives
         FROM joueur_matchs jm
         JOIN joueurs j ON j.id = jm.joueur_id
         WHERE jm.user_id = ? AND jm.match_id = ? AND j.equipe_id = ?'
    );
    $statsStmt->execute([$userId, $selectedMatchId, $selectedTeamId]);
    foreach ($statsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $statsByPlayer[(int) $row['joueur_id']] = [
            'buts' => (int) $row['buts'],
            'passes_decisives' => (int) $row['passes_decisives'],
        ];
    }
}

$status = $_GET['status'] ?? null;
$statusMessages = [
    'match_invalide' => ['error', 'Le match doit contenir une date valide.'],
    'score_invalide' => ['error', 'Renseignez les deux scores pour marquer le match comme joue.'],
    'match_planifie' => ['success', 'Match planifie avec succes.'],
    'match_supprime' => ['success', 'Match supprime avec succes.'],
    'match_introuvable' => ['error', 'Match introuvable.'],
    'stats_enregistrees' => ['success', 'Statistiques du match enregistrees.'],
];
$flash = isset($statusMessages[$status]) ? $statusMessages[$status] : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="f7070317-bfa5-464f-bf91-24cf10f1ad59" type="text/javascript" async></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des matchs - MasterCoach</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="header">
        <h1>Gestion des matchs</h1>
        <a href="equipe.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>" class="home-btn">Retour equipe</a>
    </div>

    <?php if ($flash !== null): ?>
        <div class="team-flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <div class="team-layout">
        <section class="team-panel team-panel-wide">
            <h2 class="section-title">Contexte</h2>
            <form method="GET" class="add-form team-form">
                <div class="team-grid-two">
                    <div>
                        <label for="equipe-id">Equipe</label>
                        <select id="equipe-id" name="equipe_id" onchange="this.form.submit()">
                            <?php foreach ($equipes as $equipe): ?>
                                <option value="<?= (int) $equipe['id'] ?>" <?= (int) $equipe['id'] === $selectedTeamId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($equipe['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="saison-id">Saison</label>
                        <select id="saison-id" name="saison_id" onchange="this.form.submit()">
                            <?php foreach ($saisons as $saison): ?>
                                <option value="<?= (int) $saison['id'] ?>" <?= (int) $saison['id'] === $selectedSeasonId ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($saison['nom']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </section>

        <section class="team-panel">
            <h2 class="section-title">Planifier un match</h2>
            <form method="POST" class="add-form team-form">
                <input type="hidden" name="action" value="creer_match">
                <input type="hidden" name="equipe_id" value="<?= $selectedTeamId ?>">
                <input type="hidden" name="saison_id" value="<?= $selectedSeasonId ?>">

                <div>
                    <label for="date-match">Date du match</label>
                    <input type="date" id="date-match" name="date_match" value="<?= date('Y-m-d') ?>" required>
                </div>

                <div>
                    <label for="adversaire">Adversaire</label>
                    <input type="text" id="adversaire" name="adversaire" placeholder="Ex. FC Rivals">
                </div>

                <div class="team-grid-two">
                    <div>
                        <label for="score-equipe">Score equipe (optionnel)</label>
                        <input type="number" id="score-equipe" name="score_equipe" min="0" placeholder="Ex. 2">
                    </div>
                    <div>
                        <label for="score-adverse">Score adverse (optionnel)</label>
                        <input type="number" id="score-adverse" name="score_adverse" min="0" placeholder="Ex. 1">
                    </div>
                </div>

                <button type="submit" class="btn btn-add">Creer le match</button>
            </form>
        </section>

        <section class="team-panel">
            <h2 class="section-title">Matchs de la saison</h2>
            <?php if (count($matchs) === 0): ?>
                <div class="empty-state team-empty">Aucun match cree pour cette saison.</div>
            <?php else: ?>
                <div class="team-feed">
                    <?php foreach ($matchs as $match): ?>
                        <article class="team-feed-card">
                            <strong><?= htmlspecialchars($match['date_match']) ?><?php if (!empty($match['adversaire'])): ?> - <?= htmlspecialchars($match['adversaire']) ?><?php endif; ?></strong>
                            <span>Statut: <?= htmlspecialchars($match['statut'] === 'joue' ? 'Joue' : 'Planifie') ?></span>
                            <?php if ($match['score_equipe'] !== null && $match['score_adverse'] !== null): ?>
                                <span>Score: <?= (int) $match['score_equipe'] ?> - <?= (int) $match['score_adverse'] ?></span>
                            <?php endif; ?>
                            <div class="form-buttons" style="justify-content: flex-start;">
                                <a class="btn btn-edit" href="match_ajout.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>&match_id=<?= (int) $match['id'] ?>">Gerer</a>
                                <form method="POST" class="inline-action-form" onsubmit="return confirm('Supprimer ce match et ses statistiques ?');" style="margin-top: 0;">
                                    <input type="hidden" name="action" value="supprimer_match">
                                    <input type="hidden" name="equipe_id" value="<?= $selectedTeamId ?>">
                                    <input type="hidden" name="saison_id" value="<?= $selectedSeasonId ?>">
                                    <input type="hidden" name="match_id" value="<?= (int) $match['id'] ?>">
                                    <button type="submit" class="btn btn-delete">Supprimer</button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="team-panel team-panel-wide">
            <h2 class="section-title">Saisie des stats joueurs</h2>
            <?php if ($selectedMatch === null): ?>
                <div class="empty-state team-empty">Selectionnez ou creez un match pour saisir les statistiques.</div>
            <?php else: ?>
                <form method="POST" class="add-form team-form">
                    <input type="hidden" name="action" value="enregistrer_stats_match">
                    <input type="hidden" name="equipe_id" value="<?= $selectedTeamId ?>">
                    <input type="hidden" name="saison_id" value="<?= $selectedSeasonId ?>">
                    <input type="hidden" name="match_id" value="<?= (int) $selectedMatch['id'] ?>">

                    <div class="team-grid-two">
                        <div>
                            <label for="date-match-edit">Date du match</label>
                            <input type="date" id="date-match-edit" name="date_match" value="<?= htmlspecialchars((string) $selectedMatch['date_match']) ?>" required>
                        </div>
                        <div>
                            <label for="adversaire-edit">Adversaire</label>
                            <input type="text" id="adversaire-edit" name="adversaire" value="<?= htmlspecialchars((string) ($selectedMatch['adversaire'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="team-grid-two">
                        <div>
                            <label for="score-equipe-edit">Score equipe</label>
                            <input type="number" id="score-equipe-edit" name="score_equipe" value="<?= $selectedMatch['score_equipe'] !== null ? (int) $selectedMatch['score_equipe'] : '' ?>" min="0">
                        </div>
                        <div>
                            <label for="score-adverse-edit">Score adverse</label>
                            <input type="number" id="score-adverse-edit" name="score_adverse" value="<?= $selectedMatch['score_adverse'] !== null ? (int) $selectedMatch['score_adverse'] : '' ?>" min="0">
                        </div>
                    </div>

                    <div>
                        <label class="position-option" style="width: fit-content;">
                            <input type="checkbox" name="match_joue" value="1" <?= $selectedMatch['statut'] === 'joue' ? 'checked' : '' ?>>
                            <span>Match joue</span>
                        </label>
                    </div>

                    <div>
                        <label>Joueurs et stats individuelles</label>
                        <div class="form-buttons" style="justify-content: flex-start; margin-bottom: 10px;">
                            <button type="button" id="select-all-players" class="btn btn-edit">Tout selectionner</button>
                            <button type="button" id="unselect-all-players" class="btn btn-delete">Tout deselectionner</button>
                        </div>
                        <div class="team-table-wrapper">
                            <table class="team-table">
                                <thead>
                                    <tr>
                                        <th>Selection</th>
                                        <th>Joueur</th>
                                        <th>Buts</th>
                                        <th>Passes decisives</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($joueurs as $joueur): ?>
                                        <?php
                                            $jid = (int) $joueur['id'];
                                            $existingStats = $statsByPlayer[$jid] ?? null;
                                            $isChecked = $existingStats !== null || $jid === (int) ($_GET['joueur_id'] ?? 0);
                                        ?>
                                        <tr>
                                            <td>
                                                <input type="checkbox" class="player-match-checkbox" name="joueur_ids[]" value="<?= $jid ?>" <?= $isChecked ? 'checked' : '' ?>>
                                            </td>
                                            <td><?= htmlspecialchars($joueur['nom']) ?></td>
                                            <td>
                                                <input type="number" name="buts[<?= $jid ?>]" value="<?= (int) ($existingStats['buts'] ?? 0) ?>" min="0" style="max-width: 90px;">
                                            </td>
                                            <td>
                                                <input type="number" name="passes_decisives[<?= $jid ?>]" value="<?= (int) ($existingStats['passes_decisives'] ?? 0) ?>" min="0" style="max-width: 90px;">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-add" <?= count($joueurs) === 0 ? 'disabled' : '' ?>>Enregistrer le match</button>
                </form>
            <?php endif; ?>
        </section>
    </div>

    <script>
        const playerCheckboxes = document.querySelectorAll('.player-match-checkbox');
        const selectAllBtn = document.getElementById('select-all-players');
        const unselectAllBtn = document.getElementById('unselect-all-players');

        if (selectAllBtn !== null) {
            selectAllBtn.addEventListener('click', () => {
                playerCheckboxes.forEach((checkbox) => {
                    checkbox.checked = true;
                });
            });
        }

        if (unselectAllBtn !== null) {
            unselectAllBtn.addEventListener('click', () => {
                playerCheckboxes.forEach((checkbox) => {
                    checkbox.checked = false;
                });
            });
        }
    </script>
</body>
</html>
