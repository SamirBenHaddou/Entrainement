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

try {
    $pdo->exec('ALTER TABLE joueurs ADD COLUMN equipe_id INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueurs MODIFY poste VARCHAR(255) DEFAULT NULL');
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

$selectedSeasonId = isset($_GET['saison_id']) ? (int) $_GET['saison_id'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $teamId = (int) ($_POST['equipe_id'] ?? $selectedTeamId);
    if (!in_array($teamId, $knownTeamIds, true)) {
        $teamId = $selectedTeamId;
    }

    $nom = trim($_POST['nom'] ?? '');
    $pointsForts = trim($_POST['points_forts'] ?? '');
    $pointsFaibles = trim($_POST['points_faibles'] ?? '');
    $commentaireJoueur = trim($_POST['commentaire_joueur'] ?? '');
    $poste = normalize_positions($_POST['postes'] ?? [], $positionOptions);

    if ($nom === '') {
        header('Location: joueur_ajout.php?equipe_id=' . $teamId . '&status=joueur_invalide');
        exit;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO joueurs (user_id, equipe_id, nom, poste, points_forts, points_faibles, commentaire_joueur)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $userId,
        $teamId,
        $nom,
        $poste,
        $pointsForts !== '' ? $pointsForts : null,
        $pointsFaibles !== '' ? $pointsFaibles : null,
        $commentaireJoueur !== '' ? $commentaireJoueur : null,
    ]);

    header('Location: equipe.php?equipe_id=' . $teamId . '&saison_id=' . $selectedSeasonId . '&status=joueur_ajoute');
    exit;
}

$status = $_GET['status'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="f7070317-bfa5-464f-bf91-24cf10f1ad59" type="text/javascript" async></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un joueur - MasterCoach</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="header">
        <h1>Ajouter un joueur</h1>
        <a href="equipe.php?equipe_id=<?= $selectedTeamId ?>&saison_id=<?= $selectedSeasonId ?>" class="home-btn">Retour equipe</a>
    </div>

    <?php if ($status === 'joueur_invalide'): ?>
        <div class="team-flash error">Le nom du joueur est obligatoire.</div>
    <?php endif; ?>

    <div class="team-layout">
        <section class="team-panel team-panel-wide">
            <h2 class="section-title">Nouveau joueur</h2>
            <form method="POST" class="add-form team-form">
                <input type="hidden" name="equipe_id" value="<?= $selectedTeamId ?>">

                <div>
                    <label for="equipe-id">Equipe</label>
                    <select id="equipe-id" onchange="window.location.href='joueur_ajout.php?equipe_id=' + this.value + '&saison_id=<?= $selectedSeasonId ?>';">
                        <?php foreach ($equipes as $equipe): ?>
                            <option value="<?= (int) $equipe['id'] ?>" <?= (int) $equipe['id'] === $selectedTeamId ? 'selected' : '' ?>>
                                <?= htmlspecialchars($equipe['nom']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="nom">Nom du joueur</label>
                    <input type="text" id="nom" name="nom" placeholder="Ex. Karim Benali" required>
                </div>

                <div>
                    <span class="team-field-label">Postes</span>
                    <div class="position-selector-grid">
                        <?php foreach ($positionOptions as $positionOption): ?>
                            <label class="position-option">
                                <input type="checkbox" name="postes[]" value="<?= htmlspecialchars($positionOption) ?>">
                                <span><?= htmlspecialchars($positionOption) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div>
                    <label for="points-forts-ajout">Points forts</label>
                    <textarea id="points-forts-ajout" name="points_forts" placeholder="Ex. Vitesse, relance, duel..." ></textarea>
                </div>
                <div>
                    <label for="points-faibles-ajout">Points faibles</label>
                    <textarea id="points-faibles-ajout" name="points_faibles" placeholder="Axes d'amelioration" ></textarea>
                </div>
                <div>
                    <label for="commentaire-joueur-ajout">Commentaire coach</label>
                    <textarea id="commentaire-joueur-ajout" name="commentaire_joueur" placeholder="Observation generale" ></textarea>
                </div>

                <button type="submit" class="btn btn-add">Ajouter le joueur</button>
            </form>
        </section>
    </div>
</body>
</html>
