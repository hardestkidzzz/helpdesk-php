<?php
// stats.php
session_start();
require_once 'includes/db.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = (int)$_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'utilisateur';

// Autoriser tout le monde à voir ses stats perso ; stats globales si technicien/admin
$isTechOrAdmin = in_array($role, ['technicien','admin'], true);

$page_title = 'Statistiques';
require_once 'includes/header.php';

/* -------------------- KPI -------------------- */
// Portée: globale si tech/admin, sinon uniquement tickets créés par l'utilisateur
$scopeWhere = $isTechOrAdmin ? "" : "WHERE createur_id = ?";
$scopeParams = $isTechOrAdmin ? [] : [$user_id];

// Statut
$kpiStmt = $pdo->prepare("
  SELECT statut, COUNT(*) nb
  FROM tickets
  $scopeWhere
  GROUP BY statut
");
$kpiStmt->execute($scopeParams);
$kpi = ['ouvert'=>0,'en_cours'=>0,'resolu'=>0];
foreach ($kpiStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $kpi[$r['statut']] = (int)$r['nb'];

// Priorité
$prioStmt = $pdo->prepare("
  SELECT priorite, COUNT(*) nb
  FROM tickets
  $scopeWhere
  GROUP BY priorite
");
$prioStmt->execute($scopeParams);
$prio = ['haute'=>0,'moyenne'=>0,'basse'=>0];
foreach ($prioStmt->fetchAll(PDO::FETCH_ASSOC) as $r) $prio[$r['priorite']] = (int)$r['nb'];

// Tickets / jour (30 derniers jours)
$days = 30; // tu peux rendre ça dynamique via $_GET['days'] (7/30/90)
$trendWhere = "WHERE date_creation >= DATE_SUB(CURDATE(), INTERVAL ".($days-1)." DAY)";
$trendParams = [];

if (!$isTechOrAdmin) {
  $trendWhere .= " AND createur_id = ?";
  $trendParams[] = $user_id;
}

$trendStmt = $pdo->prepare("
  SELECT DATE(date_creation) AS d, COUNT(*) AS nb
  FROM tickets
  $trendWhere
  GROUP BY d
  ORDER BY d
");
$trendStmt->execute($trendParams);

// Préparer la série complète (jours manquants à 0)
$trend = [];
for ($i = $days-1; $i >= 0; $i--) {
  $day = (new DateTime())->modify("-$i day")->format('Y-m-d');
  $trend[$day] = 0;
}
foreach ($trendStmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  $trend[$r['d']] = (int)$r['nb'];
}

// Tickets par technicien (top 6) – seulement tech/admin
$byTech = [];
if ($isTechOrAdmin) {
  $techStmt = $pdo->query("
    SELECT COALESCE(CONCAT(u.prenom,' ',u.nom),'(non assigné)') tech, COUNT(*) nb
    FROM tickets t
    LEFT JOIN users u ON t.technicien_id = u.id
    GROUP BY tech
    ORDER BY nb DESC
    LIMIT 6
  ");
  $byTech = $techStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Temps de résolution moyen (en heures) – nécessite une colonne date_resolu
// (voir note plus bas pour l’ajouter automatiquement)
$avgHours = null;
$avgStmt = $pdo->prepare("
  SELECT AVG(TIMESTAMPDIFF(HOUR, date_creation, date_resolu)) as avg_h
  FROM tickets
  WHERE statut = 'resolu' AND date_resolu IS NOT NULL
  ".($isTechOrAdmin ? "" : "AND createur_id = ?")
);
$avgStmt->execute($isTechOrAdmin ? [] : [$user_id]);
$avgHours = $avgStmt->fetchColumn();
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h2 class="mb-0">Statistiques <?= $isTechOrAdmin ? 'globales' : 'personnelles' ?></h2>
  <a href="index.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left"></i> Retour</a>
</div>

<!-- KPI -->
<div class="row g-3 mb-3">
  <div class="col-md-3">
    <div class="card shadow-sm border-danger-subtle">
      <div class="card-body">
        <div class="text-muted small">Ouverts</div>
        <div class="fs-3 fw-semibold"><?= (int)$kpi['ouvert'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm border-warning-subtle">
      <div class="card-body">
        <div class="text-muted small">En cours</div>
        <div class="fs-3 fw-semibold"><?= (int)$kpi['en_cours'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm border-success-subtle">
      <div class="card-body">
        <div class="text-muted small">Résolus</div>
        <div class="fs-3 fw-semibold"><?= (int)$kpi['resolu'] ?></div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Temps de résolution moyen</div>
        <div class="fs-5 fw-semibold">
          <?= $avgHours !== null ? number_format((float)$avgHours, 1, ',', ' ') . ' h' : '—' ?>
        </div>
        <div class="text-muted small">(<em>tickets résolus</em>)</div>
      </div>
    </div>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-3">Créations par jour (30 jours)</h5>
        <canvas id="chartTrend" height="100"></canvas>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-3">Répartition par statut</h5>
        <canvas id="chartStatus" height="230"></canvas>
      </div>
    </div>
  </div>

  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-3">Répartition par priorité</h5>
        <canvas id="chartPrio" height="160"></canvas>
      </div>
    </div>
  </div>

  <?php if ($isTechOrAdmin): ?>
  <div class="col-lg-6">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-3">Tickets par technicien (Top 6)</h5>
        <canvas id="chartTech" height="160"></canvas>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
const trendLabels = <?= json_encode(array_keys($trend)) ?>;
const trendData   = <?= json_encode(array_values($trend)) ?>;

const statusData = {
  labels: ['Ouvert', 'En cours', 'Résolu'],
  values: [<?= (int)$kpi['ouvert'] ?>, <?= (int)$kpi['en_cours'] ?>, <?= (int)$kpi['resolu'] ?>]
};
const prioData = {
  labels: ['Haute', 'Moyenne', 'Basse'],
  values: [<?= (int)$prio['haute'] ?>, <?= (int)$prio['moyenne'] ?>, <?= (int)$prio['basse'] ?>]
};
<?php if ($isTechOrAdmin): ?>
const techLabels = <?= json_encode(array_column($byTech,'tech')) ?>;
const techValues = <?= json_encode(array_map('intval', array_column($byTech,'nb'))) ?>;
<?php endif; ?>

function makeLine(ctx, labels, data) {
  new Chart(ctx, {
    type: 'line',
    data: { labels, datasets: [{ data, fill:false, tension:.2 }]},
    options: {
      plugins: { legend: { display:false } },
      scales: { x: { ticks: { maxRotation: 0, autoSkip: true } } }
    }
  });
}
function makeDoughnut(ctx, labels, data) {
  new Chart(ctx, {
    type: 'doughnut',
    data: { labels, datasets: [{ data }]},
    options: { plugins: { legend: { position:'bottom' } } }
  });
}
function makeBar(ctx, labels, data) {
  new Chart(ctx, {
    type: 'bar',
    data: { labels, datasets: [{ data }]},
    options: { plugins: { legend: { display:false } } }
  });
}

makeLine(document.getElementById('chartTrend'), trendLabels, trendData);
makeDoughnut(document.getElementById('chartStatus'), statusData.labels, statusData.values);
makeDoughnut(document.getElementById('chartPrio'), prioData.labels, prioData.values);
<?php if ($isTechOrAdmin): ?>
makeBar(document.getElementById('chartTech'), techLabels, techValues);
<?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>
