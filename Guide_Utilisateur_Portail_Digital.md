# Guide Utilisateur — Portail Digital ANAC

> **Version :** 1.0.0-mvp  
> **Date :** Avril 2026  
> **Public cible :** Administrateurs et utilisateurs du Portail Digital

---

## Table des matières

1. [Présentation du Portail](#1-présentation-du-portail)
2. [Comment fonctionne le Portail](#2-comment-fonctionne-le-portail)
3. [Lancer le Portail sur votre machine](#3-lancer-le-portail-sur-votre-machine)
4. [Se connecter](#4-se-connecter)
5. [Le Tableau de bord](#5-le-tableau-de-bord)
6. [Les Applets](#6-les-applets)
7. [Mon Profil](#7-mon-profil)
8. [Mes Paramètres](#8-mes-paramètres)
9. [Panneau de contrôle — Administrateurs uniquement](#9-panneau-de-contrôle--administrateurs-uniquement)
10. [Gestion des utilisateurs](#10-gestion-des-utilisateurs)
11. [Gestion des Applets](#11-gestion-des-applets)
12. [Questions fréquentes](#12-questions-fréquentes)

---

## 1. Présentation du Portail

Le **Portail Digital** est une plateforme centrale qui regroupe toutes vos applications métier en un seul endroit. Au lieu d'avoir plusieurs sites avec plusieurs mots de passe, vous vous connectez **une seule fois** au Portail, et toutes vos applications sont accessibles depuis la même interface.

Imaginez le Portail comme un **tableau de bord de voiture** : vous n'avez pas besoin de savoir comment fonctionne le moteur pour conduire. Vous tournez la clé, et tout est disponible devant vous.

### Ce que le Portail vous permet de faire

- Accéder à toutes vos applications métier depuis un seul endroit
- Gérer les utilisateurs et leurs droits d'accès
- Ajouter ou retirer des applications selon les besoins
- Consulter votre historique d'activité
- Personnaliser votre interface (thème, informations personnelles)

---

## 2. Comment fonctionne le Portail

### Une analogie simple

Pensez à un **immeuble de bureaux sécurisé** :

- La **réception** (le Portail) vérifie votre identité à l'entrée
- Chaque **bureau** (une application métier) est accessible une fois que vous êtes passé par la réception
- Vous ne pouvez pas entrer directement dans un bureau sans passer par la réception
- La réceptionniste sait exactement à quels bureaux vous avez le droit d'accéder

De la même façon, le Portail :

1. Vérifie votre identité (email + mot de passe + code à usage unique)
2. Vous donne accès uniquement aux applications auxquelles vous êtes autorisé
3. Toutes les applications restent invisibles et inaccessibles sans passer par le Portail

### Architecture simplifiée

```
Vous (navigateur)
        │
        ▼
  ┌─────────────┐
  │   Portail   │  ← Votre point d'entrée unique
  └─────────────┘
        │
   ┌────┴─────┐
   │          │
   ▼          ▼
┌──────┐  ┌──────┐
│ CRM  │  │  RH  │  ← Applications isolées, invisibles depuis l'extérieur
└──────┘  └──────┘
```

---

## 3. Lancer le Portail sur votre machine

> Cette section s'adresse aux développeurs ou administrateurs techniques qui souhaitent faire tourner le Portail en local pour le développement ou les tests.

### Ce dont vous avez besoin

Avant de commencer, assurez-vous d'avoir installé sur votre machine :

- **Podman** ou **Docker** — le moteur qui fait tourner les conteneurs (pensez-y comme à une machine virtuelle légère)
- **podman-compose** ou **docker-compose** — l'outil qui orchestre plusieurs conteneurs ensemble
- **Git** — pour récupérer le code source
- **Composer** — le gestionnaire de dépendances PHP (à installer depuis [getcomposer.org](https://getcomposer.org))

### Étape 1 — Récupérer le code source

Ouvrez un terminal et tapez :

```bash
git clone <url-de-votre-dépôt> digital-portal
cd digital-portal
```

> Remplacez `<url-de-votre-dépôt>` par l'adresse de votre dépôt Git.  
> Si le dépôt est sur votre machine locale dans `/srv/git/` :

```bash
git clone /srv/git/digital-portal.git digital-portal
cd digital-portal
```

### Étape 2 — Configurer l'environnement

Le fichier `.env` contient les paramètres de configuration. Il est déjà présent dans le projet avec des valeurs par défaut pour le développement. Vous n'avez normalement pas besoin de le modifier pour un premier lancement.

Si besoin, vérifiez que le fichier contient bien :

```
APP_ENV=dev
MARIADB_DATABASE=portal
MARIADB_USER=portal_user
MAILER_DSN=log://default
```

### Étape 3 — Lancer les conteneurs

Cette commande démarre tous les services nécessaires (le Portail, la base de données, le serveur web) :

```bash
podman-compose up -d
```

Attendez environ 10 secondes le temps que tout démarre, puis vérifiez que tout fonctionne :

```bash
podman ps
```

Vous devriez voir trois lignes avec le statut **Up** :

```
digital-portal_portal_1   Up   ...
digital-portal_nginx_1    Up   0.0.0.0:8080->80/tcp
digital-portal_db_1       Up   ...
```

### Étape 4 — Initialiser la base de données

La première fois uniquement, il faut créer les tables en base de données :

```bash
podman exec -it digital-portal_portal_1 zsh
php bin/console doctrine:migrations:migrate
```

Tapez `yes` quand le système vous le demande.

### Étape 5 — Lancer le serveur de ressources visuelles (Vite)

Le Portail utilise un outil appelé **Vite** pour gérer les feuilles de style, les icônes et les animations de l'interface. Il doit tourner en parallèle du Portail pendant le développement.

Dans le terminal, à l'intérieur du conteneur (suite de l'étape 4) :

```bash
npm run dev
```

Vous devriez voir apparaître :

```
VITE v5.x.x  ready in xxx ms
➜  Local:   http://0.0.0.0:5173/
```

> **Important :** Laissez ce terminal ouvert. Vite doit continuer à tourner pendant que vous utilisez le Portail. Si vous fermez ce terminal, les styles de l'interface disparaîtront.

### Étape 6 — Accéder au Portail

Ouvrez votre navigateur et rendez-vous à l'adresse :

```
http://localhost:8080
```

Vous devriez voir la page de connexion du Portail.

### Résumé — Ordre de démarrage à chaque session

```
1. podman-compose up -d
2. podman exec -it digital-portal_portal_1 zsh
3. npm run dev          ← laisser ce terminal ouvert
4. Ouvrir http://localhost:8080 dans le navigateur
```

---

## 4. Se connecter

### Première connexion

1. Rendez-vous sur `http://localhost:8080`
2. Entrez votre **adresse email** et votre **mot de passe**
3. Cliquez sur **Continuer →**

### Le code de vérification (OTP)

Pour renforcer la sécurité, le Portail utilise une **double vérification** :

Après avoir entré votre email et mot de passe, un code à 6 chiffres est envoyé à votre adresse email. Ce code est valable **10 minutes**.

> **En environnement de développement :** les emails ne sont pas réellement envoyés. Le code apparaît dans les journaux du système. L'administrateur peut le retrouver avec la commande :
> ```bash
> tail -f var/log/dev.log | grep OTP
> ```

Entrez le code reçu et cliquez sur **Vérifier →**.

### Changement de mot de passe obligatoire

Si votre compte a été créé par un administrateur, vous serez automatiquement redirigé vers une page de changement de mot de passe lors de votre première connexion. Choisissez un mot de passe d'au moins **8 caractères**.

### Problèmes de connexion

| Problème | Solution |
|---|---|
| Email ou mot de passe incorrect | Vérifiez la saisie. Contactez l'administrateur si le problème persiste. |
| Code expiré | Recommencez la connexion depuis le début pour recevoir un nouveau code. |
| Compte inactif | Contactez l'administrateur. |

---

## 5. Le Tableau de bord

Après connexion, vous arrivez sur le **Tableau de bord**. C'est votre page d'accueil.

### Ce que vous y trouvez

**En haut :** La barre de navigation avec le nom du Portail, le sélecteur de thème, et votre avatar.

**À gauche :** Le menu de navigation avec :
- 🏠 **Dashboard** — retour à l'accueil
- Les **Applets** auxquels vous avez accès
- La section **Admin** (visible uniquement si vous êtes administrateur)

**Au centre :** Les cartes des applications disponibles avec leur statut (En ligne / Hors ligne).

### Naviguer sur mobile

Sur téléphone ou tablette, le menu de gauche est masqué. Appuyez sur l'icône ☰ en haut à gauche pour l'ouvrir.

---

## 6. Les Applets

Les **Applets** sont les applications métier accessibles depuis le Portail (CRM, RH, Notifications, etc.).

### Ouvrir une application

Cliquez sur le bouton **Ouvrir →** sur la carte de l'application, ou cliquez sur son nom dans le menu de gauche.

L'application s'affiche directement dans l'interface du Portail.

### Statuts des applications

| Statut | Signification |
|---|---|
| 🟢 En ligne | L'application est disponible |
| 🔴 Hors ligne | L'application est temporairement indisponible |
| 🟡 Maintenance | L'application est en cours de mise à jour |

### Application non visible ?

Si une application n'apparaît pas dans votre menu, c'est que vous n'avez pas encore les droits d'accès. Contactez votre administrateur pour qu'il vous attribue le rôle approprié.

---

## 7. Mon Profil

Cliquez sur votre **avatar** (cercle coloré en haut à droite) puis sur **👤 Profil**.

### Ce que vous y trouvez

- Votre **photo de profil** (sous forme d'initiales pour l'instant)
- Votre **nom**, **email** et **rôle(s)**
- La date de votre **dernière connexion**
- Votre **historique d'activité** (les 20 dernières actions enregistrées)

### Historique d'activité

L'historique liste vos actions récentes :

| Action | Signification |
|---|---|
| 🔑 Logged in | Connexion réussie |
| 🔐 Changed password | Mot de passe modifié |
| 👤 Updated profile | Informations de profil mises à jour |

---

## 8. Mes Paramètres

Cliquez sur votre **avatar** puis sur **⚙️ Paramètres**, ou accédez à `/profile/settings`.

Les paramètres sont organisés en trois onglets :

### Onglet Profil

Modifiez votre **prénom** et **nom de famille**. L'email ne peut pas être modifié directement — contactez l'administrateur.

Cliquez sur **Enregistrer le profil** pour sauvegarder.

### Onglet Mot de passe

Pour changer votre mot de passe :

1. Entrez votre **mot de passe actuel**
2. Entrez votre **nouveau mot de passe** (minimum 8 caractères)
3. Confirmez le nouveau mot de passe
4. Cliquez sur **Mettre à jour le mot de passe**

### Onglet Apparence

Choisissez le **thème visuel** de votre interface parmi :

| Thème | Description |
|---|---|
| Corporate | Professionnel, tons bleus (défaut) |
| Light | Clair et épuré |
| Dark | Sombre, pour les environnements peu éclairés |
| Forest | Sombre avec des nuances de vert |

Le thème choisi est **sauvegardé** et s'appliquera à chaque connexion, même depuis un autre navigateur.

---

## 9. Panneau de contrôle — Administrateurs uniquement

> Cette section est visible uniquement pour les comptes ayant le rôle **ROLE_ADMIN**.

Accédez au panneau via le menu de gauche → **🛠️ Panneau de contrôle**, ou à l'adresse `/admin`.

### Vue d'ensemble

Le tableau de bord administrateur affiche :

- Le nombre d'**applets enregistrées**
- Le nombre d'**utilisateurs**
- Des raccourcis vers les actions principales

---

## 10. Gestion des utilisateurs

Accédez à `/admin/users` via le menu **👤 Utilisateurs**.

### La liste des utilisateurs

Chaque utilisateur est affiché avec :
- Son **adresse email**
- Ses **rôles** (ROLE_USER, ROLE_ADMIN, ROLE_EDITOR)
- Son **statut** : Actif, Invitation en attente, Inactif

### Créer un utilisateur directement

Cliquez sur **＋ Créer un utilisateur**.

Remplissez :
- **Email** de l'utilisateur
- **Rôle(s)** à attribuer
- **Mot de passe** temporaire

L'utilisateur recevra ses identifiants par email et sera **obligé de changer son mot de passe** à la première connexion.

> En développement, l'email est enregistré dans `var/log/otp.log` plutôt qu'envoyé réellement.

### Inviter un utilisateur par email

Cliquez sur **📧 Inviter un utilisateur**.

Remplissez :
- **Email** de la personne à inviter
- **Rôle(s)** à attribuer

Un email contenant un lien d'activation est envoyé. Ce lien est valable **48 heures**. La personne clique sur le lien, choisit son mot de passe, et son compte est activé.

### Modifier un utilisateur

Cliquez sur le crayon ✏️ à droite de l'utilisateur pour :
- Modifier ses **rôles**
- **Activer ou désactiver** le compte

> L'email d'un utilisateur ne peut pas être modifié après création.

### Supprimer un utilisateur

Cliquez sur 🗑️ à droite de l'utilisateur. Une confirmation est demandée avant suppression définitive.

### Renvoyer une invitation

Si une invitation a expiré ou n'a pas été reçue, cliquez sur **📧 Renvoyer** pour générer un nouveau lien valable 48 heures.

### Les rôles expliqués

| Rôle | Accès |
|---|---|
| ROLE_USER | Accès au Portail et aux applets autorisées |
| ROLE_ADMIN | Accès complet + panneau d'administration |
| ROLE_EDITOR | Accès intermédiaire (selon configuration des applets) |

---

## 11. Gestion des Applets

Accédez à `/admin/applets` via **🛠️ Panneau de contrôle → Gérer →** ou directement depuis le menu.

### Enregistrer une nouvelle application

Cliquez sur **＋ Nouvelle Applet**.

Remplissez le formulaire :

| Champ | Description | Exemple |
|---|---|---|
| **Nom** | Nom affiché dans l'interface | Système RH |
| **Slug** | Identifiant URL (minuscules, sans espaces) | `sirh` |
| **URL** | Adresse interne du conteneur | `http://applet-sirh:8000` |
| **Icône** | Emoji représentant l'application | 👥 |
| **Statut** | État actuel de l'application | En ligne |
| **Catégorie** | Famille de l'application | Ressources Humaines |
| **Description** | Courte description | Gestion des agents et congés |
| **Rôles autorisés** | Qui peut voir et accéder à cette applet | ROLE_USER |

> **Le Slug est important** — c'est ce qui apparaît dans l'URL. Par exemple, un slug `sirh` donnera l'adresse `/applet/sirh`. Il ne peut pas contenir d'espaces ni de majuscules.

Cliquez sur **Enregistrer l'Applet**.

L'application apparaîtra immédiatement dans le menu de gauche pour tous les utilisateurs ayant le bon rôle.

### Modifier une application

Cliquez sur ✏️ à droite de l'applet. Vous pouvez modifier tous les champs sauf le slug.

Pour mettre une application temporairement hors ligne (maintenance), changez le **Statut** en `maintenance` ou `offline`. Elle disparaîtra du menu des utilisateurs sans être supprimée.

### Supprimer une application

Cliquez sur 🗑️. Une confirmation est demandée. La suppression retire l'applet du Portail mais **ne supprime pas l'application elle-même**.

---

## 12. Questions fréquentes

**Je n'ai pas reçu le code de vérification.**  
Vérifiez vos spams. En développement, le code n'est pas envoyé par email — l'administrateur doit le récupérer dans les journaux système.

**Mon code de vérification ne fonctionne plus.**  
Le code expire après 10 minutes. Retournez sur la page de connexion et recommencez depuis le début pour en recevoir un nouveau.

**Je ne vois pas une application dans le menu.**  
Votre compte n'a pas le rôle nécessaire pour accéder à cette application. Contactez votre administrateur.

**L'interface n'a plus de style (tout est en texte brut).**  
Le serveur Vite n'est pas démarré. L'administrateur technique doit lancer `npm run dev` dans le conteneur portal.

**J'ai oublié mon mot de passe.**  
Contactez votre administrateur. Il peut créer un nouveau compte ou vous envoyer une nouvelle invitation.

**Puis-je utiliser le Portail sur mobile ?**  
Oui. L'interface s'adapte aux petits écrans. Le menu de navigation est accessible via l'icône ☰ en haut à gauche.

**Comment changer de thème rapidement ?**  
Cliquez sur **🎨 Thème** dans la barre de navigation en haut à droite, et choisissez parmi les options disponibles. Pour sauvegarder votre choix de façon permanente, allez dans **Paramètres → Apparence**.

**Une application s'affiche mais me demande de me connecter à nouveau.**  
Certaines applications tierces ont leur propre système de connexion. Cela sera corrigé dans une prochaine version du Portail. Pour l'instant, connectez-vous directement à l'application depuis son interface interne, ou contactez votre administrateur.

---

*Guide rédigé en avril 2026 — Version 1.0.0-mvp*  
*Pour toute question technique, consultez le document `Symfony_Reproducible_Env_init.md`*
