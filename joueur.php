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
$joueurId = (int) ($_GET['id'] ?? $_POST['joueur_id'] ?? 0);

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
        points_forts TEXT DEFAULT NULL,
        points_faibles TEXT DEFAULT NULL,
        commentaire_joueur TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_joueurs_user (user_id),
        INDEX idx_joueurs_equipe (equipe_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

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
        INDEX idx_joueur_seances_saison (saison_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS joueur_matchs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        joueur_id INT NOT NULL,
        saison_id INT DEFAULT NULL,
        date_match DATE NOT NULL,
        adversaire VARCHAR(120) DEFAULT NULL,
        buts INT NOT NULL DEFAULT 0,
        passes_decisives INT NOT NULL DEFAULT 0,
        matchs_joues INT NOT NULL DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_joueur_matchs_user (user_id),
        INDEX idx_joueur_matchs_joueur (joueur_id),
        INDEX idx_joueur_matchs_saison (saison_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

try {
    $pdo->exec('ALTER TABLE joueur_seances ADD COLUMN saison_id INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueur_matchs ADD COLUMN saison_id INT DEFAULT NULL');
} catch (Exception $e) {
}

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

function parse_positions(?string $positions): array
{
    if ($positions === null || trim($positions) === '') {
        return [];
    }

    $values = preg_split('/\s*,\s*/', $positions);
    return is_array($values) ? array_values(array_filter($values)) : [];
}

$joueurStmt = $pdo->prepare('SELECT id, equipe_id, nom, poste, points_forts, points_faibles, commentaire_joueur FROM joueurs WHERE id = ? AND user_id = ?');
$joueurStmt->execute([$joueurId, $userId]);
$joueur = $joueurStmt->fetch(PDO::FETCH_ASSOC);

if (!$joueur) {
    header('Location: equipe.php?status=joueur_introuvable');
    exit;
}

$selectedTeamId = (int) $joueur['equipe_id'];

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

$knownSeasonIds = array_map(static fn(array $saison) => (int) $saison['id'], $saisons);
$selectedSeasonId = isset($_GET['saison_id']) ? (int) $_GET['saison_id'] : 0;
if ($selectedSeasonId > 0 && !in_array($selectedSeasonId, $knownSeasonIds, true)) {
    $selectedSeasonId = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mettre_a_jour_joueur') {
    $nom = trim($_POST['nom'] ?? '');
    $poste = normalize_positions($_POST['postes'] ?? [], $positionOptions);
    $pointsForts = trim($_POST['points_forts'] ?? '');
    $pointsFaibles = trim($_POST['points_faibles'] ?? '');
    $commentaireJoueur = trim($_POST['commentaire_joueur'] ?? '');

    if ($nom === '') {
        header('Location: joueur.php?id=' . $joueurId . '&saison_id=' . $selectedSeasonId . '&status=joueur_invalide');
        exit;
    }

    $updateStmt = $pdo->prepare(
        'UPDATE joueurs
         SET nom = ?, poste = ?, points_forts = ?, points_faibles = ?, commentaire_joueur = ?
         WHERE id = ? AND user_id = ?'
    );
    $updateStmt->execute([
        $nom,
        $poste,
        $pointsForts !== '' ? $pointsForts : null,
        $pointsFaibles !== '' ? $pointsFaibles : null,
        $commentaireJoueur !== '' ? $commentaireJoueur : null,
        $joueurId,
        $userId,
    ]);

    header('Location: joueur.php?id=' . $joueurId . '&saison_id=' . $selectedSeasonId . '&status=joueur_mis_a_jour');
    exit;
}

$seancesSql =
    'SELECT date_seance, intitule, commentaire
     FROM joueur_seances
     WHERE user_id = :user_id AND joueur_id = :joueur_id' . ($selectedSeasonId > 0 ? ' AND saison_id = :season_id' : '') . '
     ORDER BY date_seance DESC, id DESC
     LIMIT 10';
$seancesStmt = $pdo->prepare($seancesSql);
$seancesStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$seancesStmt->bindValue(':joueur_id', $joueurId, PDO::PARAM_INT);
if ($selectedSeasonId > 0) {
    $seancesStmt->bindValue(':season_id', $selectedSeasonId, PDO::PARAM_INT);
}
$seancesStmt->execute();
$seances = $seancesStmt->fetchAll(PDO::FETCH_ASSOC);

$playerMatchSummarySql =
    'SELECT
        COALESCE(SUM(matchs_joues), 0) AS matchs_joues,
        COALESCE(SUM(buts), 0) AS buts,
        COALESCE(SUM(passes_decisives), 0) AS passes_decisives
     FROM joueur_matchs
     WHERE user_id = :user_id AND joueur_id = :joueur_id' . ($selectedSeasonId > 0 ? ' AND saison_id = :season_id' : '');
$playerMatchSummaryStmt = $pdo->prepare($playerMatchSummarySql);
$playerMatchSummaryStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$playerMatchSummaryStmt->bindValue(':joueur_id', $joueurId, PDO::PARAM_INT);
if ($selectedSeasonId > 0) {
    $playerMatchSummaryStmt->bindValue(':season_id', $selectedSeasonId, PDO::PARAM_INT);
}
$playerMatchSummaryStmt->execute();
$playerMatchSummary = $playerMatchSummaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

$playerSeanceSummarySql =
    'SELECT COUNT(*) AS seances
     FROM joueur_seances
     WHERE user_id = :user_id AND joueur_id = :joueur_id' . ($selectedSeasonId > 0 ? ' AND saison_id = :season_id' : '');
$playerSeanceSummaryStmt = $pdo->prepare($playerSeanceSummarySql);
$playerSeanceSummaryStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$playerSeanceSummaryStmt->bindValue(':joueur_id', $joueurId, PDO::PARAM_INT);
if ($selectedSeasonId > 0) {
    $playerSeanceSummaryStmt->bindValue(':season_id', $selectedSeasonId, PDO::PARAM_INT);
}
$playerSeanceSummaryStmt->execute();

$playerStats = [
    'matchs' => (int) ($playerMatchSummary['matchs_joues'] ?? 0),
    'buts' => (int) ($playerMatchSummary['buts'] ?? 0),
    'passes' => (int) ($playerMatchSummary['passes_decisives'] ?? 0),
    'seances' => (int) $playerSeanceSummaryStmt->fetchColumn(),
    'contributions' => 0,
    'ratio' => '0.00',
];
$playerStats['contributions'] = $playerStats['buts'] + $playerStats['passes'];
if ($playerStats['matchs'] > 0) {
    $playerStats['ratio'] = number_format($playerStats['contributions'] / $playerStats['matchs'], 2, '.', '');
}

$joueurPostes = parse_positions($joueur['poste'] ?? null);
$status = $_GET['status'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="f7070317-bfa5-464f-bf91-24cf10f1ad59" type="text/javascript" async></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil joueur - MasterCoach</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="header">
        <h1>Profil: <?= htmlspecialchars($joueur['nom']) ?></h1>
        <a href="equipe.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>" class="home-btn">Retour equipe</a>
    </div>

    <?php if ($status === 'joueur_mis_a_jour'): ?>
        <div class="team-flash success">Fiche joueur mise a jour.</div>
    <?php elseif ($status === 'joueur_invalide'): ?>
        <div class="team-flash error">Le nom du joueur est obligatoire.</div>
    <?php endif; ?>

    <div class="team-layout">
        <section class="team-panel team-panel-wide">
            <h2 class="section-title">Filtre saison</h2>
            <form method="GET" class="add-form team-inline-form">
                <input type="hidden" name="id" value="<?= (int) $joueur['id'] ?>">
                <label for="saison-id">Voir les stats de la saison</label>
                <select id="saison-id" name="saison_id" onchange="this.form.submit()">
                    <option value="0" <?= $selectedSeasonId === 0 ? 'selected' : '' ?>>Toutes les saisons</option>
                    <?php foreach ($saisons as $saison): ?>
                        <option value="<?= (int) $saison['id'] ?>" <?= (int) $saison['id'] === $selectedSeasonId ? 'selected' : '' ?>>
                            <?= htmlspecialchars($saison['nom']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </section>

        <section class="team-summary-grid team-panel-wide" aria-label="Resume des statistiques joueur">
            <article class="team-summary-card">
                <span class="team-summary-label">Matchs joues</span>
                <strong><?= $playerStats['matchs'] ?></strong>
            </article>
            <article class="team-summary-card">
                <span class="team-summary-label">Buts</span>
                <strong><?= $playerStats['buts'] ?></strong>
            </article>
            <article class="team-summary-card">
                <span class="team-summary-label">Passes decisives</span>
                <strong><?= $playerStats['passes'] ?></strong>
            </article>
            <article class="team-summary-card">
                <span class="team-summary-label">Contributions offensives</span>
                <strong><?= $playerStats['contributions'] ?></strong>
            </article>
            <article class="team-summary-card">
                <span class="team-summary-label">Contrib. par match</span>
                <strong><?= htmlspecialchars($playerStats['ratio']) ?></strong>
            </article>
            <article class="team-summary-card">
                <span class="team-summary-label">Seances</span>
                <strong><?= $playerStats['seances'] ?></strong>
            </article>
        </section>

        <section class="team-panel">
            <h2 class="section-title">Fiche joueur</h2>
            <form method="POST" class="add-form team-form">
                <input type="hidden" name="action" value="mettre_a_jour_joueur">
                <input type="hidden" name="joueur_id" value="<?= (int) $joueur['id'] ?>">

                <div>
                    <label for="nom-joueur">Nom du joueur</label>
                    <input type="text" id="nom-joueur" name="nom" value="<?= htmlspecialchars($joueur['nom']) ?>" required>
                </div>

                <div>
                    <span class="team-field-label">Postes</span>
                    <div class="position-selector-grid">
                        <?php foreach ($positionOptions as $positionOption): ?>
                            <label class="position-option">
                                <input type="checkbox" name="postes[]" value="<?= htmlspecialchars($positionOption) ?>" <?= in_array($positionOption, $joueurPostes, true) ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($positionOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label for="points-forts">Points forts</label>
                    <textarea id="points-forts" name="points_forts" placeholder="Qualites principales du joueur"><?= htmlspecialchars($joueur['points_forts'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="points-faibles">Points faibles</label>
                    <textarea id="points-faibles" name="points_faibles" placeholder="Axes d'amelioration"><?= htmlspecialchars($joueur['points_faibles'] ?? '') ?></textarea>
                </div>
                <div>
                    <label for="commentaire-joueur">Commentaire coach</label>
                    <textarea id="commentaire-joueur" name="commentaire_joueur" placeholder="Notes complementaires"><?= htmlspecialchars($joueur['commentaire_joueur'] ?? '') ?></textarea>
                </div>

                <div class="form-buttons">
                    <button type="submit" class="btn btn-add">Enregistrer la fiche</button>
                    <a href="match_ajout.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>&joueur_id=<?= (int) $joueur['id'] ?>" class="btn btn-edit">Ajouter un match</a>
                </div>
            </form>
        </section>

        <section class="team-panel">
            <h2 class="section-title">Feuille stats matchs</h2>
            <p>Consultez l'historique complet des matchs du joueur sur une page dediee.</p>
            <a href="joueur_matchs.php?id=<?= (int) $joueur['id'] ?>&saison_id=<?= $selectedSeasonId ?>" class="btn btn-edit">Ouvrir la feuille matchs</a>
        </section>

        <section class="team-panel">
            <h2 class="section-title">Dernieres seances <?= $selectedSeasonId > 0 ? 'de la saison' : '' ?></h2>
            <?php if (count($seances) === 0): ?>
                <div class="empty-state team-empty">Aucune seance enregistree.</div>
            <?php else: ?>
                <div class="team-feed">
                    <?php foreach ($seances as $seance): ?>
                        <article class="team-feed-card">
                            <strong><?= htmlspecialchars($seance['date_seance']) ?></strong>
                            <span><?= htmlspecialchars($seance['intitule']) ?></span>
                            <p><?= htmlspecialchars($seance['commentaire'] ?: 'Aucun commentaire.') ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>
</body>
</html>
