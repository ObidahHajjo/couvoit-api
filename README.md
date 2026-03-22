# Couvoit API

Production-oriented Laravel backend for a carpooling platform. The API manages authentication, user profiles, vehicles, trip publishing and booking, driver/passenger messaging, email notifications, and generated OpenAPI documentation.

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Tech Stack and Integrations](#tech-stack-and-integrations)
- [Architecture](#architecture)
- [Core Domains and Modules](#core-domains-and-modules)
- [Authentication and Authorization](#authentication-and-authorization)
- [Realtime Chat and Broadcasting](#realtime-chat-and-broadcasting)
- [API Documentation](#api-documentation)
- [Environment Configuration](#environment-configuration)
- [Local Setup](#local-setup)
- [Development Workflow](#development-workflow)
- [Testing and Quality](#testing-and-quality)
- [Caching, Mail, Queues, and Scheduled Tasks](#caching-mail-queues-and-scheduled-tasks)
- [Deployment Considerations](#deployment-considerations)
- [Project Structure](#project-structure)
- [Troubleshooting](#troubleshooting)

## Overview

Couvoit API is a backend application built with Laravel 12 and PHP 8.2+ for a ride-sharing domain.

The current codebase includes:

- local JWT-based authentication with refresh token rotation
- profile and role management through `User` and `Person` aggregates
- car management and trip search/publication/reservation flows
- private trip conversations with realtime broadcast events
- Swagger/OpenAPI generation through `l5-swagger`
- ORS-backed geocoding and routing for trip distance and duration
- Resend-based transactional emails for password reset and trip events
- repository/service layering with policy-based authorization and cache helpers

Important routing note: this application intentionally runs API routes without Laravel's default `/api` prefix. Endpoints such as login and trips are exposed as `/auth/login` and `/trips`, not `/api/auth/login` and `/api/trips`.

## Key Features

- JWT auth with access tokens, refresh token rotation, logout, `me`, forgot password, and reset password
- Role-aware permissions for admins, drivers, and passengers
- Person profile completion and account lifecycle handling, including soft deletion and delayed personal-data purge
- Vehicle creation, lookup, update, and search with brand/model/type/color references
- Trip creation with ORS geocoding and route summary calculation
- Seat reservation and cancellation flows with business-rule checks
- Driver/passenger conversations tied to trips, plus realtime message broadcasting
- OpenAPI docs generated from PHP attributes/annotations in controllers, requests, and Swagger bootstrap classes

## Tech Stack and Integrations

- `Laravel 12`
- `PHP 8.2+` in app requirements; CI and Dockerfile currently target PHP 8.5
- `firebase/php-jwt` for local JWT issuance and verification
- `laravel/reverb` and `pusher/pusher-php-server` for broadcasting and WebSocket-compatible realtime messaging
- `darkaonline/l5-swagger` for OpenAPI/Swagger UI
- `resend/resend-laravel` for password reset and trip notification emails
- `predis/predis` is installed; `.env.example` currently defaults Redis client selection to `phpredis`
- `OpenRouteService` for address geocoding and route distance/duration lookup
- `PHPUnit 11` for unit and feature testing
- `Laravel Pint` for code style tooling
- `SonarQube` support in CI via `sonar-project.properties` and `.github/workflows/tests.yml`

## Architecture

The codebase follows a layered Laravel backend structure rather than a pure CRUD layout.

- `Controllers` handle HTTP concerns, validation handoff, resources, and authorization entry points
- `Requests` define validation rules for incoming payloads
- `Services` contain application workflows and domain orchestration
- `Repositories` wrap persistence concerns behind interfaces and Eloquent implementations
- `Resolvers` normalize and resolve reference data such as addresses and car catalog relations
- `Policies` enforce authorization rules at the model/use-case boundary
- `Resources` shape API responses
- `Support/Cache/RepositoryCacheManager` centralizes tagged cache keys and invalidation strategy

Service and repository bindings are registered in `app/Providers/AppServiceProvider.php` and `app/Providers/RepositoryProvider.php`.

## Core Domains and Modules

### Auth and Accounts

- `User` is the authenticated identity
- `Person` is the profile aggregate linked through `users.person_id`
- registration creates both a `Person` and a `User`
- refresh tokens are stored hashed in `refresh_tokens`
- soft-deleted accounts can be restored on login during the 90-day retention window
- accounts older than 90 days after soft deletion can be anonymized by the purge command

### Profiles and Roles

- persons can complete or update their own profile
- admins can list persons and update roles
- role-derived behavior is implemented in `App\Models\User` with helpers such as `isAdmin()`, `isDriver()`, `canPublishTrip()` and `canBookTrip()`

### Cars and Catalog Data

- cars belong to persons through `persons.car_id`
- catalog references include brands, models, types, and colors
- car creation/update uses DTOs and reference resolvers instead of directly trusting raw payloads
- `/cars/search` provides catalog-oriented car lookup for UI flows

### Trips and Reservations

- trips belong to a driver (`persons.id`)
- passengers are attached through the `reservations` pivot table
- trip creation resolves addresses, geocodes both endpoints, computes route duration and distance, and stores derived values such as `arrival_time`
- reservation logic prevents self-booking, duplicate booking, overbooking, and actions on already-started trips

### Conversations and Messages

- conversations are two-party threads around a trip
- messages are stored in `conversation_messages`
- a driver can contact a passenger on their own trip
- a passenger can contact the driver of a trip
- messages broadcast a `chat.message.sent` event to private channels

### Operations and Housekeeping

- scheduled password reset cleanup via `auth:clear-resets`
- scheduled personal-data purge via `accounts:purge-deleted`
- health endpoint available at `/up`
- root endpoint `/` returns a simple JSON `{"message":"ok"}` response

## Authentication and Authorization

### Auth Flow

Public endpoints:

- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/refresh`
- `POST /auth/forgot-password`
- `POST /auth/reset-password`

Protected endpoints use the custom `jwt` middleware defined in `app/Http/Middleware/LocalJwtAuth.php`.

The middleware accepts either:

- an `access_token` HTTP-only cookie, or
- an `Authorization: Bearer <token>` header

At login/register/refresh time, the API returns token data in JSON and also sets secure-by-configuration HTTP-only cookies.

### JWT Details

JWT settings are configured in `config/jwt.php`:

- `JWT_SECRET`
- `JWT_ISSUER`
- `JWT_AUDIENCE`
- `JWT_ACCESS_TTL`
- `JWT_REFRESH_TTL`

The middleware verifies signature and claims, resolves the authenticated user, and caches token-to-user identity lookups with a TTL aligned to token expiration.

### Refresh Tokens

- refresh tokens are generated server-side
- plain values are only returned to the client
- stored values are hashed in the database
- refresh rotates the token through the refresh token repository
- logout deletes all refresh tokens for the authenticated user

### Authorization

Authorization is handled with Laravel policies:

- `app/Policies/PersonPolicy.php`
- `app/Policies/CarPolicy.php`
- `app/Policies/TripPolicy.php`

High-level behavior in the current codebase:

- admins bypass most policy checks via `before()`
- non-admin users can only manage their own profile and car
- only drivers can publish trips
- only trip owners can update/cancel their trips
- admins can manage cross-user operations such as role changes and broader data access

## Realtime Chat and Broadcasting

Realtime chat support is present in the repository.

- broadcasting is wired in `bootstrap/app.php`
- private channel authorization lives in `routes/channels.php`
- chat events use `App\Events\ChatMessageSent`
- the event implements `ShouldBroadcastNow`, so broadcasting is immediate rather than queued
- broadcast auth is exposed through `POST /broadcasting/auth-proxy`

Relevant chat endpoints:

- `GET /conversations`
- `GET /conversations/{conversation}`
- `POST /conversations/{conversation}/messages`
- `POST /trips/{trip}/contact-driver`
- `POST /my-trips/{trip}/contact-passenger/{person}`

The API also exposes duplicate `/chat/conversations...` aliases for conversation listing, detail, and message sending.

Reverb-related configuration is defined in:

- `config/broadcasting.php`
- `config/reverb.php`

Default private channels used by the app:

- `chat.user.{personId}`
- `chat.conversation.{conversationId}`

## API Documentation

Swagger/OpenAPI support is built in with `l5-swagger`.

- Swagger UI route: `/api/documentation`
- generated docs route: `/docs`
- annotations are scanned from `app/Http/Controllers`, `app/Http/Requests`, and `app/Swagger`

Generate docs with:

```bash
php artisan l5-swagger:generate
```

OpenAPI bootstrap definitions live in:

- `app/Swagger/OpenApi.php`
- `app/Swagger/Bootstrap.php`

## Environment Configuration

Start from `.env.example` and adjust for your environment.

### Core Application

```env
APP_NAME=Laravel
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:3000
```

`FRONTEND_URL` is used when generating password reset URLs.

### Database

The repository defaults to PostgreSQL for normal runtime configuration:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=covoiturage
DB_USERNAME=postgres
DB_PASSWORD=postgres
```

SQLite is also configured and used by tests/CI:

```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

### JWT and Auth Cookies

```env
JWT_SECRET=base64:replace_me
JWT_ISSUER=couvoit-api
JWT_AUDIENCE=couvoit-client
JWT_ACCESS_TTL=3600
JWT_REFRESH_TTL=2592000

AUTH_COOKIE_PATH=/
AUTH_COOKIE_DOMAIN=null
AUTH_COOKIE_SECURE=false
AUTH_COOKIE_SAMESITE=lax
```

### Redis and Cache

```env
CACHE_STORE=redis
REDIS_CLIENT=phpredis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_PREFIX=couvoit_
```

Because the app uses cache tags heavily, Redis is the safest production choice.

### Reverb / Broadcasting

```env
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=local-app
REVERB_APP_KEY=local-key
REVERB_APP_SECRET=local-secret
REVERB_HOST=localhost
REVERB_PORT=8080
REVERB_SCHEME=http
REVERB_SERVER_HOST=0.0.0.0
REVERB_SERVER_PORT=8080
```

### Mail / Resend

```env
MAIL_MAILER=resend
MAIL_FROM_ADDRESS="contact@ohajjo.online"
MAIL_FROM_NAME="${APP_NAME}"
RESEND_API_KEY=
RESEND_RESET_PASSWORD_TEMPLATE_ID=
RESEND_TRIP_RESERVATION_PASSENGER_TEMPLATE_ID=
RESEND_TRIP_RESERVATION_DRIVER_TEMPLATE_ID=
RESEND_TRIP_CANCELLED_PASSENGER_TEMPLATE_ID=
RESEND_TRIP_RESERVATION_CANCEL_PASSENGER_TEMPLATE_ID=
RESEND_TRIP_RESERVATION_CANCEL_DRIVER_TEMPLATE_ID=
```

Trip template variable guidance is documented in `docs/resend-trip-templates.md`.

### OpenRouteService

```env
OPENROUTESERVICE_API_KEY=
```

Trip creation depends on a valid ORS API key when route computation is needed.

## Local Setup

### Prerequisites

- PHP 8.2+
- Composer 2
- PostgreSQL if using the default app setup
- Redis if you want production-like cache/broadcast behavior
- a valid ORS key for route calculation
- a valid Resend key if you want real email delivery

### Standard Backend Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
```

Optional seed step:

```bash
php artisan db:seed
```

Then start the API server:

```bash
php artisan serve
```

The API is then typically available at `http://localhost:8000`.

### Realtime / Broadcast Setup

If you want chat broadcasting locally, start Reverb in a separate process:

```bash
php artisan reverb:start
```

### Logs and Queue Worker

Useful local commands already reflected by `composer.json`:

```bash
php artisan queue:listen --tries=1 --timeout=0
php artisan pail --timeout=0
```

### About `composer setup` and `composer dev`

The repository keeps these Composer scripts:

- `composer setup`
- `composer dev`

They currently include `npm install`, `npm run build`, and `npm run dev`, but this repository does not include a committed `package.json` at the moment. For backend-only work, use the direct Composer and Artisan commands shown above instead of relying on those scripts unchanged.

## Development Workflow

Typical backend workflow:

1. copy `.env.example` to `.env`
2. configure database, Redis, JWT, ORS, and optional Resend values
3. run migrations
4. start the HTTP server
5. start Reverb if testing realtime chat
6. run tests before pushing changes

Useful commands from the current repository:

```bash
composer test
php artisan test
php artisan test --filter=ChatControllerTest
php artisan config:clear
php artisan optimize:clear
php artisan l5-swagger:generate
```

## Testing and Quality

The test suite is split into unit and feature tests through `phpunit.xml`.

Covered areas include:

- auth controller flows
- JWT middleware behavior
- chat endpoints
- services
- repositories
- DTOs
- resources
- policies
- Eloquent model behavior

Run the suite with:

```bash
php artisan test
```

Generate a Clover coverage file for Sonar or CI:

```bash
php artisan test --coverage-clover=coverage.xml
```

Code style tooling is available via Laravel Pint:

```bash
./vendor/bin/pint
```

CI is defined in `.github/workflows/tests.yml` and currently:

- installs Composer dependencies
- prepares `.env`
- runs SQLite migrations
- runs tests
- generates coverage
- optionally runs a SonarQube scan when secrets are configured

## Caching, Mail, Queues, and Scheduled Tasks

### Caching

Caching is a real part of the application design, not just a framework default.

- repository-level cache coordination lives in `app/Support/Cache/RepositoryCacheManager.php`
- route model bindings also cache lookups for `person`, `trip`, `brand`, and `car`
- auth middleware caches token fingerprint to user mappings
- trip creation caches ORS geocoding and route responses for 24 hours

The cache manager defines tagged keys for persons, cars, brands, models, cities, colors, trips, reservations, and types.

### Mail

Mail delivery is supported through Laravel mail configuration, with Resend actively used by the codebase for:

- password reset notifications
- reservation created notifications
- reservation cancellation notifications
- driver trip cancellation notifications

Trip email sending happens after database commit, but it is currently executed synchronously by the service layer rather than dispatched as Laravel queue jobs.

### Queues

Queue configuration exists in `config/queue.php`, and `.env.example` defaults to:

```env
QUEUE_CONNECTION=database
```

The CI/test environment overrides this to `sync`, and the repository's `composer dev` script starts `php artisan queue:listen`. At present, the main user-visible workflows in this codebase do not depend on custom queued jobs to function.

### Scheduled Tasks

Scheduled commands are declared in `routes/console.php`:

- `auth:clear-resets` every 15 minutes
- `accounts:purge-deleted` daily

Manual purge command:

```bash
php artisan accounts:purge-deleted
```

## Deployment Considerations

Deployment details depend on your environment, but the current repository suggests the following operational needs.

- run the app behind a standard Laravel-compatible web server setup pointing to `public/`
- configure `APP_URL` and `FRONTEND_URL` correctly for generated links and cookie behavior
- use Redis for cache tags and for Reverb scaling if you run multiple instances
- run a separate Reverb process if realtime chat is enabled in the target environment
- run Laravel scheduler every minute so password reset cleanup and account purge tasks execute
- set `AUTH_COOKIE_SECURE=true` and review `AUTH_COOKIE_SAMESITE` in HTTPS environments
- provide valid `JWT_SECRET`, `OPENROUTESERVICE_API_KEY`, and any required `RESEND_*` template IDs
- generate and publish Swagger docs only as appropriate for the environment

The repository also includes a basic `Dockerfile` based on `php:8.5-apache` with PostgreSQL and Redis extensions enabled and Apache configured to serve from `public/`.

## Project Structure

```text
app/
  Console/Commands/         Operational commands
  DTOS/                     Input data objects for car flows
  Events/                   Broadcast events
  Exceptions/               API exception mapping and domain exceptions
  Http/
    Controllers/            API endpoints
    Middleware/             Custom JWT middleware
    Requests/               Validation request objects
    Resources/              JSON response transformers
  Models/                   Eloquent models
  Policies/                 Authorization policies
  Providers/                Service, repo, auth, and route binding registration
  Repositories/             Interfaces and Eloquent implementations
  Resolvers/                Reference/address resolution logic
  Security/                 JWT issuer contracts and implementation
  Services/                 Application services
  Support/Cache/            Cache key/tag management
  Swagger/                  OpenAPI bootstrap definitions
bootstrap/
  app.php                   Routing, middleware aliases, exception wiring
config/                     Framework and integration configuration
database/
  factories/
  migrations/
  seeders/
docs/                       Supplemental integration notes
routes/                     API, channel, and console routes
tests/                      Unit and feature tests
```

## Troubleshooting

### `Missing Bearer token`

- send `Authorization: Bearer <access_token>` or rely on the `access_token` cookie returned by auth endpoints
- confirm the request is hitting a protected route and cookies are being sent for the configured domain/path

### `Token expired` or unexpected auth failures

- call `POST /auth/refresh` with a valid refresh token
- if configuration changed, run `php artisan config:clear`
- if cached auth state becomes stale during development, run `php artisan optimize:clear`

### Trip creation fails with ORS errors

- verify `OPENROUTESERVICE_API_KEY`
- confirm the departure and arrival addresses can be geocoded by ORS
- check app logs with `php artisan pail --timeout=0`

### Realtime chat is not receiving events

- verify `BROADCAST_CONNECTION=reverb`
- ensure `php artisan reverb:start` is running
- confirm the client authenticates against `POST /broadcasting/auth-proxy`
- check that the authenticated user belongs to the requested private channel

### Cache tag errors or inconsistent cached reads

- prefer Redis in runtime environments because the application relies heavily on tagged caches
- clear caches after changing environment or cache driver settings:

```bash
php artisan optimize:clear
```

### Emails are not sent

- verify `MAIL_MAILER=resend`
- verify `RESEND_API_KEY` and the template IDs used by the codebase
- note that if template IDs are blank, trip emails are skipped intentionally

### `composer setup` or `composer dev` fails on npm commands

- this backend repository currently has no committed `package.json`
- run the PHP and Artisan commands directly instead of those Composer scripts unless you add the missing frontend tooling
