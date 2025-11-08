USE helpdesk;

-- 👥 Utilisateurs de démonstration
INSERT INTO users (nom, prenom, email, mot_de_passe, role) VALUES
('Admin', 'Root', 'admin@example.com', '$2y$10$K9iIC1Q.C0C8TovUxLShE.v3v3WJX6MwcmmxSOZnZRAxLue7tKSuS', 'admin'),
('Dupont', 'Jean', 'jean@example.com', '$2y$10$RkoLZ8lvSBZXHnPlt6B1TeBq1kX6kJyiI0UQamAjQK4DMJ4n9CJ5S', 'technicien'),
('Martin', 'Alice', 'alice@example.com', '$2y$10$RkoLZ8lvSBZXHnPlt6B1TeBq1kX6kJyiI0UQamAjQK4DMJ4n9CJ5S', 'utilisateur');

-- 🎫 Tickets de test
INSERT INTO tickets (createur_id, technicien_id, titre, description, priorite, statut) VALUES
(3, 2, 'Problème d’impression', 'L’imprimante du bureau ne répond plus depuis hier.', 'moyenne', 'ouvert'),
(3, 2, 'Erreur d’accès réseau', 'Impossible de se connecter au partage de fichiers interne.', 'haute', 'en_cours'),
(3, 2, 'Demande de mise à jour logiciel', 'Je souhaite une mise à jour de Visual Studio Code.', 'basse', 'resolu');

-- 💬 Commentaires associés aux tickets
INSERT INTO commentaires (ticket_id, auteur_id, contenu) VALUES
(1, 2, 'Avez-vous essayé de redémarrer l’imprimante ?'),
(1, 3, 'Oui, plusieurs fois. Aucun changement.'),
(2, 2, 'Je vais vérifier le serveur réseau.'),
(3, 3, 'Merci pour la mise à jour, tout fonctionne maintenant.');
