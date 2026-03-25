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
  🌐 <strong>English</strong> &nbsp;|&nbsp; <a href="README.md">Français</a> &nbsp;|&nbsp; <a href="README.ar.md">العربية</a>
</p>

</div>

A **production-ready** carpooling REST API built with **Laravel 12**, **PHP 8.2+**, **PostgreSQL**, and a **custom local JWT system** built around **httpOnly cookies**.

The project follows a **clean layered architecture** with strict separation of concerns, authorization Policies, real-time messaging via Reverb, structured caching, Resend transactional emails, and robust test coverage.

> ⚠️ **Important note**: this application intentionally does **not** use Laravel's default `/api` prefix. Endpoints are exposed directly: `/auth/login`, `/trips`, `/cars`, etc.

---

# 📚 Table of Contents

- [🏗 Architecture](#-architecture)
- [🧱 Tech Stack](#-tech-stack)
- [📦 Business Model](#-business-model)
- [🔐 Authentication](#-authentication)
- [💬 Real-Time Messaging](#-real-time-messaging)
- [📧 Transactional Emails](#-transactional-emails)
- [🚀 Installation](#-installation)
- [⚙ Configuration](#-configuration)
- [📖 API Documentation](#-api-documentation)
- [🧪 Tests & Quality](#-tests--quality)
- [📌 Cache](#-cache)
- [🛡 Authorization](#-authorization)
- [📍 Creating a Trip](#-creating-a-trip)
- [🔄 Scheduled Tasks](#-scheduled-tasks)
- [🌍 Deployment](#-deployment)
- [📁 Project Structure](#-project-structure)
- [📊 Endpoints](#-endpoints)
- [🔄 Roadmap](#-roadmap)
- [🔧 Troubleshooting](#-troubleshooting)
- [👤 Author](#-author)

---

# 🏗 Architecture

Strictly separated layered architecture:

```
HTTP
└── Controllers
    └── Requests (validation)
    └── Resources (transformation)

Application
└── Services (business orchestration)
    ├── DTOs (input normalization)
    └── Resolvers (reference resolution)

Domain
└── Models (Eloquent)
└── Policies (authorization)

Infrastructure
├── Repositories (interfaces + Eloquent implementations)
├── Support/Cache (RepositoryCacheManager)
├── Security (JWT issuer)
└── Clients (OpenRouteService)
```

Service/Repository bindings are registered in `AppServiceProvider` and `RepositoryProvider`.

### 🔎 Applied Principles

- Strict separation of concerns (Single Responsibility)
- Repository Pattern (interfaces + Eloquent implementations)
- DTOs for input validation and normalization
- Authorization via Policies (with admin bypass)
- Tagged cache (read-through / write-through)
- Real-time broadcasting via Laravel Reverb
- Transactional emails via Resend
- OpenAPI documentation (Swagger / l5-swagger)
- Robust unit and feature tests (PHPUnit 11)
- CI/CD via GitHub Actions + SonarQube

---

# 🧱 Tech Stack

| Technology               | Usage                                     |
| ------------------------ | ----------------------------------------- |
| Laravel 12               | Main framework                            |
| PHP 8.2+                 | Language (CI/Docker targets PHP 8.5)      |
| PostgreSQL               | Relational database (runtime)             |
| SQLite                   | Database for tests/CI                     |
| firebase/php-jwt         | Local JWT issuance and verification       |
| laravel/reverb           | Real-time WebSocket broadcasting          |
| pusher/pusher-php-server | Reverb compatible                         |
| PHPUnit 11               | Unit and feature tests                    |
| darkaonline/l5-swagger   | OpenAPI documentation                     |
| resend/resend-laravel    | Transactional emails                      |
| predis/predis            | Redis client                              |
| OpenRouteService         | Geocoding & distance/duration calculation |
| Laravel Pint             | Code style                                |
| SonarQube                | Code quality (CI)                         |

---

# 📦 Business Model

## Core Entities

- **User** — authenticated identity
- **Person** — profile aggregate linked via `users.person_id`
- **Car** — vehicle belonging to a person
- **Trip** — ride published by a driver
- **Reservation** — passenger booking for a trip
- **Conversation / ConversationMessage** — private messaging tied to trips
- **Brand / CarModel / Type / Color** — vehicle catalog data
- **Address / City** — geographic data
- **Role** — user role (`admin` / `user`)

## Key Business Rules

### 👤 Person / User

- Registration simultaneously creates a `Person` and a `User`
- Has one vehicle (optional) via `persons.car_id`
- Has a role (`admin` or `user`)
- Can be deactivated (`is_active = false`) — access blocked by middleware
- Soft-deleted accounts can be restored on login for **90 days**
- After 90 days: automatic anonymization by the `accounts:purge-deleted` command

### 🚗 Trip

- `available_seats > 0`
- `distance_km > 0`
- Cannot be cancelled once it has started
- Creation flow: ORS geocoding → distance/duration calculation → persistence + derived `arrival_time`

### 📌 Reservation

- Composite primary key (`person_id + trip_id`)
- Driver cannot book their own trip
- No duplicate bookings
- No overbooking (available seats are checked)
- Not possible on an already-started trip

### 💬 Conversation

- Two-party threads around a trip
- A driver can contact a passenger on their trip
- A passenger can contact the driver of a trip

---

# 🔐 Authentication (Local JWT)

The API uses a local JWT-based authentication system (HS256). It does not depend on any third-party service: token generation, validation, and rotation are entirely handled server-side.

## 🧩 Authentication Architecture

| Token           | Lifetime                  | Usage                      |
| --------------- | ------------------------- | -------------------------- |
| `access_token`  | Short-lived (e.g. 1h)     | Access to protected routes |
| `refresh_token` | Long-lived (e.g. 30 days) | JWT renewal                |

## 🔄 Authentication Flow

### 1️⃣ The user registers or logs in via:

```
POST /auth/register
POST /auth/login
```

### 2️⃣ The server:

- Verifies credentials and `is_active` status
- Hashes the password (bcrypt)
- Generates an HS256-signed JWT `access_token`
- Generates a random `refresh_token` (`random_bytes(32)`)
- Stores the hashed refresh token in the database (`refresh_tokens`)
- Sets the tokens as secure HTTP-only cookies
- Returns a success JSON message without exposing `access_token` or `refresh_token`

### 3️⃣ The client sends the JWT via:

```
Authorization: Bearer <access_token>
```

or, in the normal browser/frontend flow, via the `access_token` cookie (HTTP-only).

> The Covoit frontend now relies exclusively on session cookies and no longer reads auth tokens from JSON auth responses.

### 4️⃣ The `jwt` middleware (`LocalJwtAuth`):

- Verifies the signature (HS256)
- Verifies `iss`, `aud`, `exp` claims
- Resolves `sub` → `User`
- Caches the token → user mapping (TTL aligned with token expiration)
- Loads `auth()->user()`

---

# 🧾 JWT Structure

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

## 🔎 Claims Used

| Claim     | Description              |
| --------- | ------------------------ |
| `iss`     | Issuer                   |
| `aud`     | Audience                 |
| `sub`     | Internal user identifier |
| `exp`     | Expiration               |
| `role_id` | User role                |
| `jti`     | Unique token identifier  |

---

# 🔁 Refresh Token (Secure Rotation)

## The refresh token:

- Is generated via `random_bytes(32)`
- Only the raw value is returned to the client
- Is stored **hashed** in the database (`refresh_tokens`) with an expiration date
- Is revoked on every rotation

## 🔄 Endpoint

```
POST /auth/refresh
```

## Process:

1. Verification of the provided refresh token (usually read from the `refresh_token` cookie)
2. Revocation of the used token
3. Generation of a new `access_token` + `refresh_token` pair

### This strategy protects against:

- Token theft
- Replay attacks
- Reuse after compromise

## 🔒 Logout

```
POST /auth/logout
```

Deletes **all** refresh tokens for the authenticated user.

---

# 🔒 Security

- Password hashed via **bcrypt**
- JWT signed via **HS256** with a long secret (≥ 32 bytes)
- Refresh token rotation on every renewal
- Full revocation support (logout)
- Inactive users (`is_active = false`) blocked by middleware
- Centralized JWT middleware (`LocalJwtAuth`)
- HTTP-only cookie with configurable `Secure` and `SameSite` settings

---

# ⚙ Configuration `.env`

```env
# JWT
JWT_SECRET=base64:...
JWT_ACCESS_TTL=3600
JWT_REFRESH_TTL=2592000
JWT_ISSUER=couvoit-api
JWT_AUDIENCE=couvoit-client

# Auth Cookies
AUTH_COOKIE_PATH=/
AUTH_COOKIE_DOMAIN=null
AUTH_COOKIE_SECURE=false
AUTH_COOKIE_SAMESITE=lax
```

---

# 💬 Real-Time Messaging

Real-time messaging is handled via **Laravel Reverb** (Pusher-compatible).

## Architecture

- Broadcasting configured in `bootstrap/app.php`
- Private channel authorization in `routes/channels.php`
- Event: `App\Events\ChatMessageSent` (implements `ShouldBroadcastNow`)
- Broadcast authentication exposed via `POST /broadcasting/auth-proxy`

## Private Channels

```
chat.user.{personId}
chat.conversation.{conversationId}
support.session.{sessionId}
support.admins
```

## Messaging Endpoints

| Method | Endpoint                                      | Description                     |
| ------ | --------------------------------------------- | ------------------------------- |
| GET    | `/conversations`                              | List conversations              |
| GET    | `/conversations/{conversation}`               | Conversation detail             |
| POST   | `/conversations/{conversation}/messages`      | Send a message                  |
| POST   | `/trips/{trip}/contact-driver`                | Contact the driver              |
| POST   | `/my-trips/{trip}/contact-passenger/{person}` | Contact a passenger             |
| POST   | `/support-chat/sessions`                      | Open or reuse a support session |
| GET    | `/support-chat/sessions/{session}/messages`   | Support message history         |
| POST   | `/support-chat/sessions/{session}/messages`   | Send a support message          |
| POST   | `/support-chat/sessions/{session}/close`      | Close a support session         |

> `/chat/conversations...` aliases are also available.

## Start Reverb locally

```bash
php artisan reverb:start
```

---

# 📧 Transactional Emails

Emails are sent via **Resend** with configurable templates.

## Covered Events

- Password reset
- Reservation created (passenger & driver)
- Reservation cancellation (passenger & driver)
- Trip cancellation (passengers)

> Template variable documentation is available in `docs/resend-trip-templates.md`.

> ⚠️ Email sending is done **synchronously** (no queue job) after database commit.

---

# 🚀 Installation

## 1️⃣ Clone the project

```bash
git clone https://github.com/your-account/couvoit-api.git
cd couvoit-api
```

## 2️⃣ Install dependencies

```bash
composer install
```

## 3️⃣ Environment setup

```bash
cp .env.example .env
php artisan key:generate
```

## 4️⃣ Configure `.env`

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

## 5️⃣ Database migration

```bash
php artisan migrate
# Optional:
php artisan db:seed
```

## 6️⃣ Start the server

```bash
php artisan serve
```

### API available at:

```
http://localhost:8000
```

### (Optional) Real-time broadcasting:

```bash
php artisan reverb:start
```

### (Optional) Queue worker:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

---

# 📖 API Documentation

## Generate the documentation:

```bash
php artisan l5-swagger:generate
```

### Swagger UI available at:

```
/api/documentation
```

### Generated spec at:

```
/docs
```

Annotations are scanned from `app/Http/Controllers`, `app/Http/Requests`, and `app/Swagger`.

---

# 🧪 Tests & Quality

## Run all tests:

```bash
php artisan test
# or
composer test
```

## Run a specific test:

```bash
php artisan test --filter=TripServiceTest
php artisan test --filter=ChatControllerTest
```

## Generate coverage report (Clover):

```bash
php artisan test --coverage-clover=coverage.xml
```

## Code style (Laravel Pint):

```bash
./vendor/bin/pint
```

## Test conventions:

- Each method includes `@throws Throwable`
- Use of `Model::query()->create()`
- Full coverage: Services, Policies, Repositories, DTOs, Resources, Middleware, Controllers

## Covered areas:

- Auth flows (register, login, refresh, logout, forgot/reset password)
- JWT middleware
- Chat/conversation endpoints
- Services and Repositories
- DTOs and Resources
- Authorization Policies
- Eloquent model behavior

## CI/CD (GitHub Actions)

The `.github/workflows/tests.yml` workflow:

1. Installs Composer dependencies
2. Prepares `.env` (SQLite)
3. Runs migrations
4. Runs tests
5. Generates code coverage
6. Runs SonarQube scan (if secrets are configured)

---

# 📌 Cache

Caching is a real part of the architecture, not just a framework default.

## Architecture

- Centralized coordination in `app/Support/Cache/RepositoryCacheManager.php`
- Tags defined for: persons, cars, brands, models, cities, colors, trips, reservations, types
- Route model bindings cached for: `person`, `trip`, `brand`, `car`
- JWT middleware caches token → user mapping (TTL aligned with token expiration)
- Trip creation: ORS geocoding/routing responses cached for **24 hours**

## Example keys:

```
persons:all
person:{id}
cities:{name}:{postal}
trips:all
trip:{id}
```

- Default TTL: **3600 seconds**
- Automatic invalidation on create/update/delete operations
- Redis recommended in production (required for cache tags)

> ⚠️ The application relies heavily on cache tags — **Redis is mandatory in production**.

---

# 🛡 Authorization

## Main Policies:

| Policy         | Covered Model                          |
| -------------- | -------------------------------------- |
| `PersonPolicy` | Profile and role management            |
| `CarPolicy`    | Vehicle management                     |
| `TripPolicy`   | Trip publishing, editing, cancellation |

## Key behavior:

- **Admins**: bypass via `before()` on all policies
- **Users**: can only manage their own profile and vehicle
- **Drivers**: trip publishing reserved for drivers (`canPublishTrip()`)
- **Trip owners**: only they can update/cancel their trips
- **Helpers on `User`**: `isAdmin()`, `isDriver()`, `canPublishTrip()`, `canBookTrip()`

## Admin bypass:

```php
public function before(Person $user): ?bool
{
    return $user->isAdmin() ? true : null;
}
```

---

# 📍 Creating a Trip

1. Validation via DTO
2. Verification that the driver owns a car
3. Reference resolution (Brand, Type, Model, Color, Addresses)
4. Geocoding of both endpoints via ORS (with 24h cache)
5. Distance & duration calculation via ORS (with 24h cache)
6. Derived `arrival_time` calculation
7. Trip persistence
8. Return of the refreshed model with relations

---

# 🔄 Scheduled Tasks

Declared in `routes/console.php`:

| Command                  | Frequency    | Description                                      |
| ------------------------ | ------------ | ------------------------------------------------ |
| `auth:clear-resets`      | Every 15 min | Clean up expired password reset tokens           |
| `accounts:purge-deleted` | Daily        | Anonymize accounts deleted for more than 90 days |

### Manual execution:

```bash
php artisan accounts:purge-deleted
```

> The Laravel scheduler must run every minute on your server.

---

# 🌍 Deployment

## Recommended stack:

- VPS (e.g. Hetzner)
- Ubuntu 22.04+
- Apache or Nginx (pointing to `public/`)
- UFW Firewall
- Cloudflare DNS
- SSL Let's Encrypt
- **Redis in production** (mandatory for cache tags)
- Reverb as a separate process (if real-time chat is enabled)
- Laravel scheduler (`* * * * * php artisan schedule:run`)

## Production checklist:

- Configure `APP_URL` and `FRONTEND_URL` correctly (generated links, cookies)
- `AUTH_COOKIE_SECURE=true` over HTTPS
- Review `AUTH_COOKIE_SAMESITE` based on context
- Provide `JWT_SECRET`, `OPENROUTESERVICE_API_KEY`, `RESEND_API_KEY`, and Resend template IDs
- Generate and publish Swagger docs as appropriate

## Dockerfile

A `Dockerfile` based on `php:8.5-apache` is provided with PostgreSQL and Redis extensions enabled, and Apache configured to serve from `public/`.

---

# 📁 Project Structure

```
app/
  Console/Commands/         Operational commands (purge, clear-resets)
  DTOS/                     Input data objects (cars, trips...)
  Events/                   Broadcast events (ChatMessageSent)
  Exceptions/               Exception → API response mapping
  Http/
    Controllers/            API endpoints
    Middleware/             Custom JWT middleware (LocalJwtAuth)
    Requests/               Validation request objects
    Resources/              JSON response transformers
  Models/                   Eloquent models
  Policies/                 Authorization policies
  Providers/                ServiceProvider, RepositoryProvider, RouteBindings
  Repositories/             Interfaces + Eloquent implementations
  Resolvers/                Reference / address resolution
  Security/                 JWT issuer contracts and implementation
  Services/                 Application services
  Support/Cache/            Cache key/tag management (RepositoryCacheManager)
  Swagger/                  OpenAPI bootstrap definitions
bootstrap/
  app.php                   Routing, middleware aliases, exceptions
config/                     Framework and integration configuration
database/
  factories/
  migrations/
  seeders/
docs/                       Supplemental notes (Resend templates...)
routes/
  api.php                   API routes (no /api prefix)
  channels.php              Broadcast channel authorization
  console.php               Scheduled commands
tests/                      Unit and feature tests (PHPUnit 11)
```

---

# 📊 Endpoints

## 🔐 Authentication — Public Routes

| Method | Endpoint                | Description                        |
| ------ | ----------------------- | ---------------------------------- |
| POST   | `/auth/register`        | Registration                       |
| POST   | `/auth/login`           | Login                              |
| POST   | `/auth/refresh`         | Session renewal via refresh cookie |
| POST   | `/auth/logout`          | Logout (token revocation)          |
| GET    | `/auth/me`              | Current user profile               |
| POST   | `/auth/forgot-password` | Password reset request             |
| POST   | `/auth/reset-password`  | Password reset                     |

> Auth endpoints now return a success message and set session cookies. They no longer expose tokens in the JSON body.

## 👤 Persons

| Method | Endpoint                            | Description        |
| ------ | ----------------------------------- | ------------------ |
| GET    | `/persons`                          | List users         |
| GET    | `/persons/{person}`                 | User detail        |
| GET    | `/persons/{person}/trips-driver`    | Trips as driver    |
| GET    | `/persons/{person}/trips-passenger` | Trips as passenger |
| POST   | `/persons`                          | Create a user      |
| PATCH  | `/persons/role`                     | Update role        |
| PATCH  | `/persons/{person}`                 | Update a user      |
| DELETE | `/persons/{person}`                 | Delete a user      |

## 🚗 Trips

| Method | Endpoint                                      | Description                                                          |
| ------ | --------------------------------------------- | -------------------------------------------------------------------- |
| GET    | `/trips`                                      | List trips                                                           |
| GET    | `/trips/{trip}`                               | Trip detail, now including `driver.car` (plate, model, brand, color) |
| GET    | `/trips/{trip}/person`                        | List passengers                                                      |
| POST   | `/trips`                                      | Create a trip                                                        |
| PATCH  | `/trips/{trip}`                               | Update a trip                                                        |
| PATCH  | `/trips/{trip}/cancel`                        | Cancel a trip                                                        |
| DELETE | `/trips/{trip}`                               | Delete a trip                                                        |
| POST   | `/trips/{trip}/person`                        | Book a seat                                                          |
| DELETE | `/trips/{trip}/reservations`                  | Cancel a reservation                                                 |
| POST   | `/trips/{trip}/contact-driver`                | Contact the driver                                                   |
| POST   | `/my-trips/{trip}/contact-passenger/{person}` | Contact a passenger                                                  |

## 🏷 Brands & Catalog

| Method | Endpoint         | Description  |
| ------ | ---------------- | ------------ |
| GET    | `/brands`        | List brands  |
| GET    | `/brand/{brand}` | Brand detail |

## 🚘 Cars

| Method | Endpoint       | Description    |
| ------ | -------------- | -------------- |
| GET    | `/cars`        | List cars      |
| GET    | `/cars/{car}`  | Car detail     |
| GET    | `/cars/search` | Catalog search |
| POST   | `/cars`        | Create a car   |
| PUT    | `/cars/{car}`  | Full update    |
| DELETE | `/cars/{car}`  | Delete a car   |

## 💬 Conversations

| Method | Endpoint                                 | Description                 |
| ------ | ---------------------------------------- | --------------------------- |
| GET    | `/conversations`                         | List conversations          |
| GET    | `/conversations/{conversation}`          | Conversation detail         |
| POST   | `/conversations/{conversation}/messages` | Send a message              |
| POST   | `/broadcasting/auth-proxy`               | Reverb private channel auth |

## 🆘 Realtime Support

| Method | Endpoint                                    | Description                            |
| ------ | ------------------------------------------- | -------------------------------------- |
| POST   | `/support-chat/sessions`                    | Create or reuse a user support session |
| GET    | `/support-chat/sessions/{session}`          | Support session detail                 |
| GET    | `/support-chat/sessions/{session}/messages` | List support messages                  |
| POST   | `/support-chat/sessions/{session}/messages` | Send a support message                 |
| POST   | `/support-chat/sessions/{session}/close`    | Close a session                        |

## 🩺 Health

| Method | Endpoint | Description               |
| ------ | -------- | ------------------------- |
| GET    | `/up`    | Health check              |
| GET    | `/`      | Ping (`{"message":"ok"}`) |

## 📌 Important Notes

- All protected routes go through the `jwt` middleware (`LocalJwtAuth`)
- Authorization is handled via `CarPolicy`, `TripPolicy`, and `PersonPolicy`
- Admins benefit from a bypass via `before()`
- Inactive users (`is_active = false`) are blocked at the middleware level
- Routes do **not** use the default `/api` prefix

---

# 🔧 Troubleshooting

### `Missing Bearer token`

- Send `Authorization: Bearer <access_token>` for non-browser clients, or rely on the `access_token` cookie
- Verify the request is hitting a protected route and cookies are being transmitted

### `Token expired` or unexpected auth failures

- Call `POST /auth/refresh` with a valid `refresh_token` cookie
- If config changed: `php artisan config:clear`
- If cache is stale: `php artisan optimize:clear`

### Vehicle details do not appear in the frontend

- Check the `GET /trips/{trip}` JSON response
- Confirm `TripResource` exposes `driver.car`, `driver.car.model.brand`, and `driver.car.color`
- If the color label appears without the swatch, verify `driver.car.color.hex_code` is present

### Trip creation fails with ORS errors

- Verify `OPENROUTESERVICE_API_KEY`
- Confirm departure and arrival addresses are geocodable by ORS
- Check logs: `php artisan pail --timeout=0`

### Real-time chat not receiving events

- Verify `BROADCAST_CONNECTION=reverb`
- Ensure `php artisan reverb:start` is running
- Confirm the client authenticates via `POST /broadcasting/auth-proxy`
- Verify the authenticated user belongs to the requested private channel

### Cache tag errors or inconsistent cached reads

- Prefer Redis in runtime environments (required for tagged caches)
- After changing config or cache driver:

```bash
php artisan optimize:clear
```

### Emails are not sent

- Verify `MAIL_MAILER=resend` and `RESEND_API_KEY`
- Verify Resend template IDs in `.env`
- If template IDs are blank, email sending is silently skipped

### `composer setup` or `composer dev` fails on npm commands

- This backend repository has no committed `package.json`
- Use PHP/Artisan commands directly instead

---

# 🔄 Roadmap

- Redis in production (full configuration)
- Email dispatch via Laravel Queue jobs
- Event-driven architecture
- API versioning (`/v1/...`)
- Rate limiting per role
- Full Dockerization (docker-compose)
- Complete CI/CD GitHub Actions pipeline
- WebSockets for real-time trip status updates

---

# 👤 Author

### Obidah Hajjo

### Full Stack Developer
