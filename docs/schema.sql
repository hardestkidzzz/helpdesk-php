commentaires
CREATE TABLE `commentaires` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `ticket_id` int(11) DEFAULT NULL,
 `auteur_id` int(11) DEFAULT NULL,
 `contenu` text DEFAULT NULL,
 `date_message` datetime DEFAULT current_timestamp(),
 PRIMARY KEY (`id`),
 KEY `ticket_id` (`ticket_id`),
 KEY `auteur_id` (`auteur_id`),
 CONSTRAINT `commentaires_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `tickets` (`id`),
 CONSTRAINT `commentaires_ibfk_2` FOREIGN KEY (`auteur_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

tickets
CREATE TABLE `tickets` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `titre` varchar(255) DEFAULT NULL,
 `description` text DEFAULT NULL,
 `priorite` enum('basse','moyenne','haute') DEFAULT 'basse',
 `statut` enum('ouvert','en_cours','resolu') DEFAULT 'ouvert',
 `createur_id` int(11) DEFAULT NULL,
 `technicien_id` int(11) DEFAULT NULL,
 `date_creation` datetime DEFAULT current_timestamp(),
 `date_maj` datetime DEFAULT NULL,
 `date_resolu` datetime DEFAULT NULL,
 PRIMARY KEY (`id`),
 KEY `createur_id` (`createur_id`),
 KEY `idx_tickets_date_id` (`date_creation`,`id`),
 KEY `idx_tickets_dates` (`date_creation`,`date_resolu`),
 CONSTRAINT `tickets_ibfk_1` FOREIGN KEY (`createur_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=17 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci

users
CREATE TABLE `users` (
 `id` int(11) NOT NULL AUTO_INCREMENT,
 `nom` varchar(100) DEFAULT NULL,
 `prenom` varchar(100) DEFAULT NULL,
 `email` varchar(150) DEFAULT NULL,
 `mot_de_passe` varchar(255) DEFAULT NULL,
 `role` enum('utilisateur','technicien','admin') DEFAULT 'utilisateur',
 PRIMARY KEY (`id`),
 UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci