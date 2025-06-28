<?php
// === 1. index.php ===
session_start();

$pdo = new PDO('mysql:host=localhost;dbname=entrainement', 'root', 'BeagroupSamir!');

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
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Connexion / Inscription - Entrainement</title>
  <link rel="stylesheet" href="css/style.css" />
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0;
      padding: 20px;
    }

    .container {
      max-width: 400px;
      width: 100%;
      padding: 2em;
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
      color: white;
    }

    h2 {
      text-align: center;
      margin-bottom: 1.5em;
      font-size: 2rem;
    }

    label {
      display: block;
      margin-bottom: 0.5em;
      font-weight: 500;
    }

    input[type="email"], input[type="password"] {
      width: 100%;
      padding: 12px 15px;
      margin-bottom: 1em;
      border: none;
      border-radius: 25px;
      background: rgba(255, 255, 255, 0.2);
      color: white;
      font-size: 16px;
    }

    input[type="email"]::placeholder, input[type="password"]::placeholder {
      color: rgba(255, 255, 255, 0.7);
    }

    button[type="submit"] {
      width: 100%;
      padding: 12px;
      background: #ff6b6b;
      color: white;
      border: none;
      border-radius: 25px;
      font-size: 16px;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-bottom: 1em;
    }

    button[type="submit"]:hover {
      background: #ff5252;
      transform: translateY(-2px);
    }

    .switch-link {
      color: #ffd93d;
      cursor: pointer;
      text-decoration: underline;
      transition: color 0.3s ease;
    }

    .switch-link:hover {
      color: #ffed4e;
    }

    .hidden {
      display: none;
    }

    .error {
      color: #ff6b6b;
      background: rgba(255, 107, 107, 0.1);
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 1em;
      text-align: center;
    }

    .success {
      color: #4ecdc4;
      background: rgba(78, 205, 196, 0.1);
      padding: 10px;
      border-radius: 5px;
      margin-bottom: 1em;
      text-align: center;
    }
  </style>
</head>
<body>

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