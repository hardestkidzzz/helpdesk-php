<?php
session_start();
require_once 'includes/db.php';

// Sécurité : seuls technicien / admin exportent
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['technicien','admin'])) {
    header('Location: login.php');
    exit;
}

// Requête : tous les tickets (ou adapte avec WHERE si besoin)
$stmt = $pdo->query("SELECT t.id, t.titre, t.description, t.priorite, t.statut,
                            u.nom AS createur_nom, u.prenom AS createur_prenom,
                            t.date_creation, t.date_maj
                     FROM tickets t
                     JOIN users u ON t.createur_id = u.id
                     ORDER BY t.date_creation DESC");
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Headers HTTP
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="tickets_helpdesk.csv"');

// Flux de sortie
$output = fopen('php://output', 'w');

// Entête du CSV
fputcsv($output, array_keys($tickets[0]));

// Données
foreach ($tickets as $t) {
    fputcsv($output, $t);
}

fclose($output);
exit;
