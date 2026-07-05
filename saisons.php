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
$selectedSeasonId = isset($_GET['saison_id']) ? (int) $_GET['saison_id'] : 0;
$knownTeamIds = array_map(static fn(array $team) => (int) $team['id'], $equipes);
if ($selectedTeamId <= 0 || !in_array($selectedTeamId, $knownTeamIds, true)) {
    $selectedTeamId = (int) ($equipes[0]['id'] ?? 0);
}

function redirect_saisons(string $status, int $teamId, int $seasonId = 0): void
{
    $url = 'saisons.php?status=' . urlencode($status) . '&equipe_id=' . $teamId;
    if ($seasonId > 0) {
        $url .= '&saison_id=' . $seasonId;
    }
    header('Location: ' . $url);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = (string) $_POST['action'];
    $teamId = (int) ($_POST['equipe_id'] ?? $selectedTeamId);

    if (!in_array($teamId, $knownTeamIds, true)) {
        $teamId = $selectedTeamId;
    }

    $saisonsStmt = $pdo->prepare('SELECT id FROM saisons WHERE user_id = ? AND equipe_id = ?');
    $saisonsStmt->execute([$userId, $teamId]);
    $seasonIds = array_map(static fn(array $row) => (int) $row['id'], $saisonsStmt->fetchAll(PDO::FETCH_ASSOC));

    if ($action === 'ajouter_saison') {
        $nom = trim((string) ($_POST['nom_saison'] ?? ''));
        $dateDebut = trim((string) ($_POST['date_debut'] ?? ''));
        $dateFin = trim((string) ($_POST['date_fin'] ?? ''));

        if ($nom === '') {
            redirect_saisons('saison_invalide', $teamId, $selectedSeasonId);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO saisons (user_id, equipe_id, nom, date_debut, date_fin) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            $teamId,
            $nom,
            $dateDebut !== '' ? $dateDebut : null,
            $dateFin !== '' ? $dateFin : null,
        ]);

        redirect_saisons('saison_ajoutee', $teamId, (int) $pdo->lastInsertId());
    }

    if ($action === 'modifier_saison') {
        $seasonId = (int) ($_POST['saison_id'] ?? 0);
        $nom = trim((string) ($_POST['nom_saison'] ?? ''));
        $dateDebut = trim((string) ($_POST['date_debut'] ?? ''));
        $dateFin = trim((string) ($_POST['date_fin'] ?? ''));

        if ($seasonId <= 0 || !in_array($seasonId, $seasonIds, true) || $nom === '') {
            redirect_saisons('saison_invalide', $teamId, $selectedSeasonId);
        }

        $stmt = $pdo->prepare(
            'UPDATE saisons SET nom = ?, date_debut = ?, date_fin = ?
             WHERE id = ? AND user_id = ? AND equipe_id = ?'
        );
        $stmt->execute([
            $nom,
            $dateDebut !== '' ? $dateDebut : null,
            $dateFin !== '' ? $dateFin : null,
            $seasonId,
            $userId,
            $teamId,
        ]);

        redirect_saisons($stmt->rowCount() > 0 ? 'saison_modifiee' : 'saison_introuvable', $teamId, $seasonId);
    }

    if ($action === 'supprimer_saison') {
        $seasonId = (int) ($_POST['saison_id'] ?? 0);

        if ($seasonId <= 0 || !in_array($seasonId, $seasonIds, true) || count($seasonIds) <= 1) {
            redirect_saisons('saison_non_supprimable', $teamId, $selectedSeasonId);
        }

        $deleteMatchsEquipeSaisonStmt = $pdo->prepare('DELETE FROM equipe_matchs WHERE user_id = ? AND equipe_id = ? AND saison_id = ?');
        $deleteMatchsEquipeSaisonStmt->execute([$userId, $teamId, $seasonId]);

        $deleteMatchsSaisonStmt = $pdo->prepare(
            'DELETE jm FROM joueur_matchs jm
             JOIN joueurs j ON j.id = jm.joueur_id
             WHERE jm.user_id = ? AND j.equipe_id = ? AND jm.saison_id = ?'
        );
        $deleteMatchsSaisonStmt->execute([$userId, $teamId, $seasonId]);

        $deleteSeancesSaisonStmt = $pdo->prepare(
            'DELETE js FROM joueur_seances js
             JOIN joueurs j ON j.id = js.joueur_id
             WHERE js.user_id = ? AND j.equipe_id = ? AND js.saison_id = ?'
        );
        $deleteSeancesSaisonStmt->execute([$userId, $teamId, $seasonId]);

        $deleteSeasonStmt = $pdo->prepare('DELETE FROM saisons WHERE id = ? AND user_id = ? AND equipe_id = ?');
        $deleteSeasonStmt->execute([$seasonId, $userId, $teamId]);

        $nextSeasonStmt = $pdo->prepare('SELECT id FROM saisons WHERE user_id = ? AND equipe_id = ? ORDER BY created_at DESC, id DESC LIMIT 1');
        $nextSeasonStmt->execute([$userId, $teamId]);
        $nextSeasonId = (int) $nextSeasonStmt->fetchColumn();

        redirect_saisons('saison_supprimee', $teamId, $nextSeasonId);
    }
}

$saisonsListStmt = $pdo->prepare('SELECT id, nom, date_debut, date_fin FROM saisons WHERE user_id = ? AND equipe_id = ? ORDER BY created_at DESC, id DESC');
$saisonsListStmt->execute([$userId, $selectedTeamId]);
$saisons = $saisonsListStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($saisons) === 0) {
    $year = (int) date('Y');
    $defaultSeasonName = 'Saison ' . $year . '-' . ($year + 1);
    $stmt = $pdo->prepare(
        'INSERT INTO saisons (user_id, equipe_id, nom, date_debut, date_fin) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $selectedTeamId,
        $defaultSeasonName,
        date('Y-07-01'),
        date('Y-m-d', strtotime('+1 year', strtotime(date('Y-06-30')))),
    ]);

    $saisonsListStmt->execute([$userId, $selectedTeamId]);
    $saisons = $saisonsListStmt->fetchAll(PDO::FETCH_ASSOC);
}

$status = $_GET['status'] ?? '';
$statusMessages = [
    'saison_ajoutee' => ['success', 'Saison creee avec succes.'],
    'saison_modifiee' => ['success', 'Saison modifiee avec succes.'],
    'saison_supprimee' => ['success', 'Saison supprimee avec succes.'],
    'saison_invalide' => ['error', 'Le nom de la saison est obligatoire.'],
    'saison_introuvable' => ['error', 'Saison introuvable.'],
    'saison_non_supprimable' => ['error', 'Impossible de supprimer cette saison (gardez au moins une saison).'],
];
$flash = isset($statusMessages[$status]) ? $statusMessages[$status] : null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="f7070317-bfa5-464f-bf91-24cf10f1ad59" type="text/javascript" async></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des saisons - MasterCoach</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="header">
        <h1>Gestion des saisons</h1>
        <a href="equipe.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>" class="home-btn">Retour equipe</a>
    </div>

    <?php if ($flash !== null): ?>
        <div class="team-flash <?= htmlspecialchars($flash[0]) ?>"><?= htmlspecialchars($flash[1]) ?></div>
    <?php endif; ?>

    <div class="team-layout">
        <section class="team-panel team-panel-wide">
            <h2 class="section-title">Contexte</h2>
            <form method="GET" class="add-form team-form">
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
            </form>
        </section>

        <section class="team-panel">
            <h2 class="section-title">Ajouter une saison</h2>
            <form method="POST" class="add-form team-form">
                <input type="hidden" name="action" value="ajouter_saison">
                <input type="hidden" name="equipe_id" value="<?= $selectedTeamId ?>">

                <div>
                    <label for="nom-saison">Nom</label>
                    <input type="text" id="nom-saison" name="nom_saison" placeholder="Ex. Saison 2026-2027" required>
                </div>
                <div class="team-grid-two">
                    <div>
                        <label for="date-debut-saison">Debut</label>
                        <input type="date" id="date-debut-saison" name="date_debut">
                    </div>
                    <div>
                        <label for="date-fin-saison">Fin</label>
                        <input type="date" id="date-fin-saison" name="date_fin">
                    </div>
                </div>
                <button type="submit" class="btn btn-add">Creer</button>
            </form>
        </section>

        <section class="team-panel team-panel-wide">
            <h2 class="section-title">Saisons existantes</h2>
            <div class="team-feed">
                <?php foreach ($saisons as $saison): ?>
                    <article class="team-feed-card">
                        <strong><?= htmlspecialchars($saison['nom']) ?></strong>
                        <span><?= htmlspecialchars((string) ($saison['date_debut'] ?? '')) ?> <?= !empty($saison['date_fin']) ? ' -> ' . htmlspecialchars((string) $saison['date_fin']) : '' ?></span>
                        <div class="form-buttons" style="justify-content: flex-start;">
                            <a href="equipe.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= (int) $saison['id'] ?>" class="btn btn-edit">Activer</a>
                        </div>
                        <form method="POST" class="add-form team-form" style="margin-top: 12px;">
                            <input type="hidden" name="action" value="modifier_saison">
                            <input type="hidden" name="equipe_id" value="<?= $selectedTeamId ?>">
                            <input type="hidden" name="saison_id" value="<?= (int) $saison['id'] ?>">
                            <div>
                                <label for="nom-<?= (int) $saison['id'] ?>">Renommer</label>
                                <input type="text" id="nom-<?= (int) $saison['id'] ?>" name="nom_saison" value="<?= htmlspecialchars($saison['nom']) ?>" required>
                            </div>
                            <div class="team-grid-two">
                                <div>
                                    <label for="debut-<?= (int) $saison['id'] ?>">Debut</label>
                                    <input type="date" id="debut-<?= (int) $saison['id'] ?>" name="date_debut" value="<?= htmlspecialchars((string) ($saison['date_debut'] ?? '')) ?>">
                                </div>
                                <div>
                                    <label for="fin-<?= (int) $saison['id'] ?>">Fin</label>
                                    <input type="date" id="fin-<?= (int) $saison['id'] ?>" name="date_fin" value="<?= htmlspecialchars((string) ($saison['date_fin'] ?? '')) ?>">
                                </div>
                            </div>
                            <div class="form-buttons" style="justify-content: flex-start;">
                                <button type="submit" class="btn btn-edit">Enregistrer</button>
                            </div>
                        </form>
                        <form method="POST" class="inline-action-form" onsubmit="return confirm('Supprimer cette saison et ses statistiques ?');">
                            <input type="hidden" name="action" value="supprimer_saison">
                            <input type="hidden" name="equipe_id" value="<?= $selectedTeamId ?>">
                            <input type="hidden" name="saison_id" value="<?= (int) $saison['id'] ?>">
                            <button type="submit" class="btn btn-delete" <?= count($saisons) <= 1 ? 'disabled' : '' ?>>Supprimer</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
</body>
</html>
