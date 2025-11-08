<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['role'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <title><?= isset($page_title) ? htmlspecialchars($page_title) : 'Helpdesk' ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Bootstrap 5.3+ -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand d-flex align-items-center gap-2" href="index.php">
      <i class="bi bi-life-preserver"></i> Helpdesk
    </a>
    <div class="d-flex align-items-center gap-2">
      <?php if (!empty($_SESSION['user_id'])): ?>
        <span class="text-white-50 me-2 d-none d-sm-inline">
          <?= htmlspecialchars($_SESSION['nom'] ?? '') ?>
          <?php if($role): ?>
            <span class="badge bg-secondary ms-1"><?= htmlspecialchars(ucfirst($role)) ?></span>
          <?php endif; ?>
        </span>
        <button id="themeToggle" class="btn btn-outline-light btn-sm" type="button" title="Thème">
          <i class="bi bi-moon"></i>
        </button>
        <a class="btn btn-danger" href="logout.php"><i class="bi bi-box-arrow-right"></i> Déconnexion</a>
      <?php else: ?>
        <a class="btn btn-outline-light me-2" href="login.php">Connexion</a>
        <a class="btn btn-primary" href="register.php">Inscription</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<div class="container mb-5">

<!-- Toast container -->
<div class="toast-container position-fixed top-0 end-0 p-3" id="toastContainer"></div>
