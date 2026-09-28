<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
// === 4. seances.php (Interface principale améliorée) ===
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$configs = require(__DIR__ . "/../config/config.php");
$db = $configs['mastercoach'];

try {
    $pdo = new PDO("mysql:host={$db['db_host']};dbname={$db['db_name']};charset=utf8mb4", $db['db_user'], $db['db_pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    die('Erreur base de données : ' . $e->getMessage());
}

try {
    $pdo->exec('ALTER TABLE exercices ADD COLUMN favori TINYINT(1) NOT NULL DEFAULT 0');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE exercices ADD COLUMN profils_cibles TEXT DEFAULT NULL');
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

$user_id = $_SESSION['user_id'];
$date_seance = $_GET['date'] ?? date('Y-m-d');

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS equipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        nom VARCHAR(120) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_equipes_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

$equipesStmt = $pdo->prepare('SELECT id, nom FROM equipes WHERE user_id = ? ORDER BY nom ASC');
$equipesStmt->execute([$user_id]);
$equipes = $equipesStmt->fetchAll(PDO::FETCH_ASSOC);

if (count($equipes) === 0) {
    $insertTeamStmt = $pdo->prepare('INSERT INTO equipes (user_id, nom) VALUES (?, ?)');
    $insertTeamStmt->execute([$user_id, 'Equipe principale']);

    $equipesStmt->execute([$user_id]);
    $equipes = $equipesStmt->fetchAll(PDO::FETCH_ASSOC);
}

$selected_team_id = isset($_GET['equipe_id']) ? (int) $_GET['equipe_id'] : 0;
$teamIds = array_map(static function (array $team): int {
    return (int) $team['id'];
}, $equipes);

if ($selected_team_id <= 0 || !in_array($selected_team_id, $teamIds, true)) {
    $selected_team_id = (int) ($equipes[0]['id'] ?? 0);
}

$selected_team_name = 'Equipe';
foreach ($equipes as $team) {
    if ((int) $team['id'] === $selected_team_id) {
        $selected_team_name = (string) $team['nom'];
        break;
    }
}

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS joueurs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        nom VARCHAR(120) NOT NULL,
        poste VARCHAR(80) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_joueurs_user (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'
);

try {
    $pdo->exec('ALTER TABLE joueurs MODIFY poste VARCHAR(255) DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueurs ADD COLUMN equipe_id INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE joueurs ADD INDEX idx_joueurs_equipe (equipe_id)');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE seances ADD COLUMN equipe_id INT DEFAULT NULL');
} catch (Exception $e) {
}

try {
    $pdo->exec('ALTER TABLE seances ADD INDEX idx_seances_user_date_team (user_id, date_seance, equipe_id)');
} catch (Exception $e) {
}

try {
    $uniqueIndexesStmt = $pdo->prepare(
        'SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS columns_list
         FROM information_schema.STATISTICS
         WHERE TABLE_SCHEMA = ?
           AND TABLE_NAME = "seances"
           AND NON_UNIQUE = 0
         GROUP BY INDEX_NAME'
    );
    $uniqueIndexesStmt->execute([$db['db_name']]);
    $uniqueIndexes = $uniqueIndexesStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($uniqueIndexes as $uniqueIndex) {
        $indexName = (string) ($uniqueIndex['INDEX_NAME'] ?? '');
        if ($indexName === '' || strtoupper($indexName) === 'PRIMARY') {
            continue;
        }

        $columns = array_map('trim', explode(',', (string) ($uniqueIndex['columns_list'] ?? '')));
        $columns = array_map(static function (string $value): string {
            return strtolower($value);
        }, $columns);

        // Supprime les contraintes uniques historiques qui incluent date_seance
        // sans equipe_id, car elles empêchent plusieurs séances le même jour.
        if (in_array('date_seance', $columns, true) && !in_array('equipe_id', $columns, true)) {
            $safeIndexName = str_replace('`', '``', $indexName);
            $pdo->exec("ALTER TABLE seances DROP INDEX `{$safeIndexName}`");
        }
    }
} catch (Exception $e) {
}

if (count($equipes) === 1) {
    $stmt = $pdo->prepare('UPDATE seances SET equipe_id = ? WHERE user_id = ? AND (equipe_id IS NULL OR equipe_id = 0)');
    $stmt->execute([(int) $equipes[0]['id'], (int) $user_id]);
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

function getOrCreateSeanceId(PDO $pdo, string $date, int $userId, int $teamId): int
{
    $stmt = $pdo->prepare('SELECT id FROM seances WHERE date_seance = ? AND user_id = ? AND equipe_id = ?');
    $stmt->execute([$date, $userId, $teamId]);
    $seance = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($seance) {
        return (int) $seance['id'];
    }

    $stmt = $pdo->prepare('INSERT INTO seances (date_seance, user_id, equipe_id) VALUES (?, ?, ?)');
    $stmt->execute([$date, $userId, $teamId]);

    return (int) $pdo->lastInsertId();
}

function resolveTeamId($rawValue, array $allowedTeamIds, int $fallbackTeamId): int
{
    $teamId = (int) $rawValue;
    if ($teamId > 0 && in_array($teamId, $allowedTeamIds, true)) {
        return $teamId;
    }

    return $fallbackTeamId;
}

function getEnvValue(string $key): ?string
{
    $value = getenv($key);
    if ($value !== false && trim((string) $value) !== '') {
        return trim((string) $value);
    }

    if (isset($_ENV[$key]) && trim((string) $_ENV[$key]) !== '') {
        return trim((string) $_ENV[$key]);
    }

    return null;
}

function parseJsonObjectFromText(string $text): ?array
{
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    if (preg_match('/\{[\s\S]*\}/', $text, $matches) === 1) {
        $decoded = json_decode($matches[0], true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function normalizeCategory(string $value): string
{
    $value = trim(mb_strtolower($value, 'UTF-8'));
    $value = str_replace(['é', 'è', 'ê', 'ë'], 'e', $value);
    $value = str_replace(['à', 'â'], 'a', $value);
    $value = str_replace(['î', 'ï'], 'i', $value);
    $value = str_replace(['ô', 'ö'], 'o', $value);
    $value = str_replace(['ù', 'û', 'ü'], 'u', $value);

    return $value;
}

function shufflePreservingKeys(array $items): array
{
    $copy = array_values($items);
    if (count($copy) > 1) {
        shuffle($copy);
    }

    return $copy;
}

function getAiClientConfig(): array
{
    $chatGptApiKey = getEnvValue('CHATGPT_API_KEY') ?? getEnvValue('OPENAI_API_KEY');
    if ($chatGptApiKey !== null) {
        return [
            'provider' => 'chatgpt',
            'api_key' => $chatGptApiKey,
            'model' => getEnvValue('CHATGPT_MODEL') ?? getEnvValue('OPENAI_MODEL') ?? 'gpt-4.1-mini',
            'api_url' => getEnvValue('CHATGPT_API_URL') ?? getEnvValue('OPENAI_API_URL') ?? 'https://api.openai.com/v1/chat/completions',
        ];
    }

    $githubModelsApiKey = getEnvValue('GITHUB_MODELS_API_KEY');
    if ($githubModelsApiKey !== null) {
        return [
            'provider' => 'github_models',
            'api_key' => $githubModelsApiKey,
            'model' => getEnvValue('GITHUB_MODELS_MODEL') ?? 'openai/gpt-4.1-mini',
            'api_url' => getEnvValue('GITHUB_MODELS_API_URL') ?? 'https://models.inference.ai.azure.com/chat/completions',
        ];
    }

    $copilotApiKey = getEnvValue('COPILOT_API_KEY');
    if ($copilotApiKey !== null) {
        return [
            'provider' => 'copilot',
            'api_key' => $copilotApiKey,
            'model' => getEnvValue('COPILOT_MODEL') ?? 'gpt-4.1',
            'api_url' => getEnvValue('COPILOT_API_URL') ?? 'https://api.githubcopilot.com/chat/completions',
        ];
    }
    return [
        'provider' => 'none',
        'api_key' => null,
        'model' => null,
        'api_url' => null,
    ];
}

function callSessionPlannerAI(array $constraints, array $candidates): array
{
    $aiConfig = getAiClientConfig();
    if (!is_string($aiConfig['api_key'] ?? null) || trim($aiConfig['api_key']) === '') {
        return [
            'success' => false,
            'message' => 'Configuration manquante: definir CHATGPT_API_KEY (ou OPENAI_API_KEY / GITHUB_MODELS_API_KEY / COPILOT_API_KEY) sur le serveur.',
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'message' => 'cURL n\'est pas disponible sur le serveur.',
        ];
    }

    $model = (string) $aiConfig['model'];
    $apiUrl = (string) $aiConfig['api_url'];
    $apiKey = (string) $aiConfig['api_key'];

    $systemPrompt = 'Tu es un assistant de planification d\'entrainement. Tu dois proposer une seance uniquement avec les IDs fournis, sans jamais inventer d\'exercice. Reponds uniquement en JSON.';
    $userPrompt = [
        'constraints' => $constraints,
        'rules' => [
            'Utiliser uniquement les ids disponibles dans candidates.',
            'Respecter au mieux la duree cible (sans la depasser de plus de 10%).',
            'Retourner 3 a 12 exercices selon le contexte.',
            'Diversifier les categories si possible.',
            'Format JSON strict attendu: {"exercise_ids":[1,2],"notes":"..."}',
        ],
        'candidates' => $candidates,
    ];

    $payload = [
        'model' => $model,
        'temperature' => 0.3,
        'messages' => [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => json_encode($userPrompt, JSON_UNESCAPED_UNICODE)],
        ],
    ];

    $ch = curl_init($apiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 45,
    ]);

    $rawResponse = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($rawResponse === false || $httpCode >= 400) {
        return [
            'success' => false,
            'message' => 'Erreur IA ' . strtoupper((string) ($aiConfig['provider'] ?? 'api')) . ' (HTTP ' . $httpCode . '): ' . ($curlError !== '' ? $curlError : 'requete echouee'),
        ];
    }

    $decoded = json_decode($rawResponse, true);
    $content = $decoded['choices'][0]['message']['content'] ?? '';
    if (!is_string($content) || trim($content) === '') {
        return [
            'success' => false,
            'message' => 'Reponse IA vide.',
        ];
    }

    $json = parseJsonObjectFromText($content);
    if (!is_array($json) || !isset($json['exercise_ids']) || !is_array($json['exercise_ids'])) {
        return [
            'success' => false,
            'message' => 'Format IA invalide.',
        ];
    }

    $ids = array_values(array_unique(array_filter(array_map('intval', $json['exercise_ids']), static function ($value) {
        return $value > 0;
    })));

    if (count($ids) === 0) {
        return [
            'success' => false,
            'message' => 'Aucun exercice propose.',
        ];
    }

    return [
        'success' => true,
        'exercise_ids' => $ids,
        'notes' => isset($json['notes']) ? trim((string) $json['notes']) : '',
    ];
}

// API endpoints pour AJAX
if (isset($_GET['api'])) {
    ini_set('display_errors', '0');
    header('Content-Type: application/json');
    
    if ($_GET['api'] === 'exercices') {
        $stmt = $pdo->query('SELECT *, COALESCE(favori, 0) AS favori FROM exercices ORDER BY favori DESC, categorie, nom');
        $exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($exercices);
        exit;
    }
    
    if ($_GET['api'] === 'seance' && isset($_GET['date'])) {
        $requestTeamId = resolveTeamId($_GET['equipe_id'] ?? 0, $teamIds, $selected_team_id);
        $stmt = $pdo->prepare('
            SELECT e.*, se.id as seance_exercice_id, se.ordre
            FROM exercices e
            JOIN seance_exercices se ON e.id = se.exercice_id
            JOIN seances s ON se.seance_id = s.id
            WHERE s.date_seance = ? AND s.user_id = ? AND s.equipe_id = ?
            ORDER BY se.ordre ASC
        ');
        $stmt->execute([$_GET['date'], $user_id, $requestTeamId]);
        $exercices = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($exercices);
        exit;
    }

    if ($_GET['api'] === 'joueurs') {
        $requestTeamId = resolveTeamId($_GET['equipe_id'] ?? 0, $teamIds, $selected_team_id);
        $stmt = $pdo->prepare('SELECT id, nom, poste FROM joueurs WHERE user_id = ? AND equipe_id = ? ORDER BY nom ASC');
        $stmt->execute([$user_id, $requestTeamId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }

    if ($_GET['api'] === 'joueurs_seance' && isset($_GET['date'])) {
        $requestTeamId = resolveTeamId($_GET['equipe_id'] ?? 0, $teamIds, $selected_team_id);
        $stmt = $pdo->prepare(
            'SELECT j.id, j.nom, j.poste
             FROM joueurs j
             JOIN seance_joueurs sj ON sj.joueur_id = j.id
             JOIN seances s ON s.id = sj.seance_id
             WHERE s.date_seance = ? AND s.user_id = ? AND s.equipe_id = ?
             ORDER BY j.nom ASC'
        );
        $stmt->execute([$_GET['date'], $user_id, $requestTeamId]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        exit;
    }
}

// Gestion des actions POST via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    ini_set('display_errors', '0');
    header('Content-Type: application/json');

    try {
    if ($_POST['action'] === 'ajouter_exercice') {
        $exercice_id = intval($_POST['exercice_id']);
        $date = $_POST['date'];
        $requestTeamId = resolveTeamId($_POST['equipe_id'] ?? 0, $teamIds, $selected_team_id);

        $seance_id = getOrCreateSeanceId($pdo, $date, (int) $user_id, $requestTeamId);

        // Vérifier si l'exercice n'est pas déjà ajouté
        $stmt = $pdo->prepare('SELECT 1 FROM seance_exercices WHERE seance_id = ? AND exercice_id = ?');
        $stmt->execute([$seance_id, $exercice_id]);

        if (!$stmt->fetch()) {
            // Trouver le prochain ordre
            $stmt = $pdo->prepare('SELECT MAX(ordre) AS max_ordre FROM seance_exercices WHERE seance_id = ?');
            $stmt->execute([$seance_id]);
            $maxOrdre = $stmt->fetchColumn();
            $ordre = $maxOrdre !== false ? intval($maxOrdre) + 1 : 1;

            $stmt = $pdo->prepare('INSERT INTO seance_exercices (seance_id, exercice_id, ordre) VALUES (?, ?, ?)');
            $stmt->execute([$seance_id, $exercice_id, $ordre]);
            echo json_encode(['success' => true]);
            exit;
        } else {
            echo json_encode(['success' => false, 'message' => 'Exercice déjà ajouté']);
            exit;
        }
    }
    
    if ($_POST['action'] === 'supprimer_exercice') {
        $exercice_id = intval($_POST['exercice_id']);
        $date = $_POST['date'];
        $requestTeamId = resolveTeamId($_POST['equipe_id'] ?? 0, $teamIds, $selected_team_id);
        
        $stmt = $pdo->prepare('
            DELETE se FROM seance_exercices se
            JOIN seances s ON se.seance_id = s.id
            WHERE s.date_seance = ? AND s.user_id = ? AND s.equipe_id = ? AND se.exercice_id = ?
        ');
        $stmt->execute([$date, $user_id, $requestTeamId, $exercice_id]);
        
        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'reordonner_exercices') {
        $date = $_POST['date'] ?? '';
        $orderedIds = $_POST['ordered_ids'] ?? [];
        $requestTeamId = resolveTeamId($_POST['equipe_id'] ?? 0, $teamIds, $selected_team_id);

        if ($date === '' || !is_array($orderedIds) || count($orderedIds) === 0) {
            echo json_encode(['success' => false, 'message' => 'Donnees invalides']);
            exit;
        }

        $seanceId = getOrCreateSeanceId($pdo, $date, (int) $user_id, $requestTeamId);
        $orderedIds = array_values(array_unique(array_filter(array_map('intval', $orderedIds), static function ($value) {
            return $value > 0;
        })));

        if (count($orderedIds) === 0) {
            echo json_encode(['success' => false, 'message' => 'Ordre invalide']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($orderedIds), '?'));
        $params = array_merge([$seanceId], $orderedIds);
        $stmt = $pdo->prepare("SELECT id FROM seance_exercices WHERE seance_id = ? AND id IN ($placeholders)");
        $stmt->execute($params);
        $validIds = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        if (count($validIds) !== count($orderedIds)) {
            echo json_encode(['success' => false, 'message' => 'Exercices invalides']);
            exit;
        }

        $pdo->beginTransaction();

        try {
            $updateStmt = $pdo->prepare('UPDATE seance_exercices SET ordre = ? WHERE id = ? AND seance_id = ?');
            foreach ($orderedIds as $index => $orderedId) {
                $updateStmt->execute([$index + 1, $orderedId, $seanceId]);
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erreur lors du reordonnancement']);
            exit;
        }
    }

    if ($_POST['action'] === 'proposer_seance_ia') {
        $date = $_POST['date'] ?? '';
        if ($date === '') {
            echo json_encode(['success' => false, 'message' => 'Date invalide']);
            exit;
        }

        $parseCount = static function (string $field, int $default): int {
            $value = isset($_POST[$field]) ? (int) $_POST[$field] : $default;
            return max(0, min(12, $value));
        };

        $plan = [
            'echauffement' => $parseCount('count_echauffement', 3),
            'vitesse' => $parseCount('count_vitesse', 2),
            'endurance' => $parseCount('count_endurance', 2),
            'agilite' => $parseCount('count_agilite', 2),
        ];

        $totalRequested = array_sum($plan);
        if ($totalRequested <= 0) {
            echo json_encode(['success' => false, 'message' => 'Selection invalide: choisir au moins un exercice.']);
            exit;
        }

        $formatSouhaite = trim((string) ($_POST['format_souhaite'] ?? 'individuel'));
        if (!in_array($formatSouhaite, ['individuel', 'groupe'], true)) {
            $formatSouhaite = 'individuel';
        }

        $stmt = $pdo->query('SELECT id, nom, categorie, description, duree, materiel, COALESCE(favori, 0) AS favori, COALESCE(format_entrainement, "mixte") AS format_entrainement FROM exercices ORDER BY categorie, nom');
        $allExercises = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $pools = [
            'echauffement' => [],
            'vitesse' => [],
            'endurance' => [],
            'agilite' => [],
        ];

        foreach ($allExercises as $exercise) {
            $normalized = normalizeCategory((string) ($exercise['categorie'] ?? ''));

            if (strpos($normalized, 'echauff') !== false) {
                $pools['echauffement'][] = $exercise;
                continue;
            }

            if (strpos($normalized, 'vitesse') !== false) {
                $pools['vitesse'][] = $exercise;
                continue;
            }

            if (strpos($normalized, 'endurance') !== false) {
                $pools['endurance'][] = $exercise;
                continue;
            }

            if (strpos($normalized, 'agilite') !== false) {
                $pools['agilite'][] = $exercise;
                continue;
            }
        }

        $selected = [];
        $selectedIds = [];
        $shortages = [];
        $categoryOrder = [
            'echauffement' => 1,
            'vitesse' => 2,
            'endurance' => 3,
            'agilite' => 4,
        ];

        foreach ($plan as $categoryKey => $targetCount) {
            if ($targetCount <= 0) {
                continue;
            }

            $available = shufflePreservingKeys($pools[$categoryKey]);
            $taken = 0;

            foreach ($available as $exercise) {
                $exerciseId = (int) $exercise['id'];
                $exerciseFormat = trim((string) ($exercise['format_entrainement'] ?? 'mixte'));

                if ($exerciseFormat !== $formatSouhaite && $exerciseFormat !== 'mixte') {
                    continue;
                }

                if (isset($selectedIds[$exerciseId])) {
                    continue;
                }

                $selected[] = $exercise;
                $selectedIds[$exerciseId] = true;
                $taken++;

                if ($taken >= $targetCount) {
                    break;
                }
            }

            if ($taken < $targetCount) {
                $shortages[] = $categoryKey . ' (' . $taken . '/' . $targetCount . ')';
            }
        }

        // Garantit l'ordre des types dans la proposition finale.
        $selectedWithIndex = [];
        foreach ($selected as $index => $exercise) {
            $selectedWithIndex[] = [
                'index' => $index,
                'exercise' => $exercise,
                'rank' => $categoryOrder['agilite'] + 1,
            ];
        }

        foreach ($selectedWithIndex as &$row) {
            $normalized = normalizeCategory((string) ($row['exercise']['categorie'] ?? ''));
            foreach ($categoryOrder as $key => $rank) {
                if (strpos($normalized, $key === 'echauffement' ? 'echauff' : $key) !== false) {
                    $row['rank'] = $rank;
                    break;
                }
            }
        }
        unset($row);

        usort($selectedWithIndex, static function ($a, $b) {
            if ($a['rank'] === $b['rank']) {
                return $a['index'] <=> $b['index'];
            }
            return $a['rank'] <=> $b['rank'];
        });

        $selected = array_values(array_map(static function ($row) {
            return $row['exercise'];
        }, $selectedWithIndex));

        if (count($selected) === 0) {
            echo json_encode(['success' => false, 'message' => 'Aucun exercice disponible pour les categories demandees.']);
            exit;
        }

        $totalDuration = array_reduce($selected, static function ($sum, $exercise) {
            return $sum + (int) ($exercise['duree'] ?? 0);
        }, 0);

        $notes = sprintf(
            'Tirage automatique (ordre conserve: echauffement > vitesse > endurance > agilite): %d echauffement, %d vitesse, %d endurance, %d agilite. Format: %s.',
            $plan['echauffement'],
            $plan['vitesse'],
            $plan['endurance'],
            $plan['agilite'],
            $formatSouhaite
        );
        if (count($shortages) > 0) {
            $notes .= ' Categories incompletes: ' . implode(', ', $shortages) . '.';
        }

        echo json_encode([
            'success' => true,
            'exercise_ids' => array_values(array_map(static function ($exercise) {
                return (int) $exercise['id'];
            }, $selected)),
            'exercises' => $selected,
            'total_duration' => $totalDuration,
            'notes' => $notes,
        ]);
        exit;
    }

    if ($_POST['action'] === 'inserer_proposition_ia') {
        $date = $_POST['date'] ?? '';
        $exerciseIds = $_POST['exercise_ids'] ?? [];
        $requestTeamId = resolveTeamId($_POST['equipe_id'] ?? 0, $teamIds, $selected_team_id);

        if ($date === '' || !is_array($exerciseIds) || count($exerciseIds) === 0) {
            echo json_encode(['success' => false, 'message' => 'Donnees invalides']);
            exit;
        }

        $seanceId = getOrCreateSeanceId($pdo, $date, (int) $user_id, $requestTeamId);
        $exerciseIds = array_values(array_unique(array_filter(array_map('intval', $exerciseIds), static function ($value) {
            return $value > 0;
        })));

        if (count($exerciseIds) === 0) {
            echo json_encode(['success' => false, 'message' => 'Aucun exercice a inserer']);
            exit;
        }

        $stmt = $pdo->prepare('SELECT exercice_id FROM seance_exercices WHERE seance_id = ?');
        $stmt->execute([$seanceId]);
        $alreadyInSession = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        $alreadySet = array_fill_keys($alreadyInSession, true);

        $stmt = $pdo->prepare('SELECT MAX(ordre) FROM seance_exercices WHERE seance_id = ?');
        $stmt->execute([$seanceId]);
        $nextOrder = ((int) $stmt->fetchColumn()) + 1;

        $inserted = 0;
        $insertStmt = $pdo->prepare('INSERT INTO seance_exercices (seance_id, exercice_id, ordre) VALUES (?, ?, ?)');
        foreach ($exerciseIds as $exerciseId) {
            if (isset($alreadySet[$exerciseId])) {
                continue;
            }

            $insertStmt->execute([$seanceId, $exerciseId, $nextOrder]);
            $nextOrder++;
            $inserted++;
        }

        echo json_encode([
            'success' => true,
            'inserted_count' => $inserted,
            'message' => $inserted > 0
                ? $inserted . ' exercice(s) ajoute(s).'
                : 'Aucun exercice ajoute: la proposition est deja presente pour cette equipe et cette date.',
        ]);
        exit;
    }

    if ($_POST['action'] === 'basculer_favori_exercice') {
        $exercice_id = intval($_POST['exercice_id'] ?? 0);
        if ($exercice_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Exercice invalide']);
            exit;
        }

        $stmt = $pdo->prepare('UPDATE exercices SET favori = CASE WHEN favori = 1 THEN 0 ELSE 1 END WHERE id = ?');
        $stmt->execute([$exercice_id]);

        echo json_encode(['success' => true]);
        exit;
    }

    if ($_POST['action'] === 'enregistrer_joueurs_seance') {
        $date = $_POST['date'] ?? '';
        $joueurs = $_POST['joueurs'] ?? [];
        $requestTeamId = resolveTeamId($_POST['equipe_id'] ?? 0, $teamIds, $selected_team_id);

        if ($date === '' || !is_array($joueurs)) {
            echo json_encode(['success' => false, 'message' => 'Donnees invalides']);
            exit;
        }

        $seanceId = getOrCreateSeanceId($pdo, $date, (int) $user_id, $requestTeamId);
        $joueurIds = array_values(array_unique(array_filter(array_map('intval', $joueurs), static function ($value) {
            return $value > 0;
        })));

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('DELETE sj FROM seance_joueurs sj JOIN seances s ON s.id = sj.seance_id WHERE sj.seance_id = ? AND s.user_id = ?');
            $stmt->execute([$seanceId, $user_id]);

            if (count($joueurIds) > 0) {
                $placeholders = implode(',', array_fill(0, count($joueurIds), '?'));
                $params = array_merge([$user_id, $requestTeamId], $joueurIds);
                $stmt = $pdo->prepare("SELECT id FROM joueurs WHERE user_id = ? AND equipe_id = ? AND id IN ($placeholders)");
                $stmt->execute($params);
                $joueursValides = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

                $insertStmt = $pdo->prepare('INSERT INTO seance_joueurs (seance_id, joueur_id) VALUES (?, ?)');
                foreach ($joueursValides as $joueurId) {
                    $insertStmt->execute([$seanceId, $joueurId]);
                }
            }

            $pdo->commit();
            echo json_encode(['success' => true]);
            exit;
        } catch (Throwable $exception) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erreur lors de la sauvegarde des joueurs']);
            exit;
        }
    }

    echo json_encode(['success' => false, 'message' => 'Action inconnue']);
    exit;
    } catch (Throwable $exception) {
        echo json_encode([
            'success' => false,
            'message' => 'Erreur serveur lors du traitement: ' . $exception->getMessage(),
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="f7070317-bfa5-464f-bf91-24cf10f1ad59" type="text/javascript" async></script>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Planificateur d'Entraînement - <?= htmlspecialchars($date_seance) ?></title>
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>" />
     <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-ZK321HQVXR"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-ZK321HQVXR');
</script>
</head>

<body class="planner-page">
    <div class="header">
  <h1>🏃‍♂️ Planificateur d'Entraînement</h1>
  <a href="home.php" class="home-btn">🏠 Accueil</a>
</div>

    <div class="date-selector centered">
    <label for="session-date">Date de la séance :</label>
    <input type="date" id="session-date" value="<?= htmlspecialchars($date_seance) ?>">
    <label for="session-team">Équipe :</label>
    <select id="session-team">
        <?php foreach ($equipes as $equipe): ?>
            <option value="<?= (int) $equipe['id'] ?>" <?= (int) $equipe['id'] === $selected_team_id ? 'selected' : '' ?>>
                <?= htmlspecialchars($equipe['nom']) ?>
            </option>
        <?php endforeach; ?>
    </select>
</div>

    <div class="planning-toolbar">
        <div class="planning-search-card">
            <h2 class="section-title">Trouver le bon exercice</h2>
            <div class="planning-search-grid">
                <label>
                    Recherche rapide
                    <input type="search" id="exercise-search" placeholder="Nom, description, matériel...">
                </label>
                <label>
                    Format
                    <select id="exercise-training-format-select">
                        <option value="individuel" selected>Individuel + Mixte</option>
                        <option value="groupe">En groupe</option>
                    </select>
                </label>
                <label>
                    Tri
                    <select id="exercise-sort-select">
                        <option value="favoris">Favoris puis nom</option>
                        <option value="nom">Nom A-Z</option>
                        <option value="duree_courte">Durée la plus courte</option>
                        <option value="duree_longue">Durée la plus longue</option>
                    </select>
                </label>
                <label>
                    Durée max
                    <input type="number" id="exercise-duration-max" min="1" placeholder="ex: 15">
                </label>
            </div>
            <div class="planning-search-actions">
                <label class="position-option planning-checkbox">
                    <input type="checkbox" id="exercise-favorites-only">
                    <span>Favoris uniquement</span>
                </label>
                <button type="button" class="btn btn-edit" id="reset-exercise-filters">Réinitialiser les filtres</button>
            </div>
            <div class="planning-ai-box">
                <h3>Proposition automatique de séance</h3>
                <div class="planning-search-grid planning-ai-grid">
                    <label>
                        Échauffement
                        <input type="number" id="auto-count-echauffement" min="0" max="12" value="3">
                    </label>
                    <label>
                        Vitesse
                        <input type="number" id="auto-count-vitesse" min="0" max="12" value="2">
                    </label>
                    <label>
                        Endurance
                        <input type="number" id="auto-count-endurance" min="0" max="12" value="2">
                    </label>
                    <label>
                        Agilité
                        <input type="number" id="auto-count-agilite" min="0" max="12" value="2">
                    </label>
                </div>
                <label>
                    Format de séance
                    <select id="auto-format-souhaite">
                        <option value="individuel" selected>Individuel + Mixte</option>
                        <option value="groupe">Groupe + Mixte</option>
                    </select>
                </label>
                <div class="planning-ai-actions">
                    <button type="button" class="btn btn-add" id="generate-ai-session">Générer une proposition</button>
                    <button type="button" class="btn btn-edit" id="apply-ai-session" disabled>Insérer la proposition</button>
                </div>
                <div id="ai-session-feedback" class="planning-helper-text"></div>
                <ol id="ai-session-preview" class="ai-session-preview"></ol>
            </div>
            <div class="planning-filter-card">
                <div class="planning-filter-header">
                    <h3>Types d'exercice</h3>
                </div>
                <div class="filters planning-quick-filters" id="quick-category-filters">
                    <button class="filter-btn active" data-category="Toutes">Toutes</button>
                    <button class="filter-btn" data-category="Favoris">Favoris</button>
                    <button class="filter-btn" data-category="Echauffement">Echauffement</button>
                    <button class="filter-btn" data-category="Endurance">Endurance</button>
                    <button class="filter-btn" data-category="Vitesse">Vitesse</button>
                    <button class="filter-btn" data-category="Agilité">Agilité</button>
                </div>
            </div>
            <div class="planning-helper-text" id="exercise-results-summary">Chargement du catalogue...</div>
        </div>
    </div>

    <div class="main-container">
        <div class="exercises-section">
            <h2 class="section-title">Exercices Disponibles</h2>
            <div class="exercises-grid" id="exercises-grid">
                <div class="loading">Chargement des exercices...</div>
            </div>
        </div>

        <div class="selected-section" id="selected-section">
            <div class="selected-mobile-header">
                <h2>Séance sélectionnée</h2>
                <button type="button" class="btn btn-edit" id="mobile-selected-close">Fermer</button>
            </div>
            <div class="summary">
  Durée totale estimée: <span id="total-duration">0</span> min
</div>
            <div class="team-assignment" id="team-assignment">
                <h3>Joueurs presents a la seance</h3>
                <div id="session-players" class="session-players-list">
                    <div class="loading">Chargement des joueurs...</div>
                </div>
            </div>
            <ul class="selected-exercises" id="selected-exercises">
                <!-- Les exercices sélectionnés apparaîtront ici -->
            </ul>
            <button id="export-pdf" class="btn btn-add" style="margin:20px auto 0 auto;display:block;">Exporter en PDF</button>
            <div class="selected-mobile-footer">
                <button type="button" class="btn btn-edit" id="mobile-selected-close-bottom">Fermer la séance</button>
            </div>
        </div>
    </div>

    <button type="button" class="mobile-selected-toggle" id="mobile-selected-toggle" aria-controls="selected-section" aria-expanded="false">
        Voir la séance (<span id="mobile-selected-count">0</span>)
    </button>

    <script src="js/app.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
</body>
</html>