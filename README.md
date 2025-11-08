# 🧰 Helpdesk - Gestion de Tickets (PHP/MySQL)

Projet développé en PHP: une application de gestion de tickets d'assistance.

## 🚀 Fonctionnalités principales
- Authentification sécurisée (bcrypt)
- Rôles : Utilisateur, Technicien, Administrateur
- Création, édition et suivi des tickets
- Filtres (statut, priorité, recherche)
- Commentaires et suivi des résolutions
- Export CSV (admin/technicien)
- Dashboard avec statistiques
- Interface responsive (Bootstrap 5) + mode sombre

## ⚙️ Installation locale
1. Copier le dossier `helpdesk` dans `C:\xampp\htdocs\`
2. Démarrer **Apache** ensuite **MySQL** via XAMPP
3. Créer la base :
   - Aller sur [phpMyAdmin](http://localhost/phpmyadmin)
   - Créer une base nommée `helpdesk`
   - Importer le fichier `docs/schema.sql`
4. Créer un utilisateur administrateur :
   ```sql
   INSERT INTO users (nom, prenom, email, mot_de_passe, role)
   VALUES ('Admin', 'Root', 'admin@example.com', '$2y$10$K9iIC1Q.C0C8TovUxLShE.v3v3WJX6MwcmmxSOZnZRAxLue7tKSuS', 'admin');

ps: le mdp est hashé donc il donne : admin123

### 🧩 Stack technique
- PHP 8.2
- MySQL 8.0
- Bootstrap 5.3
- XAMPP 8.2.4
