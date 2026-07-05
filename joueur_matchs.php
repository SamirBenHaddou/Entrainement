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
$joueurId = (int) ($_GET['id'] ?? 0);

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
    $pdo->exec('ALTER TABLE joueur_matchs ADD COLUMN match_id INT DEFAULT NULL');
} catch (Exception $e) {
}

$joueurStmt = $pdo->prepare('SELECT id, equipe_id, nom FROM joueurs WHERE id = ? AND user_id = ?');
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

$summarySql =
    'SELECT
        COALESCE(SUM(matchs_joues), 0) AS matchs,
        COALESCE(SUM(buts), 0) AS buts,
        COALESCE(SUM(passes_decisives), 0) AS passes
     FROM joueur_matchs
     WHERE user_id = :user_id AND joueur_id = :joueur_id' . ($selectedSeasonId > 0 ? ' AND saison_id = :season_id' : '');
$summaryStmt = $pdo->prepare($summarySql);
$summaryStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$summaryStmt->bindValue(':joueur_id', $joueurId, PDO::PARAM_INT);
if ($selectedSeasonId > 0) {
    $summaryStmt->bindValue(':season_id', $selectedSeasonId, PDO::PARAM_INT);
}
$summaryStmt->execute();
$summary = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: ['matchs' => 0, 'buts' => 0, 'passes' => 0];

$stats = [
    'matchs' => (int) ($summary['matchs'] ?? 0),
    'buts' => (int) ($summary['buts'] ?? 0),
    'passes' => (int) ($summary['passes'] ?? 0),
    'contributions' => 0,
    'ratio' => '0.00',
];
$stats['contributions'] = $stats['buts'] + $stats['passes'];
if ($stats['matchs'] > 0) {
    $stats['ratio'] = number_format($stats['contributions'] / $stats['matchs'], 2, '.', '');
}

$matchsSql =
    'SELECT
        jm.date_match,
        jm.adversaire,
        jm.matchs_joues,
        jm.buts,
        jm.passes_decisives,
        em.score_equipe,
        em.score_adverse
     FROM joueur_matchs jm
     LEFT JOIN equipe_matchs em ON em.id = jm.match_id AND em.user_id = jm.user_id
     WHERE jm.user_id = :user_id AND jm.joueur_id = :joueur_id' . ($selectedSeasonId > 0 ? ' AND jm.saison_id = :season_id' : '') . '
     ORDER BY jm.date_match DESC, jm.id DESC
     LIMIT 50';
$matchsStmt = $pdo->prepare($matchsSql);
$matchsStmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
$matchsStmt->bindValue(':joueur_id', $joueurId, PDO::PARAM_INT);
if ($selectedSeasonId > 0) {
    $matchsStmt->bindValue(':season_id', $selectedSeasonId, PDO::PARAM_INT);
}
$matchsStmt->execute();
$matchs = $matchsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="f7070317-bfa5-464f-bf91-24cf10f1ad59" type="text/javascript" async></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feuille matchs joueur - MasterCoach</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="header">
        <h1>Feuille matchs: <?= htmlspecialchars($joueur['nom']) ?></h1>
        <a href="joueur.php?id=<?= (int) $joueur['id'] ?>&saison_id=<?= $selectedSeasonId ?>" class="home-btn">Retour profil</a>
    </div>

    <div class="team-layout">
        <section class="team-panel team-panel-wide">
            <h2 class="section-title">Filtre saison</h2>
            <form method="GET" class="add-form team-inline-form">
                <input type="hidden" name="id" value="<?= (int) $joueur['id'] ?>">
                <label for="saison-id">Voir les matchs de la saison</label>
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

        <section class="team-summary-grid team-panel-wide" aria-label="Resume matchs joueur">
            <article class="team-summary-card">
                <span class="team-summary-label">Matchs joues</span>
                <strong><?= $stats['matchs'] ?></strong>
            </article>
            <article class="team-summary-card">
                <span class="team-summary-label">Buts</span>
                <strong><?= $stats['buts'] ?></strong>
            </article>
            <article class="team-summary-card">
                <span class="team-summary-label">Passes decisives</span>
                <strong><?= $stats['passes'] ?></strong>
            </article>
            <article class="team-summary-card">
                <span class="team-summary-label">Contributions offensives</span>
                <strong><?= $stats['contributions'] ?></strong>
            </article>
            <article class="team-summary-card">
                <span class="team-summary-label">Contrib. par match</span>
                <strong><?= htmlspecialchars($stats['ratio']) ?></strong>
            </article>
        </section>

        <section class="team-panel team-panel-wide">
            <h2 class="section-title">Historique des matchs <?= $selectedSeasonId > 0 ? 'de la saison' : '' ?></h2>
            <?php if (count($matchs) === 0): ?>
                <div class="empty-state team-empty">Aucune statistique de match enregistree.</div>
            <?php else: ?>
                <div class="team-table-wrapper">
                    <table class="team-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Adversaire</th>
                                <th>Score equipe</th>
                                <th>Matchs joues</th>
                                <th>Buts</th>
                                <th>Passes decisives</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($matchs as $match): ?>
                                <tr>
                                    <td data-label="Date"><?= htmlspecialchars($match['date_match']) ?></td>
                                    <td data-label="Adversaire"><?= htmlspecialchars((string) ($match['adversaire'] ?? '')) ?></td>
                                    <td data-label="Score equipe">
                                        <?php if ($match['score_equipe'] !== null && $match['score_adverse'] !== null): ?>
                                            <?= (int) $match['score_equipe'] ?> - <?= (int) $match['score_adverse'] ?>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Matchs joues"><?= (int) $match['matchs_joues'] ?></td>
                                    <td data-label="Buts"><?= (int) $match['buts'] ?></td>
                                    <td data-label="Passes decisives"><?= (int) $match['passes_decisives'] ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
