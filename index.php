<?php
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
  <title>Connexion / Inscription - Entrainement</title>
  <link rel="stylesheet" href="css/style.css" />
  <style>
    /* Simple styles pour switch form */
    .switch-link {
      color: #007bff;
      cursor: pointer;
      text-decoration: underline;
    }
    .hidden {
      display: none;
    }
  </style>
</head>
<body>

  <div class="container" style="max-width: 350px; margin: 4em auto; padding: 2em; background: white; border: 1px solid #ccc; border-radius: 6px;">
    <h2 id="form-title">Connexion</h2>

    <?php if ($error): ?>
      <p style="color:red;"><?= htmlspecialchars($error) ?></p>
    <?php elseif ($success): ?>
      <p style="color:green;"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <form id="login-form" method="POST" <?= $success ? 'class="hidden"' : '' ?>>
      <input type="hidden" name="action" value="login" />
      <label>Email<br><input type="email" name="email" required></label><br><br>
      <label>Mot de passe<br><input type="password" name="password" required></label><br><br>
      <button type="submit" style="width: 100%;">Se connecter</button>
    </form>

    <form id="register-form" method="POST" class="hidden">
      <input type="hidden" name="action" value="register" />
      <label>Email<br><input type="email" name="email" required></label><br><br>
      <label>Mot de passe<br><input type="password" name="password" required></label><br><br>
      <label>Confirmer mot de passe<br><input type="password" name="password_confirm" required></label><br><br>
      <button type="submit" style="width: 100%;">Créer un compte</button>
    </form>

    <p style="margin-top: 1em;">
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
      formTitle.textContent = 'Inscription';
    });

    switchToLogin.addEventListener('click', () => {
      registerForm.classList.add('hidden');
      loginForm.classList.remove('hidden');
      switchToLogin.classList.add('hidden');
      switchToRegister.classList.remove('hidden');
      formTitle.textContent = 'Connexion';
    });
  </script>

</body>
</html>
