# 🚗 Couvoit API
### Plateforme de Covoiturage — Laravel 12

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12-red?style=for-the-badge&logo=laravel" />
  <img src="https://img.shields.io/badge/PostgreSQL-DB-blue?style=for-the-badge&logo=postgresql" />
  <img src="https://img.shields.io/badge/Supabase-JWT-green?style=for-the-badge&logo=supabase" />
  <img src="https://img.shields.io/badge/Tests-PHPUnit-purple?style=for-the-badge&logo=php" />
  <img src="https://img.shields.io/badge/Architecture-SOLID-black?style=for-the-badge" />
</p>

API REST de covoiturage **orientée production**, développée avec **Laravel 12**, **PostgreSQL** et **Supabase Auth (JWT)**.

Le projet applique une **architecture propre (Clean Architecture)** avec séparation stricte des responsabilités, Policies d’autorisation, cache structuré et couverture de tests robuste.

---

# 📚 Sommaire

- [🏗 Architecture](#-architecture)
- [🧱 Stack Technique](#-stack-technique)
- [📦 Modèle Métier](#-modèle-métier)
- [🔐 Authentification](#-authentification)
- [🚀 Installation](#-installation)
- [📖 Documentation API](#-documentation-api)
- [🧪 Tests](#-tests)
- [📌 Cache](#-cache)
- [🛡 Autorisation](#-autorisation)
- [📍 Création d’un trajet](#-création-dun-trajet)
- [🌍 Déploiement](#-déploiement)
- [📊 Endpoints](#-endpoints)
- [🔄 Roadmap](#-roadmap)
- [👤 Auteur](#-auteur)
---

# 🏗 Architecture

Architecture en couches :
HTTP
└── Controllers

Application
└── Services
├── DTOs
└── Resolvers

Domaine
└── Models
└── Policies

Infrastructure
└── Repositories Eloquent
└── Client Supabase
└── Client ORS


### 🔎 Principes appliqués

- Séparation stricte des responsabilités
- Repository Pattern (interfaces + implémentations Eloquent)
- DTO pour validation et normalisation
- Authorization via Policies
- Cache avec tags (read-through / write-through)
- Documentation OpenAPI (Swagger)
- Tests unitaires robustes

---

# 🧱 Stack Technique

| Technologie | Usage |
|-------------|--------|
| Laravel 12 | Framework principal |
| PostgreSQL | Base de données relationnelle |
| PHPUnit | Tests unitaires |
| Swagger | Documentation OpenAPI |
| OpenRouteService | Géocodage & calcul distance |
| Redis (optionnel) | Cache production |

---

# 📦 Modèle Métier

## Entités principales

- Person
- Car
- Trip
- Reservation
- Brand
- CarModel
- Type
- Color
- Address
- City
- Role

## Règles métier importantes

### 👤 Person
- Possède un seul véhicule (optionnel)
- Possède un rôle (`admin` ou `user`)
- Peut être désactivée (`is_active`)

### 🚗 Trip
- `available_seats > 0`
- `distance_km > 0`
- Ne peut pas être annulé s’il a commencé

### 📌 Reservation
- Clé primaire composite (`person_id + trip_id`)
- Le conducteur ne peut pas réserver son propre trajet

---

# 🔐 Authentification (Local JWT)
L’API utilise un système d’authentification local basé sur JWT (HS256).
Elle ne dépend plus de Supabase : la génération, la validation et la rotation des tokens sont entièrement gérées côté serveur.

## 🧩 Architecture d’authentification
| Token | Durée | Usage |
|----------|---|------------|
| access_token | Court terme (ex: 15 min) | Accès aux routes protégées |
| refresh_token | Long terme (ex: 30 jours) | Renouvellement du JWT |

## 🔄 Flux d’authentification
### 1️⃣ L’utilisateur s’inscrit ou se connecte via :
```POST /api/register
POST /api/login
```

### 2️⃣ Le serveur :
- Vérifie les identifiants
- Hash le mot de passe (bcrypt)
- Génère un access_token JWT
- Génère un refresh_token aléatoire
- Stocke le refresh token haché en base

### 3️⃣ Le client envoie le JWT dans :
```
Authorization: Bearer <access_token>
```

### 4️⃣ Le middleware jwt :
- Vérifie la signature (HS256)
- Vérifie iss, aud, exp
-Résout sub → User
-Charge auth()->user()
---

# 🧾 Structure du JWT
Exemple de payload :
```
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
| Claim | Description |
|----------|---|
| iss | Émetteur |
| aud | Audience |
| sub | Identifiant interne utilisateur |
| exp | Expiration |
| role_id | Rôle utilisateur |
| jti | Identifiant unique du token |

---

# 🔁 Refresh Token (Rotation Sécurisée)
## Le refresh token :
- Est généré via random_bytes(32)
- Est stocké haché en base (refresh_tokens)
- Possède une date d’expiration
- Est révoqué lors de chaque rotation

## 🔄 Endpoint
```
POST /api/refresh
```

## Processus :
### 1. Vérification du refresh token
### 2. Révocation du token utilisé
### 3. Génération d’un nouveau couple :
-  access_token
-  refresh_token

## Cette stratégie protège contre :
- Vol de token
- Replay attack
- Réutilisation après compromission
---

# 🔒 Sécurité
- #### Mot de passe hashé via bcrypt
- #### JWT signé via HS256
- #### Secret long (≥ 32 bytes)
- #### Rotation des refresh tokens
- #### Support révocation
- #### Utilisateurs inactifs (is_active = false) bloqués
- #### Middleware centralisé

--- 
# ⚙ Configuration ``` .env```

```
JWT_SECRET=base64:...
JWT_ACCESS_TTL=900
JWT_REFRESH_TTL=2592000
JWT_ISSUER=couvoit-api
JWT_AUDIENCE=couvoit-client
```


--- 
# 🚀 Installation

## 1️⃣ Cloner le projet

```
git clone https://github.com/votre-compte/couvoit-api.git
cd couvoit-api
```

## 2️⃣ Installer les dépendances
```
composer install
```
## 3️⃣ Configuration environnement
```
cp .env.example .env
php artisan key:generate
```

## Configurer :
```
APP_ENV=local
APP_DEBUG=true

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=couvoit
DB_USERNAME=postgres
DB_PASSWORD=secret

CACHE_STORE=array
QUEUE_CONNECTION=sync

SUPABASE_URL=https://xxxx.supabase.co
SUPABASE_JWKS_URL=https://xxxx.supabase.co/auth/v1/keys

ORS_KEY=your_openrouteservice_key
```
## 4️⃣ Migration base de données
```
php artisan migrate
```

## 5️⃣ Lancer le serveur
```
php artisan serve
```
### API accessible sur :
```
http://localhost:8000
```

---
# 📖 Documentation API

## Générer la documentation :
```
php artisan l5-swagger:generate
```

### Accessible via :
```
/api/documentation
```

---
# 🧪 Tests

## Lancer tous les tests :
```
php artisan test
```
## Lancer un test spécifique :
```
php artisan test --filter=TripServiceTest
```

## Convention de tests :
- ### Chaque méthode inclut @throws Throwable
- ### Utilisation de Model::query()->create()
- ### Couverture complète des Services, Policies, Repositories et DTO


---
# 📌 Cache

## Exemples de clés :
```angular2html
persons:all
person:{id}
supabase:{uuid}
cities:{name}:{postal}
```
- ### TTL par défaut : 3600 secondes
- ### Invalidation automatique lors des opérations create/update/delete


---
# 🛡 Autorisation

## Policies principales :
- ### CarPolicy
- ### TripPolicy
- ### PersonPolicy

## Bypass admin :
```
public function before(Person $user): ?bool
{
    return $user->isAdmin() ? true : null;
}
```

--- 
# 📍 Création d’un trajet

1. ### Validation via DTO
2. ### Vérification que le conducteur possède une voiture
3. ### Résolution des références (Brand, Type, Model, Color)
4. ### Géocodage via ORS
5. ### Calcul distance & durée
6. ### Persistance du trajet
7. ### Retour du modèle rafraîchi avec relations


---

# 🌍 Déploiement

## Stack recommandée :
- ### VPS (ex: Hetzner)
- ### Ubuntu 22.04+
- ### Apache ou Nginx
- ### UFW Firewall
- ### Cloudflare DNS
- ### SSL Let’s Encrypt
- ### Redis en production


---

# 📊 Endpoints

## 🔐 Authentification - Routes Publiques
| Méthode | Endpoint | Description |
|----------|----------|------------|
| POST | `/api/register` | Inscription d’un utilisateur |
| POST | `/api/login` | Connexion utilisateur |
| POST | `/api/refresh` | Rafraîchissement du token JWT |

# 👤 Persons

| Méthode | Endpoint | Description |
|----------|----------|------------|
| GET | `/api/persons` | Liste des utilisateurs |
| GET | `/api/persons/{person}` | Détail d’un utilisateur |
| GET | `/api/persons/{person}/trips-driver` | Trajets en tant que conducteur |
| GET | `/api/persons/{person}/trips-passenger` | Trajets en tant que passager |
| POST | `/api/persons` | Création d’un utilisateur |
| PATCH | `/api/persons/role` | Mise à jour du rôle |
| PATCH | `/api/persons/{person}` | Mise à jour d’un utilisateur |
| DELETE | `/api/persons/{person}` | Suppression d’un utilisateur |

# 🚗 Trajets

| Méthode | Endpoint | Description |
|----------|----------|------------|
| GET | `/api/trips` | Liste des trajets |
| GET | `/api/trips/{trip}` | Détail d’un trajet |
| GET | `/api/trips/{trip}/person` | Liste des passagers |
| POST | `/api/trips` | Création d’un trajet |
| PATCH | `/api/trips/{trip}` | Mise à jour d’un trajet |
| PATCH | `/api/trips/{trip}/cancel` | Annulation d’un trajet |
| DELETE | `/api/trips/{trip}` | Suppression d’un trajet |
| POST | `/api/trips/{trip}/person` | Réservation d’un siège |
| DELETE | `/api/trips/{trip}/reservations` | Annulation d’une réservation |

# 🏷 Marques

| Méthode | Endpoint | Description |
|----------|----------|------------|
| GET | `/api/brands` | Liste des marques |
| GET | `/api/brand/{brand}` | Détail d’une marque |

## 🚗 Voitures
| Méthode | Endpoint | Description |
|----------|----------|------------|
| GET | `/api/cars` | Liste des voitures |
| GET | `/api/cars/{car}` | Détail d’une voiture |
| POST | `/api/cars` | Création d’une voiture |
| PUT | `/api/cars/{car}` | Mise à jour complète |
| DELETE | `/api/cars/{car}` | Suppression d’une voiture |

# 📌 Remarques importantes

- Toutes les routes protégées passent par le middleware `supabase.auth`
- Les autorisations sont gérées via :
    - `CarPolicy`
    - `TripPolicy`
    - `PersonPolicy`
- Les administrateurs bénéficient d’un bypass via `before()`
- Les utilisateurs inactifs (`is_active = false`) sont bloqués
- 
--- 
# 🔄 Roadmap
- #### Redis en production
- #### Architecture événementielle
- #### Versioning API
- #### Rate limiting par rôle
- #### WebSockets pour trajets temps réel
- #### CI/CD GitHub Actions
- #### Dockerisation complète


---
# 👤 Auteur

### Obidah Hajjo
### Full Stack Developer
