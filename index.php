<?php
// === 1. index.php ===

$configs = require(__DIR__ . "/../config/config.php");
$db = $configs['mastercoach'];
session_start();

$pdo = new PDO("mysql:host={$db['db_host']};dbname={$db['db_name']}", $db['db_user'], $db['db_pass']);

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'login') {
            // Connexion
            $stmt = $pdo->prepare('SELECT * FROM utilisateurs WHERE email = ?');
            $stmt->execute([$_POST['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user && password_verify($_POST['password'], $user['password'])) {
                // Après avoir vérifié le mot de passe et trouvé l'utilisateur :
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['email'] = $user['email'];
                header('Location: home.php');
                exit;
            } else {
                $error = 'Email ou mot de passe incorrect';
            }
        } elseif ($_POST['action'] === 'register') {
            // Inscription
            $email = $_POST['email'];
            $password = $_POST['password'];
            $password_confirm = $_POST['password_confirm'];

            if ($password !== $password_confirm) {
                $error = 'Les mots de passe ne correspondent pas';
            } else {
                // Vérifier si email déjà utilisé
                $stmt = $pdo->prepare('SELECT id FROM utilisateurs WHERE email = ?');
                $stmt->execute([$email]);
                if ($stmt->fetch()) {
                    $error = 'Cet email est déjà utilisé';
                } else {
                    // Insérer utilisateur
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('INSERT INTO utilisateurs (email, password) VALUES (?, ?)');
                    if ($stmt->execute([$email, $hash])) {
                        $success = 'Compte créé avec succès, vous pouvez maintenant vous connecter';
                    } else {
                        $error = 'Erreur lors de la création du compte';
                    }
                }
            }
        }
    }
}

if (isset($_SESSION['user_id'])) {
    header('Location: home.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <script id="Cookiebot" src="https://consent.cookiebot.com/uc.js" data-cbid="f7070317-bfa5-464f-bf91-24cf10f1ad59" type="text/javascript" async></script>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion / Inscription - Entrainement</title>
  <link rel="stylesheet" href="css/style.css" />
    <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-ZK321HQVXR"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-ZK321HQVXR');
</script>
  </head>

<body>

  <div class="header">
    <h1>Connexion / Inscription</h1>
    <a href="home.php" class="home-btn">🏠 Accueil</a>
  </div>

  <div class="container">
    <h2 id="form-title">🏃‍♂️ Connexion</h2>

    <?php if ($error): ?>
      <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php elseif ($success): ?>
      <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form id="login-form" method="POST" <?= $success ? 'class="hidden"' : '' ?>>
      <input type="hidden" name="action" value="login" />
      <label>Email</label>
      <input type="email" name="email" placeholder="votre@email.com" required>
      <label>Mot de passe</label>
      <input type="password" name="password" placeholder="••••••••" required>
      <button type="submit">Se connecter</button>
    </form>

    <form id="register-form" method="POST" class="hidden">
      <input type="hidden" name="action" value="register" />
      <label>Email</label>
      <input type="email" name="email" placeholder="votre@email.com" required>
      <label>Mot de passe</label>
      <input type="password" name="password" placeholder="••••••••" required>
      <label>Confirmer mot de passe</label>
      <input type="password" name="password_confirm" placeholder="••••••••" required>
      <button type="submit">Créer un compte</button>
    </form>

    <p style="text-align: center; margin-top: 1em;">
      <span id="switch-to-register" class="switch-link">Pas encore de compte ? Inscrivez-vous</span>
      <span id="switch-to-login" class="switch-link hidden">Déjà un compte ? Connectez-vous</span>
    </p>
  </div>

  <script>
    const loginForm = document.getElementById('login-form');
    const registerForm = document.getElementById('register-form');
    const switchToRegister = document.getElementById('switch-to-register');
    const switchToLogin = document.getElementById('switch-to-login');
    const formTitle = document.getElementById('form-title');

    switchToRegister.addEventListener('click', () => {
      loginForm.classList.add('hidden');
      registerForm.classList.remove('hidden');
      switchToRegister.classList.add('hidden');
      switchToLogin.classList.remove('hidden');
      formTitle.textContent = '🏃‍♂️ Inscription';
    });

    switchToLogin.addEventListener('click', () => {
      registerForm.classList.add('hidden');
      loginForm.classList.remove('hidden');
      switchToLogin.classList.add('hidden');
      switchToRegister.classList.remove('hidden');
      formTitle.textContent = '🏃‍♂️ Connexion';
    });
  </script>

</body>
</html>