<?php
session_start();
require_once 'includes/db.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $mot_de_passe = $_POST['mot_de_passe'] ?? '';

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($mot_de_passe, $user['mot_de_passe'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['role']    = $user['role'];
        $_SESSION['nom']     = $user['nom'];
        header('Location: index.php');
        exit;
    } else {
        $error = "Identifiants incorrects. Vérifiez l'email et le mot de passe.";
    }
}

$page_title = 'Connexion';
require_once 'includes/header.php';
?>
<div class="row">
  <div class="col-md-6 col-lg-4 mx-auto">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="mb-3 text-center">Connexion</h3>

        <?php if (!empty($error)): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required autocomplete="username">
          </div>
          <div class="mb-3">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="mot_de_passe" class="form-control" required autocomplete="current-password">
          </div>
          <button class="btn btn-primary w-100">Se connecter</button>
        </form>

        <hr class="my-4">
        <p class="mb-0 text-center">
          Pas de compte ? <a href="register.php">Créer un compte</a>
        </p>
      </div>
    </div>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
