<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'utilisateur';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) { die("Ticket invalide."); }
$ticket_id = (int)$_GET['id'];

// Charger ticket
$st = $pdo->prepare("SELECT * FROM tickets WHERE id=?");
$st->execute([$ticket_id]);
$ticket = $st->fetch(PDO::FETCH_ASSOC);
if (!$ticket) die("Ticket introuvable.");

// Droits : créateur, technicien, admin
$isOwner = ($ticket['createur_id'] == $user_id);
if ($role === 'utilisateur' && !$isOwner) die("Accès refusé.");

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $errors[] = "Requête invalide (CSRF).";
  } else {
    $titre = trim($_POST['titre'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $priorite = $_POST['priorite'] ?? 'basse';
    $allowed = ['basse','moyenne','haute'];
    if (!in_array($priorite, $allowed)) $priorite = 'basse';

    if ($titre === '' || $description === '') $errors[] = "Tous les champs sont requis.";

    if (!$errors) {
      // Si tu n'as pas de colonne date_maj → enlève ", date_maj = NOW()"
      $up = $pdo->prepare("UPDATE tickets SET titre=?, description=?, priorite=?, date_maj = NOW() WHERE id=?");
      $up->execute([$titre, $description, $priorite, $ticket_id]);
      $ok = true;

      // Recharger
      $st = $pdo->prepare("SELECT * FROM tickets WHERE id=?");
      $st->execute([$ticket_id]);
      $ticket = $st->fetch(PDO::FETCH_ASSOC);
    }
  }
}

$page_title = "Modifier ticket #$ticket_id";
require_once 'includes/header.php';
?>
<p><a href="view_ticket.php?id=<?= (int)$ticket_id ?>">← Retour au ticket</a></p>

<?php if ($ok): ?><div class="alert alert-success">Modifications enregistrées.</div><?php endif; ?>
<?php if ($errors): ?>
  <div class="alert alert-danger"><ul class="mb-0"><?php foreach($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul></div>
<?php endif; ?>

<div class="card shadow-sm">
  <div class="card-body">
    <h3 class="h5 mb-3">Modifier le ticket #<?= (int)$ticket_id ?></h3>
    <form method="POST">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
      <div class="mb-3">
        <label class="form-label">Titre</label>
        <input type="text" name="titre" class="form-control" required value="<?= htmlspecialchars($ticket['titre']) ?>">
      </div>
      <div class="mb-3">
        <label class="form-label">Description</label>
        <textarea name="description" rows="6" class="form-control" required><?= htmlspecialchars($ticket['description']) ?></textarea>
      </div>
      <div class="mb-3">
        <label class="form-label">Priorité</label>
        <select name="priorite" class="form-select">
          <option value="basse"   <?= $ticket['priorite']==='basse'?'selected':''; ?>>Basse</option>
          <option value="moyenne" <?= $ticket['priorite']==='moyenne'?'selected':''; ?>>Moyenne</option>
          <option value="haute"   <?= $ticket['priorite']==='haute'?'selected':''; ?>>Haute</option>
        </select>
      </div>
      <button class="btn btn-primary">💾 Enregistrer</button>
      <a class="btn btn-outline-secondary" href="view_ticket.php?id=<?= (int)$ticket_id ?>">Annuler</a>
    </form>
  </div>
</div>
<?php require_once 'includes/footer.php'; ?>
