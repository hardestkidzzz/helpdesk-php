<?php
session_start();
require_once 'includes/db.php';
$page_title = 'Créer un compte';
require_once 'includes/header.php';
// Initialiser les variables
$errors = [];
$ok = false;
// Traiter le formulaire
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom    = trim($_POST['nom'] ?? '');
    $prenom = trim($_POST['prenom'] ?? '');
    $email  = trim($_POST['email'] ?? '');
    $mdp1   = $_POST['mdp1'] ?? '';
    $mdp2   = $_POST['mdp2'] ?? '';
    $role   = $_POST['role'] ?? 'utilisateur';
    $allowed_roles = ['utilisateur','technicien','admin'];
    if (!in_array($role, $allowed_roles)) $role = 'utilisateur';

    if ($nom === '' || $prenom === '' || $email === '' || $mdp1 === '' || $mdp2 === '') {
        $errors[] = "Tous les champs sont obligatoires.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Email invalide.";
    }
    if ($mdp1 !== $mdp2) {
        $errors[] = "Les mots de passe ne correspondent pas.";
    }
    if (empty($errors)) {
        // email unique ?
        $st = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $st->execute([$email]);
        if ($st->fetch()) {
            $errors[] = "Un compte existe déjà avec cet email.";
        } else {
            $hash = password_hash($mdp1, PASSWORD_DEFAULT);
            $ins = $pdo->prepare("INSERT INTO users (nom, prenom, email, mot_de_passe, role) VALUES (?,?,?,?,?)");
            $ins->execute([$nom, $prenom, $email, $hash, $role]);
            $ok = true;
        }
    }
}
?>
<div class="row">
  <div class="col-lg-6 mx-auto">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="mb-3">Créer un compte</h3>

        <?php if ($ok): ?>
          <div class="alert alert-success">Compte créé. Vous pouvez <a href="login.php">vous connecter</a>.</div>
        <?php endif; ?>

        <?php if ($errors): ?>
          <div class="alert alert-danger">
            <ul class="mb-0">
              <?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?>
            </ul>
          </div>
        <?php endif; ?>

        <form method="post" novalidate>
          <div class="mb-3">
            <label class="form-label">Nom</label>
            <input type="text" name="nom" class="form-control" required value="<?= htmlspecialchars($nom ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Prénom</label>
            <input type="text" name="prenom" class="form-control" required value="<?= htmlspecialchars($prenom ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($email ?? '') ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Mot de passe</label>
            <input type="password" name="mdp1" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Confirmer le mot de passe</label>
            <input type="password" name="mdp2" class="form-control" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Rôle</label>
            <select name="role" class="form-select">
              <option value="utilisateur">Utilisateur</option>
              <option value="technicien">Technicien</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <button class="btn btn-primary">Créer le compte</button>
        </form>
      </div>
    </div>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
