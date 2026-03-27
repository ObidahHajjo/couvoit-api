<div align="center">
<img src="https://img.shields.io/badge/-%F0%9F%9A%97%20COVOIT%20BACKEND-0a0a0a?style=for-the-badge&labelColor=0a0a0a" />

### Plateforme de Covoiturage — Laravel 12

<p>
  <img src="https://img.shields.io/badge/Laravel-12-red?style=for-the-badge&logo=laravel" />
  <img src="https://img.shields.io/badge/PHP-8.2+-blue?style=for-the-badge&logo=php" />
  <img src="https://img.shields.io/badge/PostgreSQL-DB-blue?style=for-the-badge&logo=postgresql" />
  <img src="https://img.shields.io/badge/Auth-JWT-black?style=for-the-badge&logo=jsonwebtokens" />
  <img src="https://img.shields.io/badge/Tests-PHPUnit_11-purple?style=for-the-badge&logo=php" />
  <img src="https://img.shields.io/badge/Architecture-SOLID-black?style=for-the-badge" />
  <img src="https://img.shields.io/badge/Realtime-Reverb-orange?style=for-the-badge" />
</p>

<p>
  <a href="README.md">English</a> &nbsp;|&nbsp;🌐 <strong>Français</strong> &nbsp;|&nbsp; <a href="README.ar.md">العربية</a>
</p>

</div>

API REST de covoiturage **orientée production**, développée avec **Laravel 12**, **PHP 8.2+**, **PostgreSQL** et un système **JWT local personnalisé** basé sur des **cookies httpOnly**.

Le projet applique une **architecture en couches propre** avec séparation stricte des responsabilités, Policies d'autorisation, messagerie temps réel via Reverb, cache structuré, emails transactionnels Resend et couverture de tests robuste.

> ⚠️ **Note importante** : les routes API de cette application ne suivent **pas** le préfixe `/api` par défaut de Laravel. Les endpoints sont exposés directement : `/auth/login`, `/trips`, `/cars`, etc.

---

# 📚 Sommaire

- [🏗 Architecture](#-architecture)
- [🧱 Stack Technique](#-stack-technique)
- [📦 Modèle Métier](#-modèle-métier)
- [🔐 Authentification](#-authentification)
- [💬 Messagerie Temps Réel](#-messagerie-temps-réel)
- [📧 Emails Transactionnels](#-emails-transactionnels)
- [🚀 Installation](#-installation)
- [⚙ Configuration](#-configuration)
- [📖 Documentation API](#-documentation-api)
- [🧪 Tests & Qualité](#-tests--qualité)
- [📌 Cache](#-cache)
- [🛡 Autorisation](#-autorisation)
- [📍 Création d'un trajet](#-création-dun-trajet)
- [🔄 Tâches planifiées](#-tâches-planifiées)
- [🌍 Déploiement](#-déploiement)
- [📁 Structure du projet](#-structure-du-projet)
- [📊 Endpoints](#-endpoints)
- [🔄 Roadmap](#-roadmap)
- [🔧 Dépannage](#-dépannage)
- [👤 Auteur](#-auteur)

---

# 🏗 Architecture

Architecture en couches strictement séparées :

```
HTTP
└── Controllers
    └── Requests (validation)
    └── Resources (transformation)

Application
└── Services (orchestration métier)
    ├── DTOs (normalisation des entrées)
    └── Resolvers (résolution des références)

Domaine
└── Models (Eloquent)
└── Policies (autorisation)

Infrastructure
├── Repositories (interfaces + implémentations Eloquent)
├── Support/Cache (RepositoryCacheManager)
├── Security (JWT issuer)
└── Clients (OpenRouteService)
```

Les bindings Service/Repository sont enregistrés dans `AppServiceProvider` et `RepositoryProvider`.

### 🔎 Principes appliqués

- Séparation stricte des responsabilités (Single Responsibility)
- Repository Pattern (interfaces + implémentations Eloquent)
- DTOs pour validation et normalisation des entrées
- Authorization via Policies (avec bypass admin)
- Cache avec tags (read-through / write-through)
- Broadcasting temps réel via Laravel Reverb
- Emails transactionnels via Resend
- Documentation OpenAPI (Swagger / l5-swagger)
- Tests unitaires et feature robustes (PHPUnit 11)
- CI/CD via GitHub Actions + SonarQube

---

# 🧱 Stack Technique

| Technologie              | Usage                                   |
| ------------------------ | --------------------------------------- |
| Laravel 12               | Framework principal                     |
| PHP 8.2+                 | Langage (CI/Docker cible PHP 8.5)       |
| PostgreSQL               | Base de données relationnelle (runtime) |
| SQLite                   | Base de données pour tests/CI           |
| firebase/php-jwt         | Émission et vérification JWT locale     |
| laravel/reverb           | Broadcasting WebSocket temps réel       |
| pusher/pusher-php-server | Compatible Reverb                       |
| PHPUnit 11               | Tests unitaires et feature              |
| darkaonline/l5-swagger   | Documentation OpenAPI                   |
| resend/resend-laravel    | Emails transactionnels                  |
| predis/predis            | Client Redis                            |
| OpenRouteService         | Géocodage & calcul distance/durée       |
| Laravel Pint             | Style de code                           |
| SonarQube                | Qualité de code (CI)                    |

---

# 📦 Modèle Métier

## Entités principales

- **User** — identité authentifiée
- **Person** — profil agrégat lié via `users.person_id`
- **Car** — véhicule appartenant à une personne
- **Trip** — trajet publié par un conducteur
- **Reservation** — réservation d'un passager pour un trajet
- **Conversation / ConversationMessage** — messagerie privée liée aux trajets
- **Brand / CarModel / Type / Color** — données catalogue véhicule
- **Address / City** — données géographiques
- **Role** — rôle utilisateur (`admin` / `user`)

## Règles métier importantes

### 👤 Person / User

- L'inscription crée simultanément un `Person` et un `User`
- Possède un seul véhicule (optionnel) via `persons.car_id`
- Possède un rôle (`admin` ou `user`)
- Peut être désactivée (`is_active = false`) — accès bloqué par le middleware
- Les comptes supprimés (soft delete) peuvent être restaurés à la reconnexion pendant **90 jours**
- Après 90 jours : anonymisation automatique par la commande `accounts:purge-deleted`

### 🚗 Trip

- `available_seats > 0`
- `distance_km > 0`
- Ne peut pas être annulé s'il a déjà commencé
- Création : géocodage ORS → calcul distance/durée → persistance + `arrival_time` dérivé

### 📌 Reservation

- Clé primaire composite (`person_id + trip_id`)
- Le conducteur ne peut pas réserver son propre trajet
- Pas de double réservation
- Pas de sur-réservation (vérification des sièges disponibles)
- Impossible sur un trajet déjà commencé

### 💬 Conversation

- Fils de discussion à deux parties autour d'un trajet
- Un conducteur peut contacter un passager de son trajet
- Un passager peut contacter le conducteur d'un trajet

---

# 🔐 Authentification (Local JWT)

L'API utilise un système d'authentification local basé sur JWT (HS256). Elle ne dépend d'aucun service tiers : génération, validation et rotation des tokens sont entièrement gérées côté serveur.

## 🧩 Architecture d'authentification

| Token           | Durée                     | Usage                      |
| --------------- | ------------------------- | -------------------------- |
| `access_token`  | Court terme (ex: 1h)      | Accès aux routes protégées |
| `refresh_token` | Long terme (ex: 30 jours) | Renouvellement du JWT      |

## 🔄 Flux d'authentification

### 1️⃣ L'utilisateur s'inscrit ou se connecte via :

```
POST /auth/register
POST /auth/login
```

### 2️⃣ Le serveur :

- Vérifie les identifiants et le statut `is_active`
- Hash le mot de passe (bcrypt)
- Génère un `access_token` JWT signé HS256
- Génère un `refresh_token` aléatoire (`random_bytes(32)`)
- Stocke le refresh token haché en base (`refresh_tokens`)
- Pose les tokens en cookies HTTP-only sécurisés
- Retourne un message de succès JSON sans exposer `access_token` ni `refresh_token`

### 3️⃣ Le client envoie le JWT via :

```
Authorization: Bearer <access_token>
```

ou, dans le cas nominal navigateur/frontend, via le cookie `access_token` (HTTP-only).

> Le frontend Covoit utilise désormais exclusivement les cookies de session ; il ne lit plus de jetons dans les réponses JSON d'authentification.

### 4️⃣ Le middleware `jwt` (`LocalJwtAuth`) :

- Vérifie la signature (HS256)
- Vérifie les claims `iss`, `aud`, `exp`
- Résout `sub` → `User`
- Cache le mapping token → user (TTL aligné sur l'expiration du token)
- Charge `auth()->user()`

---

# 🧾 Structure du JWT

```json
{
    "iss": "couvoit-api",
    "aud": "couvoit-client",
    "iat": 1700000000,
    "exp": 1700000900,
    "sub": "12",
    "role_id": 1,
    "user_id": 1,
    "jti": "a1b2c3d4e5f6..."
}
```

## 🔎 Claims utilisés

| Claim     | Description                     |
| --------- | ------------------------------- |
| `iss`     | Émetteur                        |
| `aud`     | Audience                        |
| `sub`     | Identifiant interne utilisateur |
| `exp`     | Expiration                      |
| `role_id` | Rôle utilisateur                |
| `jti`     | Identifiant unique du token     |

---

# 🔁 Refresh Token (Rotation Sécurisée)

## Le refresh token :

- Est généré via `random_bytes(32)`
- Seule la valeur brute est retournée au client
- Est stocké **haché** en base (`refresh_tokens`) avec date d'expiration
- Est révoqué à chaque rotation

## 🔄 Endpoint

```
POST /auth/refresh
```

## Processus :

1. Vérification du refresh token fourni (généralement lu depuis le cookie `refresh_token`)
2. Révocation du token utilisé
3. Génération d'un nouveau couple `access_token` + `refresh_token`

### Cette stratégie protège contre :

- Vol de token
- Replay attack
- Réutilisation après compromission

## 🔒 Déconnexion

```
POST /auth/logout
```

Supprime **tous** les refresh tokens de l'utilisateur authentifié.

---

# 🔒 Sécurité

- Mot de passe hashé via **bcrypt**
- JWT signé via **HS256** avec secret long (≥ 32 bytes)
- Rotation des refresh tokens à chaque renouvellement
- Support de révocation complète (logout)
- Utilisateurs inactifs (`is_active = false`) bloqués par le middleware
- Middleware JWT centralisé (`LocalJwtAuth`)
- Cookie HTTP-only avec `Secure` et `SameSite` configurables

---

# ⚙ Configuration `.env`

```env
# JWT
JWT_SECRET=base64:...
JWT_ACCESS_TTL=3600
JWT_REFRESH_TTL=2592000
JWT_ISSUER=couvoit-api
JWT_AUDIENCE=couvoit-client

# Cookies Auth
AUTH_COOKIE_PATH=/
AUTH_COOKIE_DOMAIN=null
AUTH_COOKIE_SECURE=false
AUTH_COOKIE_SAMESITE=lax
```

---

# 💬 Messagerie Temps Réel

La messagerie temps réel est gérée via **Laravel Reverb** (compatible Pusher).

## Architecture

- Broadcasting configuré dans `bootstrap/app.php`
- Autorisation des canaux privés dans `routes/channels.php`
- Événement : `App\Events\ChatMessageSent` (implémente `ShouldBroadcastNow`)
- Authentification broadcast exposée via `POST /broadcasting/auth-proxy`

## Canaux privés

```
chat.user.{personId}
chat.conversation.{conversationId}
support.session.{sessionId}
support.admins
```

## Endpoints de messagerie

| Méthode | Endpoint                                      | Description                                |
| ------- | --------------------------------------------- | ------------------------------------------ |
| GET     | `/conversations`                              | Liste des conversations                    |
| GET     | `/conversations/{conversation}`               | Détail d'une conversation                  |
| POST    | `/conversations/{conversation}/messages`      | Envoyer un message                         |
| POST    | `/trips/{trip}/contact-driver`                | Contacter le conducteur                    |
| POST    | `/my-trips/{trip}/contact-passenger/{person}` | Contacter un passager                      |
| POST    | `/support-chat/sessions`                      | Ouvrir ou retrouver une session de support |
| GET     | `/support-chat/sessions/{session}/messages`   | Historique des messages de support         |
| POST    | `/support-chat/sessions/{session}/messages`   | Envoyer un message de support              |
| POST    | `/support-chat/sessions/{session}/close`      | Clore une session de support               |

> Des alias `/chat/conversations...` sont également disponibles.

## Lancer Reverb en local

```bash
php artisan reverb:start
```

---

# 📧 Emails Transactionnels

Les emails sont envoyés via **Resend** avec des templates configurables.

## Événements couverts

- Réinitialisation de mot de passe
- Réservation créée (passager & conducteur)
- Annulation de réservation (passager & conducteur)
- Annulation de trajet (passagers)

> La documentation des variables de templates Resend est disponible dans `docs/resend-trip-templates.md`.

> ⚠️ L'envoi d'emails se fait **synchroniquement** (pas de queue job) après commit en base.

---

# 🚀 Installation

## 1️⃣ Cloner le projet

```bash
git clone https://github.com/votre-compte/couvoit-api.git
cd couvoit-api
```

## 2️⃣ Installer les dépendances

```bash
composer install
```

## 3️⃣ Configuration environnement

```bash
cp .env.example .env
php artisan key:generate
```

## 4️⃣ Configurer `.env`

```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=covoiturage
DB_USERNAME=postgres
DB_PASSWORD=postgres

CACHE_STORE=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

OPENROUTESERVICE_API_KEY=your_ors_key

MAIL_MAILER=resend
RESEND_API_KEY=your_resend_key
```

## 5️⃣ Migration base de données

```bash
php artisan migrate
# Optionnel :
php artisan db:seed
```

## 6️⃣ Lancer le serveur

```bash
php artisan serve
```

### API accessible sur :

```
http://localhost:8000
```

### (Optionnel) Broadcasting temps réel :

```bash
php artisan reverb:start
```

### (Optionnel) Queue worker :

```bash
php artisan queue:listen --tries=1 --timeout=0
```

---

# 📖 Documentation API

## Générer la documentation :

```bash
php artisan l5-swagger:generate
```

### Swagger UI accessible via :

```
/api/documentation
```

### Spec générée via :

```
/docs
```

Les annotations sont scannées depuis `app/Http/Controllers`, `app/Http/Requests` et `app/Swagger`.

---

# 🧪 Tests & Qualité

## Lancer tous les tests :

```bash
php artisan test
# ou
composer test
```

## Lancer un test spécifique :

```bash
php artisan test --filter=TripServiceTest
php artisan test --filter=ChatControllerTest
```

## Générer le rapport de couverture (Clover) :

```bash
php artisan test --coverage-clover=coverage.xml
```

## Style de code (Laravel Pint) :

```bash
./vendor/bin/pint
```

## Convention de tests :

- Chaque méthode inclut `@throws Throwable`
- Utilisation de `Model::query()->create()`
- Couverture complète : Services, Policies, Repositories, DTOs, Resources, Middleware, Controllers

## Domaines couverts :

- Flux auth (register, login, refresh, logout, forgot/reset password)
- Middleware JWT
- Endpoints chat/conversations
- Services et Repositories
- DTOs et Resources
- Policies d'autorisation
- Comportement des modèles Eloquent

## CI/CD (GitHub Actions)

Le workflow `.github/workflows/tests.yml` :

1. Installe les dépendances Composer
2. Prépare `.env` (SQLite)
3. Exécute les migrations
4. Lance les tests
5. Génère la couverture de code
6. Exécute le scan SonarQube (si secrets configurés)

---

# 📌 Cache

Le cache est une composante réelle de l'architecture, pas juste un défaut framework.

## Architecture

- Coordination centralisée dans `app/Support/Cache/RepositoryCacheManager.php`
- Tags définis pour : persons, cars, brands, models, cities, colors, trips, reservations, types
- Route model bindings cachés pour : `person`, `trip`, `brand`, `car`
- Middleware JWT cache le mapping token → user (TTL aligné sur l'expiration)
- Création de trajet : réponses ORS géocodage/routage cachées **24h**

## Exemples de clés :

```
persons:all
person:{id}
cities:{name}:{postal}
trips:all
trip:{id}
```

- TTL par défaut : **3600 secondes**
- Invalidation automatique lors des opérations create/update/delete
- Redis recommandé en production (nécessaire pour les cache tags)

> ⚠️ L'application utilise intensivement les cache tags — **Redis est indispensable en production**.

---

# 🛡 Autorisation

## Policies principales :

| Policy         | Modèle couvert                                    |
| -------------- | ------------------------------------------------- |
| `PersonPolicy` | Gestion des profils et rôles                      |
| `CarPolicy`    | Gestion des véhicules                             |
| `TripPolicy`   | Publication, modification, annulation des trajets |

## Comportement clé :

- **Admins** : bypass via `before()` sur toutes les policies
- **Utilisateurs** : gestion de leur propre profil et véhicule uniquement
- **Conducteurs** : publication de trajets réservée aux drivers (`canPublishTrip()`)
- **Propriétaires de trajets** : seuls autorisés à modifier/annuler leurs trajets
- **Helpers sur `User`** : `isAdmin()`, `isDriver()`, `canPublishTrip()`, `canBookTrip()`

## Bypass admin :

```php
public function before(Person $user): ?bool
{
    return $user->isAdmin() ? true : null;
}
```

---

# 📍 Création d'un trajet

1. Validation via DTO
2. Vérification que le conducteur possède une voiture
3. Résolution des références (Brand, Type, Model, Color, Addresses)
4. Géocodage des deux points via ORS (avec cache 24h)
5. Calcul distance & durée via ORS (avec cache 24h)
6. Calcul de `arrival_time` dérivé
7. Persistance du trajet
8. Retour du modèle rafraîchi avec relations

---

# 🔄 Tâches planifiées

Déclarées dans `routes/console.php` :

| Commande                 | Fréquence         | Description                                           |
| ------------------------ | ----------------- | ----------------------------------------------------- |
| `auth:clear-resets`      | Toutes les 15 min | Nettoyage des tokens de réinitialisation expirés      |
| `accounts:purge-deleted` | Quotidien         | Anonymisation des comptes supprimés depuis > 90 jours |

### Exécution manuelle :

```bash
php artisan accounts:purge-deleted
```

> Le scheduler Laravel doit être lancé toutes les minutes sur votre serveur.

---

# 🌍 Déploiement

## Stack recommandée :

- VPS (ex : Hetzner)
- Ubuntu 22.04+
- Apache ou Nginx (pointer vers `public/`)
- UFW Firewall
- Cloudflare DNS
- SSL Let's Encrypt
- **Redis en production** (obligatoire pour les cache tags)
- Reverb en processus séparé (si chat temps réel activé)
- Scheduler Laravel (`* * * * * php artisan schedule:run`)

## Points d'attention production :

- Configurer `APP_URL` et `FRONTEND_URL` correctement (liens générés, cookies)
- `AUTH_COOKIE_SECURE=true` en HTTPS
- Revoir `AUTH_COOKIE_SAMESITE` selon le contexte
- Fournir `JWT_SECRET`, `OPENROUTESERVICE_API_KEY`, `RESEND_API_KEY` et les template IDs Resend
- Générer et publier la doc Swagger si approprié

## Dockerfile

Un `Dockerfile` basé sur `php:8.5-apache` est fourni avec les extensions PostgreSQL et Redis activées, et Apache configuré pour servir depuis `public/`.

---

# 📁 Structure du projet

```
app/
  Console/Commands/         Commandes opérationnelles (purge, clear-resets)
  DTOS/                     Objets de données d'entrée (cars, trips...)
  Events/                   Événements de broadcast (ChatMessageSent)
  Exceptions/               Mapping exceptions → réponses API
  Http/
    Controllers/            Endpoints API
    Middleware/             Middleware JWT personnalisé (LocalJwtAuth)
    Requests/               Objets de validation
    Resources/              Transformateurs de réponses JSON
  Models/                   Modèles Eloquent
  Policies/                 Policies d'autorisation
  Providers/                ServiceProvider, RepositoryProvider, RouteBindings
  Repositories/             Interfaces + implémentations Eloquent
  Resolvers/                Résolution références / adresses
  Security/                 Contrats et implémentation JWT issuer
  Services/                 Services applicatifs
  Support/Cache/            Gestion clés/tags cache (RepositoryCacheManager)
  Swagger/                  Définitions bootstrap OpenAPI
bootstrap/
  app.php                   Routing, aliases middleware, exceptions
config/                     Configuration framework et intégrations
database/
  factories/
  migrations/
  seeders/
docs/                       Notes complémentaires (templates Resend...)
routes/
  api.php                   Routes API (sans préfixe /api)
  channels.php              Autorisation canaux broadcast
  console.php               Commandes planifiées
tests/                      Tests unitaires et feature (PHPUnit 11)
```

---

# 📊 Endpoints

## 🔐 Authentification — Routes Publiques

| Méthode | Endpoint                | Description                                  |
| ------- | ----------------------- | -------------------------------------------- |
| POST    | `/auth/register`        | Inscription                                  |
| POST    | `/auth/login`           | Connexion                                    |
| POST    | `/auth/refresh`         | Renouvellement de session via cookie refresh |
| POST    | `/auth/logout`          | Déconnexion (révocation tokens)              |
| GET     | `/auth/me`              | Profil de l'utilisateur courant              |
| POST    | `/auth/forgot-password` | Demande de réinitialisation                  |
| POST    | `/auth/reset-password`  | Réinitialisation du mot de passe             |

> Les endpoints d'authentification retournent un message de succès et définissent les cookies de session. Ils n'exposent plus les jetons dans la réponse JSON.

## 👤 Persons

| Méthode | Endpoint                            | Description                    |
| ------- | ----------------------------------- | ------------------------------ |
| GET     | `/persons`                          | Liste des utilisateurs         |
| GET     | `/persons/{person}`                 | Détail d'un utilisateur        |
| GET     | `/persons/{person}/trips-driver`    | Trajets en tant que conducteur |
| GET     | `/persons/{person}/trips-passenger` | Trajets en tant que passager   |
| POST    | `/persons`                          | Création d'un utilisateur      |
| PATCH   | `/persons/role`                     | Mise à jour du rôle            |
| PATCH   | `/persons/{person}`                 | Mise à jour d'un utilisateur   |
| DELETE  | `/persons/{person}`                 | Suppression d'un utilisateur   |

## 🚗 Trajets

| Méthode | Endpoint                                      | Description                                                                           |
| ------- | --------------------------------------------- | ------------------------------------------------------------------------------------- |
| GET     | `/trips`                                      | Liste des trajets                                                                     |
| GET     | `/trips/{trip}`                               | Détail d'un trajet, incluant désormais `driver.car` (plaque, modèle, marque, couleur) |
| GET     | `/trips/{trip}/person`                        | Liste des passagers                                                                   |
| POST    | `/trips`                                      | Création d'un trajet                                                                  |
| PATCH   | `/trips/{trip}`                               | Mise à jour d'un trajet                                                               |
| PATCH   | `/trips/{trip}/cancel`                        | Annulation d'un trajet                                                                |
| DELETE  | `/trips/{trip}`                               | Suppression d'un trajet                                                               |
| POST    | `/trips/{trip}/person`                        | Réservation d'un siège                                                                |
| DELETE  | `/trips/{trip}/reservations`                  | Annulation d'une réservation                                                          |
| POST    | `/trips/{trip}/contact-driver`                | Contacter le conducteur                                                               |
| POST    | `/my-trips/{trip}/contact-passenger/{person}` | Contacter un passager                                                                 |

## 🏷 Marques & Catalogue

| Méthode | Endpoint         | Description         |
| ------- | ---------------- | ------------------- |
| GET     | `/brands`        | Liste des marques   |
| GET     | `/brand/{brand}` | Détail d'une marque |

## 🚘 Voitures

| Méthode | Endpoint       | Description               |
| ------- | -------------- | ------------------------- |
| GET     | `/cars`        | Liste des voitures        |
| GET     | `/cars/{car}`  | Détail d'une voiture      |
| GET     | `/cars/search` | Recherche catalogue       |
| POST    | `/cars`        | Création d'une voiture    |
| PUT     | `/cars/{car}`  | Mise à jour complète      |
| DELETE  | `/cars/{car}`  | Suppression d'une voiture |

## 💬 Conversations

| Méthode | Endpoint                                 | Description               |
| ------- | ---------------------------------------- | ------------------------- |
| GET     | `/conversations`                         | Liste des conversations   |
| GET     | `/conversations/{conversation}`          | Détail d'une conversation |
| POST    | `/conversations/{conversation}/messages` | Envoyer un message        |
| POST    | `/broadcasting/auth-proxy`               | Auth canaux privés Reverb |

## 🆘 Support temps réel

| Méthode | Endpoint                                    | Description                                         |
| ------- | ------------------------------------------- | --------------------------------------------------- |
| POST    | `/support-chat/sessions`                    | Crée ou retrouve une session de support utilisateur |
| GET     | `/support-chat/sessions/{session}`          | Détail d'une session de support                     |
| GET     | `/support-chat/sessions/{session}/messages` | Liste des messages de support                       |
| POST    | `/support-chat/sessions/{session}/messages` | Envoi de message de support                         |
| POST    | `/support-chat/sessions/{session}/close`    | Clôture de session                                  |

## 🩺 Santé

| Méthode | Endpoint | Description               |
| ------- | -------- | ------------------------- |
| GET     | `/up`    | Health check              |
| GET     | `/`      | Ping (`{"message":"ok"}`) |

## 📌 Remarques importantes

- Toutes les routes protégées passent par le middleware `jwt` (`LocalJwtAuth`)
- Les autorisations sont gérées via `CarPolicy`, `TripPolicy` et `PersonPolicy`
- Les administrateurs bénéficient d'un bypass via `before()`
- Les utilisateurs inactifs (`is_active = false`) sont bloqués au niveau middleware
- Les routes n'utilisent **pas** le préfixe `/api` par défaut

---

# 🔧 Dépannage

### `Missing Bearer token`

- Envoyer `Authorization: Bearer <access_token>` pour un client non navigateur, ou s'appuyer sur le cookie `access_token`
- Vérifier que la requête atteint bien une route protégée et que les cookies sont transmis

### `Token expired` ou échecs d'auth inattendus

- Appeler `POST /auth/refresh` avec un cookie `refresh_token` valide
- Si la config a changé : `php artisan config:clear`
- Si le cache est incohérent : `php artisan optimize:clear`

### Les détails véhicule n'apparaissent pas côté frontend

- Vérifier `GET /trips/{trip}` dans la réponse JSON
- Confirmer que `TripResource` expose `driver.car`, `driver.car.model.brand` et `driver.car.color`
- Si la couleur textuelle apparaît sans pastille, vérifier la présence de `driver.car.color.hex_code`

### La création de trajet échoue avec des erreurs ORS

- Vérifier `OPENROUTESERVICE_API_KEY`
- Confirmer que les adresses de départ/arrivée sont géocodables par ORS
- Consulter les logs : `php artisan pail --timeout=0`

### Le chat temps réel ne reçoit pas d'événements

- Vérifier `BROADCAST_CONNECTION=reverb`
- S'assurer que `php artisan reverb:start` est lancé
- Confirmer que le client s'authentifie via `POST /broadcasting/auth-proxy`
- Vérifier que l'utilisateur appartient au canal privé demandé

### Erreurs de cache tags ou lectures incohérentes

- Préférer Redis en environnement runtime (nécessaire pour les tagged caches)
- Après changement de config ou de driver :

```bash
php artisan optimize:clear
```

### Les emails ne sont pas envoyés

- Vérifier `MAIL_MAILER=resend` et `RESEND_API_KEY`
- Vérifier les template IDs Resend dans `.env`
- Si les template IDs sont vides, l'envoi est silencieusement ignoré

### `composer setup` ou `composer dev` échoue sur les commandes npm

- Ce dépôt backend n'inclut pas de `package.json` committé
- Utiliser directement les commandes PHP/Artisan

---

# 🔄 Roadmap

- Redis en production (configuration complète)
- Dispatch des emails via Laravel Queue jobs
- Architecture événementielle
- Versioning API (`/v1/...`)
- Rate limiting par rôle
- Dockerisation complète (docker-compose)
- CI/CD GitHub Actions complet
- WebSockets pour trajets temps réel (statuts live)

---

# 👤 Auteur

### Obidah Hajjo

### Full Stack Developer
