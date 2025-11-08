<?php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
// Récupérer les infos utilisateur
$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'];
// Titre de la page
$page_title = 'Tableau de bord';
require_once 'includes/header.php';

// --------- Gestion des filtres & pagination ---------
$statut    = $_GET['statut']    ?? '';
$priorite  = $_GET['priorite']  ?? '';
$q         = trim($_GET['q'] ?? '');
$assigned  = $_GET['assigned']  ?? ''; // "me" si coché
$sort = $_GET['sort'] ?? 'date_desc'; // défaut
$allowedSorts = [
  'date_desc' => 't.date_creation DESC',
  'date_asc'  => 't.date_creation ASC',
  'prio_desc' => "FIELD(t.priorite,'haute','moyenne','basse') ASC", // haute d'abord
  'prio_asc'  => "FIELD(t.priorite,'basse','moyenne','haute') ASC", // basse d'abord
  'statut'    => "FIELD(t.statut,'ouvert','en_cours','resolu') ASC"
];
$orderSql = $allowedSorts[$sort] ?? $allowedSorts['date_desc'];

// Pagination
$limit  = 10;
$page   = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $limit;

// Construire la requête avec filtres
$params = [];
$where  = [];

if ($role === 'technicien' || $role === 'admin') {
    $base = "FROM tickets t JOIN users u ON t.createur_id = u.id";
} else {
    $base = "FROM tickets t";
    $where[] = "t.createur_id = ?";
    $params[] = $user_id;
}

if (in_array($statut, ['ouvert','en_cours','resolu'])) {
    $where[] = "t.statut = ?";
    $params[] = $statut;
}
if (in_array($priorite, ['basse','moyenne','haute'])) {
    $where[] = "t.priorite = ?";
    $params[] = $priorite;
}
if ($q !== '') {
    $where[] = "(t.titre LIKE ? OR t.description LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
// Filtre "Assigné à moi" pour technicien/admin
if (($role === 'technicien' || $role === 'admin') && $assigned === 'me') {
    $where[] = "t.technicien_id = ?";
    $params[] = $user_id;
}

$whereSql = count($where) ? " WHERE ".implode(" AND ", $where) : "";

// Count total
$countSql = "SELECT COUNT(*) ".$base.$whereSql;
$stmt = $pdo->prepare($countSql);
$stmt->execute($params);
$total = (int) $stmt->fetchColumn();
$pages = max(1, (int)ceil($total / $limit));
$sort = $_GET['sort'] ?? 'date_desc';
$allowedSorts = [
  'date_desc' => 't.date_creation DESC',
  'date_asc'  => 't.date_creation ASC',
  'prio_desc' => "FIELD(t.priorite,'haute','moyenne','basse') ASC",
  'prio_asc'  => "FIELD(t.priorite,'basse','moyenne','haute') ASC",
  'statut'    => "FIELD(t.statut,'ouvert','en_cours','resolu') ASC"
];
$orderSql = $allowedSorts[$sort] ?? $allowedSorts['date_desc'];

// Construire la requête finale
$numeroExpr = "(SELECT COUNT(*)
               FROM tickets t2
               WHERE t2.date_creation > t.date_creation
                  OR (t2.date_creation = t.date_creation AND t2.id > t.id)
              ) + 1 AS numero";

if ($role === 'technicien' || $role === 'admin') {
    $dataSql = "SELECT t.*, u.nom AS createur_nom, u.prenom AS createur_prenom, $numeroExpr "
             . $base . $whereSql . " ORDER BY $orderSql LIMIT $limit OFFSET $offset";
} else {
    $dataSql = "SELECT t.*, $numeroExpr "
             . $base . $whereSql . " ORDER BY $orderSql LIMIT $limit OFFSET $offset";
}


// 🔒 Sécurise
$tickets = [];
$db_error = null;
try {
    $stmt = $pdo->prepare($dataSql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
    $db_error = $e->getMessage();
    // $tickets reste []
}



$stmt = $pdo->prepare($dataSql);
$stmt->execute($params);
$exportUrl = 'export_tickets.php';
if (!empty($_GET)) {
  $exportUrl .= '?' . http_build_query($_GET); // conserver les filtres à l’export
}
?>


<?php
//
$kpi = ['ouvert'=>0,'en_cours'=>0,'resolu'=>0];
$kstmt = $pdo->prepare("SELECT statut, COUNT(*) nb FROM tickets t ".($role==='utilisateur'?"WHERE t.createur_id=?":"")." GROUP BY statut");
$kstmt->execute($role==='utilisateur' ? [$user_id] : []);
foreach ($kstmt->fetchAll(PDO::FETCH_ASSOC) as $r) { $kpi[$r['statut']] = (int)$r['nb']; }
?>
<div class="row g-3 mb-3">
  <div class="col-md-4">
    <div class="card shadow-sm border-danger-subtle">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted small">Ouverts</div>
          <div class="fs-4 fw-semibold"><?= $kpi['ouvert'] ?></div>
        </div>
        <i class="bi bi-exclamation-circle fs-2 text-danger"></i>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm border-warning-subtle">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted small">En cours</div>
          <div class="fs-4 fw-semibold"><?= $kpi['en_cours'] ?></div>
        </div>
        <i class="bi bi-arrow-repeat fs-2 text-warning"></i>
      </div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="card shadow-sm border-success-subtle">
      <div class="card-body d-flex justify-content-between align-items-center">
        <div>
          <div class="text-muted small">Résolus</div>
          <div class="fs-4 fw-semibold"><?= $kpi['resolu'] ?></div>
        </div>
        <i class="bi bi-check2-circle fs-2 text-success"></i>
      </div>
    </div>
  </div>
</div>

<div class="d-flex justify-content-between align-items-center mb-2">
  <h2 class="mb-0">Bonjour, <?= htmlspecialchars($_SESSION['nom']); ?> 👋</h2>
  <a href="new_ticket.php" class="btn btn-primary">
    <i class="bi bi-plus-circle"></i> Nouveau ticket
  </a>
</div>

<?php if (in_array($role, ['technicien','admin'])): ?>
<div class="text-end mb-3">
  <a href="<?= $exportUrl ?>" class="btn btn-outline-success">
    <i class="bi bi-download"></i> Export CSV
  </a>
</div>
<?php endif; ?>



<ul class="nav nav-pills mb-2">
  <?php
    $baseUrl = strtok($_SERVER['REQUEST_URI'], '?'); // /helpdesk/index.php
    // Construire les liens en conservant les autres filtres
    $keep = $_GET; unset($keep['statut'], $keep['page']);
    function linkWith($arr){ return '?' . http_build_query($arr); }
  ?>
  <li class="nav-item">
    <a class="nav-link <?= $statut===''?'active':'' ?>" href="<?= linkWith($keep) ?>">Tous</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $statut==='ouvert'?'active':'' ?>" href="<?= linkWith($keep + ['statut'=>'ouvert']) ?>">Ouverts</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $statut==='en_cours'?'active':'' ?>" href="<?= linkWith($keep + ['statut'=>'en_cours']) ?>">En cours</a>
  </li>
  <li class="nav-item">
    <a class="nav-link <?= $statut==='resolu'?'active':'' ?>" href="<?= linkWith($keep + ['statut'=>'resolu']) ?>">Résolus</a>
  </li>
</ul>

<form id="filtersForm" class="row g-2 mb-3" method="get">
  <div class="col-sm-2">
    <label class="form-label">Statut</label>
    <select name="statut" class="form-select">
      <option value="">Tous</option>
      <option value="ouvert"   <?= $statut==='ouvert'?'selected':''; ?>>Ouvert</option>
      <option value="en_cours" <?= $statut==='en_cours'?'selected':''; ?>>En cours</option>
      <option value="resolu"   <?= $statut==='resolu'?'selected':''; ?>>Résolu</option>
    </select>
  </div>

  <div class="col-sm-2">
    <label class="form-label">Priorité</label>
    <select name="priorite" class="form-select">
      <option value="">Toutes</option>
      <option value="basse"   <?= $priorite==='basse'?'selected':''; ?>>Basse</option>
      <option value="moyenne" <?= $priorite==='moyenne'?'selected':''; ?>>Moyenne</option>
      <option value="haute"   <?= $priorite==='haute'?'selected':''; ?>>Haute</option>
    </select>
  </div>

  <div class="col-sm-3">
    <label class="form-label">Recherche</label>
    <input type="text" name="q" class="form-control" placeholder="titre, description..." value="<?= htmlspecialchars($q) ?>">
  </div>

  <div class="col-sm-3">
    <label class="form-label">Trier par</label>
    <select name="sort" class="form-select">
      <option value="date_desc" <?= ($sort??'date_desc')==='date_desc'?'selected':''; ?>>Date ↓ (récent d’abord)</option>
      <option value="date_asc"  <?= ($sort??'date_desc')==='date_asc'?'selected':''; ?>>Date ↑ (ancien d’abord)</option>
      <option value="prio_desc" <?= ($sort??'date_desc')==='prio_desc'?'selected':''; ?>>Priorité (haute → basse)</option>
      <option value="prio_asc"  <?= ($sort??'date_desc')==='prio_asc'?'selected':''; ?>>Priorité (basse → haute)</option>
      <option value="statut"    <?= ($sort??'date_desc')==='statut'?'selected':''; ?>>Statut (ouvert → résolu)</option>
    </select>
  </div>

  <?php if ($role === 'technicien' || $role === 'admin'): ?>
  <div class="col-sm-2 d-flex align-items-end">
    <div class="form-check">
      <input class="form-check-input" type="checkbox" name="assigned" value="me" id="assigned"
             <?= ($assigned==='me') ? 'checked' : '' ?>>
      <label class="form-check-label" for="assigned">Assigné à moi</label>
    </div>
  </div>
  <?php endif; ?>
</form>


<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-striped align-middle mb-0">
      <thead class="table-light">
        <tr>
          <th>#</th>
          <th>Titre</th>
          <th>Priorité</th>
          <th>Statut</th>
          <th>Date</th>
          <?php if ($role !== 'utilisateur'): ?><th>Créé par</th><?php endif; ?>
          <th></th>
        </tr>
      </thead>
      <tbody>
<?php if (empty($tickets)): ?>
  <tr>
    <td colspan="<?= $role!=='utilisateur'?6:5; ?>" class="text-center p-5">
      <i class="bi bi-inboxes fs-1 d-block mb-2"></i>
      <div class="fw-semibold mb-1">Aucun ticket pour l’instant</div>
      <div class="text-muted mb-3">Crée ton premier ticket pour démarrer.</div>
      <a href="new_ticket.php" class="btn btn-primary"><i class="bi bi-plus-circle"></i> Nouveau ticket</a>
    </td>
  </tr>
<?php else: foreach ($tickets as $t): ?>

        <tr>
          <td><?= (int)$t['numero'] ?></td>
          <td><?= htmlspecialchars($t['titre']) ?></td>
          <td>
            <span class="badge <?php
              echo $t['priorite']==='haute'?'text-bg-danger':
                   ($t['priorite']==='moyenne'?'text-bg-warning':'text-bg-success');
            ?>">
              <?= ucfirst($t['priorite']) ?>
            </span>
          </td>
         <td>
  <span class="badge <?php
    echo $t['statut']==='resolu'?'text-bg-success':
         ($t['statut']==='en_cours'?'text-bg-warning':'text-bg-danger');
  ?>">
    <?= ucfirst($t['statut']) ?>
  </span>
  <?php if (!empty($t['technicien_id']) && (int)$t['technicien_id'] === (int)$user_id): ?>
    <span class="badge text-bg-primary ms-1">À moi</span>
  <?php endif; ?>
</td>

          <td><?= htmlspecialchars($t['date_creation']) ?></td>
          <?php if ($role !== 'utilisateur'): ?>
            <td><?= htmlspecialchars(($t['createur_prenom']??'').' '.($t['createur_nom']??'')) ?></td>
          <?php endif; ?>
          <td><a class="btn btn-sm btn-outline-primary" href="view_ticket.php?id=<?= (int)$t['id'] ?>">Ouvrir</a></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php if ($pages > 1): ?>
<nav class="mt-3">
  <ul class="pagination justify-content-center">
    <?php
      function linkWithFilters($p) {
        $params = $_GET; $params['page'] = $p;
        return '?' . http_build_query($params);
      }
    ?>
    <li class="page-item <?= $page<=1?'disabled':'' ?>">
      <a class="page-link" href="<?= $page<=1?'#':linkWithFilters($page-1) ?>">Précédent</a>
    </li>
    <?php for ($i = 1; $i <= $pages; $i++): ?>
      <li class="page-item <?= $i===$page?'active':'' ?>">
        <a class="page-link" href="<?= linkWithFilters($i) ?>"><?= $i ?></a>
      </li>
    <?php endfor; ?>
    <li class="page-item <?= $page>=$pages?'disabled':'' ?>">
      <a class="page-link" href="<?= $page>=$pages?'#':linkWithFilters($page+1) ?>">Suivant</a>
    </li>
  </ul>
  <p class="text-center text-muted mb-0">Total : <?= $total ?> tickets</p>
</nav>
<?php endif; ?>

<script>
(function(){
  const form = document.getElementById('filtersForm');
  if (!form) return;

  // changements de select & checkbox
  form.querySelectorAll('select, input[type="checkbox"]').forEach(el => {
    el.addEventListener('change', () => form.submit());
  });

  // recherche avec délai
  const search = form.querySelector('input[name="q"]');
  let t;
  if (search) {
    const submitDebounced = () => {
      clearTimeout(t);
      t = setTimeout(() => form.submit(), 400);
    };
    search.addEventListener('input', submitDebounced);
  }
})();
</script>


<?php require_once 'includes/footer.php'; ?>
