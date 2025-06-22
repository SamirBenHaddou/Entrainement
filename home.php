<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$email = $_SESSION['email'];
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <title>Accueil - Entrainement</title>
  <link rel="stylesheet" href="css/style.css" />
</head>
<body>
  <header class="header">
    <div class="welcome">Bienvenue, <strong><?= htmlspecialchars($email) ?></strong></div>
    <form action="logout.php" method="POST" class="logout-form">
      <button type="submit" class="btn-logout">Déconnexion</button>
    </form>
  </header>

  <main>
    <div id="app"></div>
  </main>

  <script src="js/app.js"></script>
</body>
</html>
