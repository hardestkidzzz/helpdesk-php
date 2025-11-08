<?php
// new_ticket.php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$page_title = 'Nouveau ticket';

$errors = [];
$infos   = [];

// Valeurs par défaut / persistance
$titre        = trim($_POST['titre'] ?? '');
$description  = trim($_POST['description'] ?? '');
$priorite     = $_POST['priorite'] ?? 'moyenne';
$allowedPrior = ['basse','moyenne','haute'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // CSRF
  if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    $errors[] = "Requête invalide (CSRF).";
  } else {
    // Validations
    if ($titre === '')          $errors[] = "Le titre est requis.";
    if (mb_strlen($titre) > 120) $errors[] = "Le titre ne doit pas dépasser 120 caractères.";
    if ($description === '')     $errors[] = "La description est requise.";
    if (mb_strlen($description) > 2000) $errors[] = "La description ne doit pas dépasser 2000 caractères.";
    if (!in_array($priorite, $allowedPrior)) $priorite = 'moyenne';

    if (!$errors) {
      // Insertion
      $stmt = $pdo->prepare("
        INSERT INTO tickets (titre, description, priorite, statut, createur_id, date_creation)
        VALUES (?, ?, ?, 'ouvert', ?, NOW())
      ");
      $stmt->execute([$titre, $description, $priorite, $user_id]);

      // Nettoyer le brouillon côté client (on fera via JS) et reset PHP
      $infos[] = "Ticket créé avec succès.";
      $titre = $description = '';
      $priorite = 'moyenne';

      // Redirection douce vers l’index ou vers le ticket créé (option) :
      // $newId = (int)$pdo->lastInsertId();
      // header("Location: view_ticket.php?id=".$newId); exit;
    }
  }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
  <div class="col-lg-9">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <h2 class="mb-0">Créer un ticket</h2>
      <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
    </div>

    <?php if ($errors): ?>
      <div class="alert alert-danger">
        <ul class="mb-0"><?php foreach ($errors as $e) echo "<li>".htmlspecialchars($e)."</li>"; ?></ul>
      </div>
    <?php endif; ?>

    <?php if ($infos): ?>
      <div class="alert alert-success">
        <?php foreach ($infos as $i) echo "<div>".htmlspecialchars($i)."</div>"; ?>
      </div>
    <?php endif; ?>

    <div class="card shadow-sm">
      <div class="card-body">
        <form method="POST" id="ticketForm" novalidate>
          <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

          <!-- Titre -->
          <div class="form-floating mb-3">
            <input type="text" class="form-control" id="titre" name="titre" placeholder="Problème Wi-Fi sur PC portable"
                   maxlength="120" required value="<?= htmlspecialchars($titre) ?>">
            <label for="titre">Titre du ticket</label>
            <div class="d-flex justify-content-between mt-1 small">
              <span class="text-muted">Sois précis : “Impossible d’installer l’imprimante HP LaserJet sur Windows 11”.</span>
              <span class="text-muted"><span id="countTitre">0</span>/120</span>
            </div>
          </div>

          <!-- Description -->
          <div class="mb-3">
            <label for="description" class="form-label">Description détaillée</label>
            <textarea class="form-control" id="description" name="description" rows="7" placeholder="Explique le problème, ce que tu as déjà essayé, les messages d’erreur éventuels…"
                      maxlength="2000" required><?= htmlspecialchars($description) ?></textarea>
            <div class="d-flex justify-content-between mt-1 small">
              <div class="text-muted">Astuce : indique le poste concerné, l’heure d’apparition, et reproduis les étapes.</div>
              <span class="text-muted"><span id="countDesc">0</span>/2000</span>
            </div>

            <!-- Quick chips -->
            <div class="mt-2 d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-sm btn-outline-secondary chip" data-insert="[Contexte] ">Contexte</button>
              <button type="button" class="btn btn-sm btn-outline-secondary chip" data-insert="[Étapes] ">Étapes</button>
              <button type="button" class="btn btn-sm btn-outline-secondary chip" data-insert="[Erreur] ">Erreur</button>
              <button type="button" class="btn btn-sm btn-outline-secondary chip" data-insert="[Attendu] ">Attendu</button>
            </div>
          </div>

          <!-- Priorité (toggle boutons) -->
          <div class="mb-4">
            <label class="form-label d-block">Priorité</label>
            <div class="btn-group" role="group" aria-label="Priorité">
              <input type="radio" class="btn-check" name="priorite" id="prio-basse" value="basse" <?= $priorite==='basse'?'checked':''; ?>>
              <label class="btn btn-outline-success" for="prio-basse"><i class="bi bi-arrow-down-circle"></i> Basse</label>

              <input type="radio" class="btn-check" name="priorite" id="prio-moyenne" value="moyenne" <?= $priorite==='moyenne'?'checked':''; ?>>
              <label class="btn btn-outline-warning" for="prio-moyenne"><i class="bi bi-arrow-left-right"></i> Moyenne</label>

              <input type="radio" class="btn-check" name="priorite" id="prio-haute" value="haute" <?= $priorite==='haute'?'checked':''; ?>>
              <label class="btn btn-outline-danger" for="prio-haute"><i class="bi bi-arrow-up-circle"></i> Haute</label>
            </div>
            <div class="form-text">Définis l’urgence réelle : <em>Haute</em> = production bloquée, <em>Basse</em> = contournement possible.</div>
          </div>

          <!-- Actions -->
          <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-primary" type="submit">
              <i class="bi bi-send"></i> Créer le ticket
            </button>
            <button class="btn btn-outline-secondary" type="button" id="btnClear">
              <i class="bi bi-eraser"></i> Effacer
            </button>
            <span class="text-muted ms-auto small">Raccourci : <kbd>Ctrl</kbd>/<kbd>Cmd</kbd> + <kbd>Enter</kbd></span>
          </div>
        </form>
      </div>
    </div>

    <!-- Tips -->
    <div class="alert alert-info mt-3 mb-0">
      <i class="bi bi-lightbulb"></i> Conseil : plus ta description est claire, plus la résolution sera rapide.
    </div>
  </div>
</div>

<script>
(function() {
  const $titre = document.getElementById('titre');
  const $desc  = document.getElementById('description');
  const $ct    = document.getElementById('countTitre');
  const $cd    = document.getElementById('countDesc');
  const $form  = document.getElementById('ticketForm');

  // Compteurs
  function updateCounts() {
    $ct.textContent = ($titre.value || '').length;
    $cd.textContent = ($desc.value  || '').length;
  }
  $titre.addEventListener('input', updateCounts);
  $desc.addEventListener('input', updateCounts);
  updateCounts();

  // Chips insertion
  document.querySelectorAll('.chip').forEach(btn => {
    btn.addEventListener('click', () => {
      const insert = btn.getAttribute('data-insert') || '';
      const area = $desc;
      const start = area.selectionStart, end = area.selectionEnd;
      const v = area.value;
      area.value = v.slice(0, start) + insert + v.slice(end);
      area.focus();
      area.selectionStart = area.selectionEnd = start + insert.length;
      updateCounts();
      saveDraft();
    });
  });

  // Draft autosave (localStorage)
  const DKEY = 'ticket-draft';
  function saveDraft() {
    const data = {
      titre: $titre.value,
      description: $desc.value,
      priorite: (document.querySelector('input[name="priorite"]:checked')||{}).value || 'moyenne'
    };
    localStorage.setItem(DKEY, JSON.stringify(data));
  }
  function loadDraft() {
    try {
      const raw = localStorage.getItem(DKEY);
      if (!raw) return;
      const d = JSON.parse(raw);
      if (d.titre) $titre.value = d.titre;
      if (d.description) $desc.value = d.description;
      if (d.priorite) {
        const el = document.querySelector(`input[name="priorite"][value="${d.priorite}"]`);
        if (el) el.checked = true;
      }
      updateCounts();
    } catch(e){}
  }
  function clearDraft() { localStorage.removeItem(DKEY); }

  // Charger le brouillon au chargement si le formulaire est vide (pas d’erreurs serveur)
  if (!<?= json_encode((bool)$errors) ?> && !<?= json_encode((bool)$infos) ?>) {
    if (!$titre.value && !$desc.value) loadDraft();
  }
  // Sauvegarde à la volée
  $titre.addEventListener('input', saveDraft);
  $desc.addEventListener('input', saveDraft);
  document.querySelectorAll('input[name="priorite"]').forEach(r => r.addEventListener('change', saveDraft));

  // Bouton Effacer
  document.getElementById('btnClear').addEventListener('click', () => {
    if (confirm('Effacer le brouillon ?')) {
      $titre.value = '';
      $desc.value  = '';
      (document.getElementById('prio-moyenne') || {}).checked = true;
      updateCounts();
      clearDraft();
    }
  });

  // Raccourci clavier: Ctrl/Cmd + Enter
  document.addEventListener('keydown', (e) => {
    if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
      $form.requestSubmit();
    }
  });

  // Si succès, on nettoie le draft
  <?php if ($infos): ?>
    clearDraft();
  <?php endif; ?>
})();
</script>

<?php require_once 'includes/footer.php'; ?>
