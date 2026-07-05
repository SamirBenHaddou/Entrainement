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

$selectedTeamId = isset($_GET['equipe_id']) ? (int) $_GET['equipe_id'] : 0;
$selectedSeasonId = isset($_GET['saison_id']) ? (int) $_GET['saison_id'] : 0;

function redirect_equipes(string $status, int $teamId = 0, int $seasonId = 0): void
{
    $url = 'equipes.php?status=' . urlencode($status);
    if ($teamId > 0) {
        $url .= '&equipe_id=' . $teamId;
    }
    if ($seasonId > 0) {
        $url .= '&saison_id=' . $seasonId;
    }
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];

    if ($action === 'ajouter_equipe') {
        $nom = trim((string) ($_POST['nom_equipe'] ?? ''));
        if ($nom === '') {
            redirect_equipes('equipe_invalide', $selectedTeamId, $selectedSeasonId);
        }

        $stmt = $pdo->prepare('INSERT INTO equipes (user_id, nom) VALUES (?, ?)');
        $stmt->execute([$userId, $nom]);

        redirect_equipes('equipe_ajoutee', (int) $pdo->lastInsertId(), $selectedSeasonId);
    }

    if ($action === 'modifier_equipe') {
        $teamId = (int) ($_POST['equipe_id'] ?? 0);
        $nom = trim((string) ($_POST['nom_equipe'] ?? ''));

        if ($teamId <= 0 || $nom === '') {
            redirect_equipes('equipe_invalide', $selectedTeamId, $selectedSeasonId);
        }

        $stmt = $pdo->prepare('UPDATE equipes SET nom = ? WHERE id = ? AND user_id = ?');
        $stmt->execute([$nom, $teamId, $userId]);

        redirect_equipes($stmt->rowCount() > 0 ? 'equipe_modifiee' : 'equipe_introuvable', $teamId, $selectedSeasonId);
    }

    if ($action === 'supprimer_equipe') {
        $teamId = (int) ($_POST['equipe_id'] ?? 0);

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM equipes WHERE user_id = ?');
        $countStmt->execute([$userId]);
        $teamCount = (int) $countStmt->fetchColumn();

        if ($teamId <= 0 || $teamCount <= 1) {
            redirect_equipes('equipe_non_supprimable', $selectedTeamId, $selectedSeasonId);
        }

        $deleteStatsStmt = $pdo->prepare(
            'DELETE jm FROM joueur_matchs jm
             JOIN joueurs j ON j.id = jm.joueur_id
             WHERE j.user_id = ? AND j.equipe_id = ?'
        );
        $deleteStatsStmt->execute([$userId, $teamId]);

        $deleteSeancesMemoireStmt = $pdo->prepare(
            'DELETE js FROM joueur_seances js
             JOIN joueurs j ON j.id = js.joueur_id
             WHERE j.user_id = ? AND j.equipe_id = ?'
        );
        $deleteSeancesMemoireStmt->execute([$userId, $teamId]);

        $deleteSeancesPlanifieesStmt = $pdo->prepare(
            'DELETE sj FROM seance_joueurs sj
             JOIN joueurs j ON j.id = sj.joueur_id
             WHERE j.user_id = ? AND j.equipe_id = ?'
        );
        $deleteSeancesPlanifieesStmt->execute([$userId, $teamId]);

        $deleteMatchsEquipeStmt = $pdo->prepare('DELETE FROM equipe_matchs WHERE user_id = ? AND equipe_id = ?');
        $deleteMatchsEquipeStmt->execute([$userId, $teamId]);

        $deleteSaisonsStmt = $pdo->prepare('DELETE FROM saisons WHERE user_id = ? AND equipe_id = ?');
        $deleteSaisonsStmt->execute([$userId, $teamId]);

        $deleteJoueursStmt = $pdo->prepare('DELETE FROM joueurs WHERE user_id = ? AND equipe_id = ?');
        $deleteJoueursStmt->execute([$userId, $teamId]);

        $deleteEquipeStmt = $pdo->prepare('DELETE FROM equipes WHERE id = ? AND user_id = ?');
        $deleteEquipeStmt->execute([$teamId, $userId]);

        $nextTeamStmt = $pdo->prepare('SELECT id FROM equipes WHERE user_id = ? ORDER BY nom ASC LIMIT 1');
        $nextTeamStmt->execute([$userId]);
        $nextTeamId = (int) $nextTeamStmt->fetchColumn();

        redirect_equipes('equipe_supprimee', $nextTeamId, 0);
    }
}

$equipesStmt = $pdo->prepare('SELECT id, nom FROM equipes WHERE user_id = ? ORDER BY nom ASC');
$equipesStmt->execute([$userId]);
$equipes = $equipesStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($equipes) === 0) {
    $stmt = $pdo->prepare('INSERT INTO equipes (user_id, nom) VALUES (?, ?)');
    $stmt->execute([$userId, 'Equipe principale']);

    $equipesStmt->execute([$userId]);
    $equipes = $equipesStmt->fetchAll(PDO::FETCH_ASSOC);
}

if ($selectedTeamId <= 0) {
    $selectedTeamId = (int) ($equipes[0]['id'] ?? 0);
}

$status = $_GET['status'] ?? '';
$statusMessages = [
    'equipe_ajoutee' => ['success', 'Equipe creee avec succes.'],
    'equipe_modifiee' => ['success', 'Equipe modifiee avec succes.'],
    'equipe_supprimee' => ['success', 'Equipe supprimee avec succes.'],
    'equipe_invalide' => ['error', 'Le nom de l\'equipe est obligatoire.'],
    'equipe_introuvable' => ['error', 'Equipe introuvable.'],
    'equipe_non_supprimable' => ['error', 'Impossible de supprimer cette equipe (gardez au moins une equipe).'],
];
$flash = isset($statusMessages[$status]) ? $statusMessages[$status] : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="f7070317-bfa5-464f-bf91-24cf10f1ad59" type="text/javascript" async></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des equipes - MasterCoach</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="header">
        <h1>Gestion des equipes</h1>
        <a href="equipe.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>" class="home-btn">Retour equipe</a>
    </div>

    <?php if ($flash !== null): ?>
        <div class="team-flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <div class="team-layout">
        <section class="team-panel">
            <h2 class="section-title">Ajouter une equipe</h2>
            <form method="POST" class="add-form team-form">
                <input type="hidden" name="action" value="ajouter_equipe">
                <div>
                    <label for="nom-equipe">Nom</label>
                    <input type="text" id="nom-equipe" name="nom_equipe" placeholder="Ex. U17 Regional" required>
                </div>
                <button type="submit" class="btn btn-add">Creer</button>
            </form>
        </section>

        <section class="team-panel team-panel-wide">
            <h2 class="section-title">Equipes existantes</h2>
            <?php if (count($equipes) === 0): ?>
                <div class="empty-state team-empty">Aucune equipe.</div>
            <?php else: ?>
                <div class="team-feed">
                    <?php foreach ($equipes as $equipe): ?>
                        <article class="team-feed-card">
                            <strong><?= htmlspecialchars($equipe['nom']) ?></strong>
                            <div class="form-buttons" style="justify-content: flex-start;">
                                <a href="equipe.php?equipe_id=<?= (int) $equipe['id'] ?>&saison_id=<?= $selectedSeasonId ?>" class="btn btn-edit">Ouvrir</a>
                            </div>
                            <form method="POST" class="add-form team-form" style="margin-top: 12px;">
                                <input type="hidden" name="action" value="modifier_equipe">
                                <input type="hidden" name="equipe_id" value="<?= (int) $equipe['id'] ?>">
                                <div>
                                    <label for="rename-<?= (int) $equipe['id'] ?>">Renommer</label>
                                    <input type="text" id="rename-<?= (int) $equipe['id'] ?>" name="nom_equipe" value="<?= htmlspecialchars($equipe['nom']) ?>" required>
                                </div>
                                <div class="form-buttons" style="justify-content: flex-start;">
                                    <button type="submit" class="btn btn-edit">Enregistrer</button>
                                </div>
                            </form>
                            <form method="POST" class="inline-action-form" onsubmit="return confirm('Supprimer cette equipe et toutes ses donnees ?');">
                                <input type="hidden" name="action" value="supprimer_equipe">
                                <input type="hidden" name="equipe_id" value="<?= (int) $equipe['id'] ?>">
                                <button type="submit" class="btn btn-delete" <?= count($equipes) <= 1 ? 'disabled' : '' ?>>Supprimer</button>
                            </form>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
