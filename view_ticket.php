<?php
session_start();
require_once 'includes/db.php';

// Auth obligatoire
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'utilisateur';

// Récup id ticket
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    die("Ticket invalide.");
}
$ticket_id = (int) $_GET['id'];

// Charger le ticket (avec créateur et technicien)
$stmt = $pdo->prepare(
    "SELECT t.*, 
            uc.nom  AS createur_nom,  uc.prenom  AS createur_prenom,
            ut.nom  AS tech_nom,      ut.prenom  AS tech_prenom
     FROM tickets t
     JOIN users uc ON t.createur_id = uc.id
     LEFT JOIN users ut ON t.technicien_id = ut.id
     WHERE t.id = ?"
);
$stmt->execute([$ticket_id]);
$ticket = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ticket) { die("Ticket introuvable."); }

// Droits d'accès : l'utilisateur simple ne voit que ses tickets
$isOwner = ($ticket['createur_id'] == $user_id);
if ($role === 'utilisateur' && !$isOwner) { die("Accès refusé."); }

// Gestion des actions POST
$errors = [];
$infos  = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sécurité CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = "Requête invalide (CSRF).";
    } else {

        // ---- AJOUT D’UN COMMENTAIRE ----
        if (isset($_POST['action']) && $_POST['action'] === 'add_comment') {

            // Empêche d’ajouter un commentaire sur un ticket résolu
            if ($ticket['statut'] === 'resolu') {
                $errors[] = "Impossible d’ajouter un commentaire sur un ticket résolu.";
            } else {
                $contenu = trim($_POST['contenu'] ?? '');
                if ($contenu === '') {
                    $errors[] = "Le commentaire ne peut pas être vide.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO commentaires (ticket_id, auteur_id, contenu, date_message)
                                           VALUES (?, ?, ?, NOW())");
                    $stmt->execute([$ticket_id, $user_id, $contenu]);
                    $infos[] = "Commentaire ajouté.";
                }
            }
        }

        // ---- CHANGEMENT DE STATUT ----
        if (($role === 'technicien' || $role === 'admin') && ($_POST['action'] ?? '') === 'change_status') {
            $nouveau = $_POST['statut'] ?? 'ouvert';
            $allowed = ['ouvert', 'en_cours', 'resolu'];
            if (!in_array($nouveau, $allowed)) {
                $errors[] = "Statut invalide.";
            } else {
                // Si aucun technicien assigné, s'assigner automatiquement
                if (empty($ticket['technicien_id'])) {
                    $stmt = $pdo->prepare("UPDATE tickets SET statut = ?, technicien_id = ?, date_maj = NOW() WHERE id = ?");
                    $stmt->execute([$nouveau, $user_id, $ticket_id]);
                } else {
                    $stmt = $pdo->prepare("UPDATE tickets SET statut = ?, date_maj = NOW() WHERE id = ?");
                    $stmt->execute([$nouveau, $ticket_id]);
                }
                $infos[] = "Statut mis à jour.";
            }
        }
    }

    // Recharger les données à jour
    $stmt = $pdo->prepare(
        "SELECT t.*, 
                uc.nom  AS createur_nom,  uc.prenom  AS createur_prenom,
                ut.nom  AS tech_nom,      ut.prenom  AS tech_prenom
         FROM tickets t
         JOIN users uc ON t.createur_id = uc.id
         LEFT JOIN users ut ON t.technicien_id = ut.id
         WHERE t.id = ?"
    );
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
}

// numéro public 1..N du ticket courant
$stNum = $pdo->prepare("
  SELECT
    (SELECT COUNT(*)
     FROM tickets t2
     WHERE t2.date_creation > t.date_creation
        OR (t2.date_creation = t.date_creation AND t2.id > t.id)
    ) + 1 AS numero
  FROM tickets t
  WHERE t.id = ?
");
$stNum->execute([$ticket_id]);
$ticket_numero = (int)$stNum->fetchColumn();

        // S'assigner le ticket (tech/admin) si non assigné
        if (($role === 'technicien' || $role === 'admin') && ($_POST['action'] ?? '') === 'assign_me') {
            if (!empty($ticket['technicien_id'])) {
                $infos[] = "Ticket déjà assigné.";
            } else {
                $stmt = $pdo->prepare("UPDATE tickets SET technicien_id = ?, date_maj = NOW() WHERE id = ?");
                $stmt->execute([$user_id, $ticket_id]);
                $infos[] = "Ticket assigné à vous.";
            }
        }
    

    // Recharger les données à jour
    $stmt = $pdo->prepare(
        "SELECT t.*, 
                uc.nom  AS createur_nom,  uc.prenom  AS createur_prenom,
                ut.nom  AS tech_nom,      ut.prenom  AS tech_prenom
         FROM tickets t
         JOIN users uc ON t.createur_id = uc.id
         LEFT JOIN users ut ON t.technicien_id = ut.id
         WHERE t.id = ?"
    );
    $stmt->execute([$ticket_id]);
    $ticket = $stmt->fetch(PDO::FETCH_ASSOC);
    $isOwner = ($ticket['createur_id'] == $user_id);


// Charger les commentaires
$stmt = $pdo->prepare(
    "SELECT c.*, u.nom, u.prenom
     FROM commentaires c
     JOIN users u ON c.auteur_id = u.id
     WHERE c.ticket_id = ?
     ORDER BY c.date_message ASC"
);
$stmt->execute([$ticket_id]);
$comments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ---- Vue (avec header/footer Bootstrap) ----
$page_title = "Ticket #".$ticket['id'];
require_once 'includes/header.php';
?>

<style>
/* Timeline très légère */
.timeline { position: relative; }
.timeline::before { content:""; position:absolute; left:20px; top:0; bottom:0; width:2px; background:rgba(0,0,0,.08); }
.timeline-item { position:relative; padding-left:56px; margin-bottom:18px; }
.timeline-item .dot { position:absolute; left:12px; top:4px; width:16px; height:16px; border-radius:50%; background:#0d6efd; box-shadow:0 0 0 3px rgba(13,110,253,.15); }
[data-bs-theme="dark"] .timeline::before{ background:rgba(255,255,255,.08); }
[data-bs-theme="dark"] .timeline-item .dot{ box-shadow:0 0 0 3px rgba(13,110,253,.25); }
.badge-pill{ border-radius:999px; padding:.35rem .6rem; }
</style>

<?php // Messages en alert (tu peux basculer en toasts si tu veux)
foreach ($errors as $e)  echo '<div class="alert alert-danger">'.$e.'</div>';
foreach ($infos  as $i)  echo '<div class="alert alert-success">'.$i.'</div>';
?>

<div class="row g-3">
  <!-- Colonne principale -->
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <!-- En-tête ticket -->
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
          <div>
            <h2 class="h4 mb-1">Ticket n°<?= $ticket_numero ?> · <?= htmlspecialchars($ticket['titre']) ?></h2>
            <div class="text-muted small">
              Créé par <strong><?= htmlspecialchars($ticket['createur_prenom'].' '.$ticket['createur_nom']) ?></strong>
              le <?= htmlspecialchars($ticket['date_creation']) ?>
              <?php if (!empty($ticket['tech_nom'])): ?>
                · Assigné à <strong><?= htmlspecialchars($ticket['tech_prenom'].' '.$ticket['tech_nom']) ?></strong>
              <?php endif; ?>
            </div>
          </div>
          <div class="d-flex gap-2">
            <span class="badge badge-pill 
              <?= $ticket['priorite']==='haute'?'text-bg-danger':($ticket['priorite']==='moyenne'?'text-bg-warning':'text-bg-success') ?>">
              Priorité : <?= ucfirst($ticket['priorite']) ?>
            </span>
            <span class="badge badge-pill 
              <?= $ticket['statut']==='resolu'?'text-bg-success':($ticket['statut']==='en_cours'?'text-bg-warning':'text-bg-danger') ?>">
              Statut : <?= ucfirst($ticket['statut']) ?>
            </span>
          </div>
        </div>

        <hr class="my-3">

        <!-- Description -->
        <h3 class="h6 text-uppercase text-muted mb-2">Description</h3>
        <p class="mb-0"><?= nl2br(htmlspecialchars($ticket['description'])) ?></p>

        <!-- Actions principales -->
        <div class="mt-3 d-flex flex-wrap gap-2">
          <?php if ($role === 'admin' || $role === 'technicien' || $isOwner): ?>
            <a href="edit_ticket.php?id=<?= (int)$ticket['id'] ?>" class="btn btn-outline-warning btn-sm">
              ✏️ Modifier
            </a>
          <?php endif; ?>

          <?php if ($role === 'admin' || $isOwner): ?>
            <a href="delete_ticket.php?id=<?= (int)$ticket['id'] ?>" 
               class="btn btn-outline-danger btn-sm"
               onclick="return confirm('Voulez-vous vraiment supprimer ce ticket ?');">🗑️ Supprimer</a>
          <?php endif; ?>

          <?php if (($role==='technicien' || $role==='admin') && empty($ticket['technicien_id'])): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="assign_me">
              <button class="btn btn-outline-primary btn-sm">👤 S’assigner</button>
            </form>
          <?php endif; ?>

          <?php if (($role==='technicien' || $role==='admin') && $ticket['statut'] !== 'resolu'): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="change_status">
              <input type="hidden" name="statut" value="resolu">
              <button class="btn btn-success btn-sm">✅ Marquer résolu</button>
            </form>
          <?php endif; ?>

          <?php if (($role==='technicien' || $role==='admin') && $ticket['statut'] === 'resolu'): ?>
            <form method="POST" class="d-inline">
              <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
              <input type="hidden" name="action" value="change_status">
              <input type="hidden" name="statut" value="en_cours">
              <button class="btn btn-outline-primary btn-sm">🔓 Rouvrir</button>
            </form>
          <?php endif; ?>
        </div>

        <?php if ($role === 'technicien' || $role === 'admin'): ?>
          <hr class="my-3">
          <!-- Changement de statut détaillé -->
          <form method="POST" class="row g-2 align-items-end">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="change_status">
            <div class="col-sm-4">
              <label class="form-label">Changer le statut</label>
              <select name="statut" class="form-select">
                <option value="ouvert"   <?= $ticket['statut']==='ouvert'?'selected':''; ?>>Ouvert</option>
                <option value="en_cours" <?= $ticket['statut']==='en_cours'?'selected':''; ?>>En cours</option>
                <option value="resolu"   <?= $ticket['statut']==='resolu'?'selected':''; ?>>Résolu</option>
              </select>
            </div>
            <div class="col-sm-3">
              <button class="btn btn-primary">Mettre à jour</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Timeline commentaires -->
    <div class="card shadow-sm mt-3">
      <div class="card-body">
        <h3 class="h6 text-uppercase text-muted mb-3">Commentaires (<?= count($comments) ?>)</h3>

        <?php if (empty($comments)): ?>
          <p class="text-muted mb-0">Aucun commentaire pour le moment.</p>
        <?php else: ?>
          <div class="timeline">
            <?php foreach ($comments as $c): ?>
              <div class="timeline-item">
                <span class="dot"></span>
                <div class="d-flex justify-content-between">
                  <strong><?= htmlspecialchars($c['prenom'].' '.$c['nom']) ?></strong>
                  <span class="text-muted small"><?= htmlspecialchars($c['date_message']) ?></span>
                </div>
                <div class="mt-1"><?= nl2br(htmlspecialchars($c['contenu'])) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Ajouter un commentaire ou message "fermé" -->
    <?php if ($ticket['statut'] !== 'resolu'): ?>
      <div class="card shadow-sm mt-3">
        <div class="card-body">
          <h3 class="h6">Ajouter un commentaire</h3>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <input type="hidden" name="action" value="add_comment">
            <textarea name="contenu" rows="5" class="form-control" placeholder="Votre message..." required></textarea>
            <div class="d-flex gap-2 mt-2">
              <button class="btn btn-secondary">Publier</button>
              <a href="index.php" class="btn btn-outline-secondary">Retour</a>
            </div>
          </form>
        </div>
      </div>
    <?php else: ?>
      <div class="alert alert-success mt-3 mb-0">
        ✅ Ce ticket est résolu. Les commentaires sont désactivés.
      </div>
    <?php endif; ?>
  </div>

  <!-- Sidebar d’infos -->
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h3 class="h6 text-uppercase text-muted mb-3">Informations</h3>

        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">numéro</span><span>#<?= (int)$ticket['id'] ?></span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Créateur</span>
          <span><?= htmlspecialchars($ticket['createur_prenom'].' '.$ticket['createur_nom']) ?></span>
        </div>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Créé le</span>
          <span><?= htmlspecialchars($ticket['date_creation']) ?></span>
        </div>
        <?php if (!empty($ticket['date_maj'])): ?>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">MàJ</span>
          <span><?= htmlspecialchars($ticket['date_maj']) ?></span>
        </div>
        <?php endif; ?>
        <div class="d-flex justify-content-between mb-2">
          <span class="text-muted">Priorité</span>
          <span class="badge <?= $ticket['priorite']==='haute'?'text-bg-danger':($ticket['priorite']==='moyenne'?'text-bg-warning':'text-bg-success') ?>">
            <?= ucfirst($ticket['priorite']) ?>
          </span>
        </div>
        <div class="d-flex justify-content-between">
          <span class="text-muted">Statut</span>
          <span class="badge <?= $ticket['statut']==='resolu'?'text-bg-success':($ticket['statut']==='en_cours'?'text-bg-warning':'text-bg-danger') ?>">
            <?= ucfirst($ticket['statut']) ?>
          </span>
        </div>
      </div>
    </div>

    <div class="card shadow-sm mt-3">
      <div class="card-body">
        <h3 class="h6 text-uppercase text-muted mb-3">Raccourcis</h3>
        <a href="index.php" class="btn btn-outline-secondary w-100 mb-2"><i class="bi bi-arrow-left"></i> Retour au tableau</a>
        <?php if ($role!=='utilisateur'): ?>
          <a href="new_ticket.php" class="btn btn-outline-primary w-100"><i class="bi bi-plus-circle"></i> Nouveau ticket</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once 'includes/footer.php'; ?>

