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
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Accueil - Entrainement</title>
  <style>
    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      min-height: 100vh;
      margin: 0;
      padding: 20px;
    }

    .header {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      padding: 20px;
      border-radius: 15px;
      margin-bottom: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      color: white;
    }

    .welcome {
      font-size: 1.2rem;
    }

    .btn-logout {
      background: #ff6b6b;
      color: white;
      border: none;
      padding: 10px 20px;
      border-radius: 25px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .btn-logout:hover {
      background: #ff5252;
      transform: translateY(-2px);
    }

    .dashboard {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 30px;
    }

    .card {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      padding: 30px;
      border-radius: 15px;
      text-align: center;
      color: white;
      text-decoration: none;
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .card:hover {
      background: rgba(255, 255, 255, 0.2);
      transform: translateY(-5px);
    }

    .card h3 {
      font-size: 1.5rem;
      margin-bottom: 15px;
    }

    .card p {
      opacity: 0.8;
      line-height: 1.5;
    }

    .icon {
      font-size: 3rem;
      margin-bottom: 20px;
      display: block;
    }
  </style>
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