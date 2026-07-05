# Planificateur d'Entraînement Sportif

## Présentation du Projet

### 🎯 Objectif du Site

Le **Planificateur d'Entraînement Sportif** est une application web conçue pour permettre aux utilisateurs de créer, gérer et planifier leurs séances d'entraînement de manière intuitive et efficace. Le site offre une solution complète pour l'organisation d'activités sportives avec un système de gestion d'exercices personnalisé.

---

## 💻 Technologies Utilisées

### **Backend**

- **PHP** - Langage principal pour la logique serveur et les API
- **MySQL** - Base de données relationnelle pour le stockage des données
- **Sessions PHP** - Gestion de l'authentification et des sessions utilisateur

### **Frontend**

- **HTML5** - Structure et contenu des pages
- **CSS3** - Mise en forme et design responsive
- **JavaScript (Vanilla)** - Interactivité côté client et requêtes AJAX
- **jsPDF** - Génération de documents PDF

### **Architecture**

- **Modèle MVC** - Organisation claire du code
- **API REST** - Communication entre frontend et backend
- **AJAX** - Mise à jour dynamique sans rechargement de page

---

## ⚡ Fonctionnalités Principales

### **1. Système d'Authentification**

- Inscription et connexion sécurisées
- Hashage des mots de passe avec `password_hash()`
- Gestion des sessions utilisateur
- Protection des pages par authentification

### **2. Gestion des Exercices**

- **Création d'exercices personnalisés** avec :
  - Nom de l'exercice
  - Catégorie (Échauffement, Endurance, Vitesse, Agilité)
  - Description détaillée
  - Durée estimée
  - Matériel nécessaire
- **Modification et suppression** d'exercices existants
- **Filtrage par catégorie** pour une navigation facilitée
- **Interface en cartes retournables** pour afficher les détails

### **3. Planification de Séances**

- **Sélection de date** pour planifier des séances
- **Ajout d'exercices** à une séance par simple clic
- **Visualisation en temps réel** des exercices sélectionnés
- **Calcul automatique** de la durée totale de la séance
- **Réorganisation** possible des exercices dans la séance

### **4. Interface Utilisateur Avancée**

- **Design responsive** adapté à tous les écrans
- **Cartes interactives** avec effet de retournement (flip)
- **Filtres dynamiques** par catégorie d'exercice
- **Mise à jour en temps réel** sans rechargement de page
- **Interface intuitive** avec feedback visuel

### **5. Export et Sauvegarde**

- **Génération de PDF** des séances planifiées
- **Sauvegarde automatique** des séances en base de données
- **Historique** des séances par date
- **Export formaté** avec détails complets des exercices

---

## 🏗️ Architecture Technique

### **Structure de la Base de Données**

```
utilisateurs (id, email, password)
exercices (id, nom, categorie, description, duree, materiel)
seances (id, date_seance, user_id)
seance_exercices (id, seance_id, exercice_id, ordre)
joueurs (id, user_id, nom, poste, points_forts, points_faibles, commentaire_joueur, created_at)
joueur_seances (id, user_id, joueur_id, date_seance, intitule, commentaire, created_at)
joueur_matchs (id, user_id, joueur_id, date_match, adversaire, buts, passes_decisives, matchs_joues, created_at)
seance_joueurs (id, seance_id, joueur_id, created_at)
```

### **Organisation des Fichiers**

- `index.php` - Page d'authentification
- `home.php` - Page d'accueil
- `exercices.php` - Gestion des exercices
- `seances.php` - Planification des séances
- `equipe.php` - Suivi des joueurs, séances individuelles et statistiques de match
- `api_exercices.php` - API pour les exercices
- `js/app.js` - Logique JavaScript frontend
- `css/style.css` - Styles et design

### **Suivi équipe et séances planifiées**

- Les joueurs peuvent être affectés directement à une séance planifiée depuis `seances.php`
- Ces affectations sont enregistrées dans `seance_joueurs`
- Les séances affectées aux joueurs remontent automatiquement dans `equipe.php` pour le suivi global

### **Fonctionnalités Techniques**

- **API RESTful** pour les opérations CRUD
- **Requêtes AJAX** pour une expérience fluide
- **Gestion d'erreurs** complète côté client et serveur
- **Sécurité** avec préparation des requêtes SQL
- **Validation** des données côté client et serveur

---

## 🎨 Points Forts du Projet

### **Expérience Utilisateur**

- Interface moderne et intuitive
- Feedback visuel immédiat
- Navigation fluide sans rechargement
- Design adaptatif (responsive)

### **Fonctionnalités Avancées**

- Cartes interactives avec animations CSS
- Filtrage dynamique en temps réel
- Génération de PDF personnalisés
- Calcul automatique des durées

### **Qualité Technique**

- Code structuré et modulaire
- Sécurité renforcée (sessions, requêtes préparées)
- Gestion d'erreurs robuste
- Performance optimisée avec AJAX

---

## 🚀 Cas d'Usage

### **Pour les Sportifs Individuels**

- Planification de séances d'entraînement personnalisées
- Suivi de la progression et des activités
- Organisation par type d'exercice et durée

### **Pour les Coaches Sportifs**

- Création de programmes d'entraînement
- Gestion de multiple séances
- Export de plans d'entraînement pour les clients

### **Applications Pédagogiques**

- Cours d'éducation physique
- Clubs sportifs
- Formation en ligne

---

## 💡 Perspectives d'Évolution

- **Statistiques** et graphiques de progression
- **Partage** de séances entre utilisateurs
- **Application mobile** avec synchronisation
- **Intégration** avec objets connectés (montres, capteurs)
- **Calendrier avancé** avec notifications
- **Système de favoris** et recommandations

ameliorations possibles :

- Gestion de l'historique d'entrainement
- Accès par utilisateurs.
- Gestion des exercices par sport
