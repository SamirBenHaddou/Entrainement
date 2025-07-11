<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}
$email = isset($_SESSION['email']) ? $_SESSION['email'] : 'Utilisateur';
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
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

  <main class="dashboard">
    <a href="seances.php" class="card">
      <span class="icon">🏃‍♂️</span>
      <h3>Planifier une séance</h3>
      <p>Créez et organisez vos séances d'entraînement en sélectionnant des exercices adaptés.</p>
    </a>

    <a href="exercices.php" class="card">
      <span class="icon">📋</span>
      <h3>Gérer les exercices</h3>
      <p>Consultez, ajoutez ou modifiez votre bibliothèque d'exercices d'entraînement.</p>
    </a>

    <a href="historique.php" class="card">
      <span class="icon">📊</span>
      <h3>Historique</h3>
      <p>Consultez l'historique de vos séances et suivez votre progression.</p>
    </a>
  </main>
</body>
</html>