<?php
session_start();
require_once 'includes/db.php';

if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }

$user_id = $_SESSION['user_id'];
$role    = $_SESSION['role'] ?? 'utilisateur';

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) die("Ticket invalide.");
$ticket_id = (int)$_GET['id'];

// Récupérer le ticket et vérifier les permissions
$st = $pdo->prepare("SELECT createur_id FROM tickets WHERE id=?");
$st->execute([$ticket_id]);
$t = $st->fetch(PDO::FETCH_ASSOC);
if (!$t) die("Ticket introuvable.");

$isOwner = ($t['createur_id'] == $user_id);
if (!$isOwner && $role !== 'admin') die("Accès refusé.");

// Supprimer les commentaires associés puis le ticket
$pdo->prepare("DELETE FROM commentaires WHERE ticket_id=?")->execute([$ticket_id]);
$pdo->prepare("DELETE FROM tickets WHERE id=?")->execute([$ticket_id]);

header('Location: index.php');
exit;
