<?php

namespace App\Swagger;

use OpenApi\Attributes as OA;

/**
 * Holds reusable OpenAPI schema annotations.
 */
#[OA\Schema(
    schema: 'AuthRequestPayload',
    required: ['email', 'password'],
    properties: [
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 6, example: 'secret123'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'RefreshRequestPayload',
    required: ['refresh_token'],
    properties: [
        new OA\Property(property: 'refresh_token', type: 'string', example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ChangePasswordPayload',
    required: ['current_password', 'password', 'password_confirmation'],
    properties: [
        new OA\Property(property: 'current_password', type: 'string', format: 'password', minLength: 8, example: 'secret123'),
        new OA\Property(property: 'password', type: 'string', format: 'password', minLength: 8, example: 'secret456'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', minLength: 8, example: 'secret456'),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'AuthSessionResponse',
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Authenticated successfully.'),
    ],
    type: 'object'
)]

/**
 * Cars
 */
#[OA\Schema(
    schema: 'StoreCarRequestPayload',
    required: ['brand', 'type', 'model', 'color', 'carregistration'],
    properties: [
        new OA\Property(
            property: 'brand',
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'peugeot'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'type',
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'berline'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'model',
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: '308'),
            ],
            type: 'object',
        ),
        new OA\Property(
            property: 'color',
            required: ['name', 'hex_code'],
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'bleu'),
                new OA\Property(property: 'hex_code', type: 'string', example: '#00AAFF'),
            ],
            type: 'object',
        ),
        new OA\Property(property: 'carregistration', type: 'string', example: 'AB-123-CD'),
        new OA\Property(property: 'seats', type: 'integer', maximum: 9, minimum: 1, example: 5),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UpdateCarRequestPayload',
    properties: [
        new OA\Property(property: 'carregistration', type: 'string', example: 'AB-123-CD', nullable: true),
        new OA\Property(property: 'license_plate', type: 'string', example: 'AB-123-CD', nullable: true),
        new OA\Property(
            property: 'color',
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'bleu', nullable: true),
                new OA\Property(property: 'hex_code', type: 'string', example: '#00AAFF', nullable: true),
            ],
            type: 'object',
            nullable: true,
        ),
        new OA\Property(
            property: 'model',
            properties: [
                new OA\Property(property: 'name', type: 'string', example: '308', nullable: true),
            ],
            type: 'object',
            nullable: true,
        ),
        new OA\Property(property: 'seats', type: 'integer', maximum: 9, minimum: 1, example: 5, nullable: true),
        new OA\Property(
            property: 'brand',
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'peugeot', nullable: true),
            ],
            type: 'object',
            nullable: true,
        ),
        new OA\Property(
            property: 'type',
            properties: [
                new OA\Property(property: 'name', type: 'string', example: 'berline', nullable: true),
            ],
            type: 'object',
            nullable: true,
        ),
    ],
    type: 'object'
)]

/**
 * Persons
 */
#[OA\Schema(
    schema: 'StorePersonRequestPayload',
    properties: [
        new OA\Property(property: 'first_name', type: 'string', maxLength: 100, example: 'John', nullable: true),
        new OA\Property(property: 'last_name', type: 'string', maxLength: 100, example: 'Doe', nullable: true),
        new OA\Property(property: 'firstname', description: 'Alias for first_name', type: 'string', maxLength: 100, example: 'John', nullable: true),
        new OA\Property(property: 'lastname', description: 'Alias for last_name', type: 'string', maxLength: 100, example: 'Doe', nullable: true),
        new OA\Property(property: 'phone', type: 'string', maxLength: 15, example: '+33600000000', nullable: true),
        new OA\Property(property: 'pseudo', type: 'string', maxLength: 50, example: 'jdoe', nullable: true),
        new OA\Property(property: 'car_id', type: 'integer', example: 12, nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UpdatePersonRequestPayload',
    properties: [
        new OA\Property(property: 'first_name', type: 'string', maxLength: 100, example: 'John', nullable: true),
        new OA\Property(property: 'last_name', type: 'string', maxLength: 100, example: 'Doe', nullable: true),
        new OA\Property(property: 'firstname', description: 'Alias for first_name', type: 'string', maxLength: 100, example: 'John', nullable: true),
        new OA\Property(property: 'lastname', description: 'Alias for last_name', type: 'string', maxLength: 100, example: 'Doe', nullable: true),
        new OA\Property(property: 'phone', type: 'string', maxLength: 15, example: '+33600000000', nullable: true),
        new OA\Property(property: 'pseudo', type: 'string', maxLength: 50, example: 'jdoe', nullable: true),
        new OA\Property(property: 'car_id', type: 'integer', example: 12, nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ['ACTIVE', 'DELETED'], example: 'ACTIVE', nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UpdateRolePersonRequestPayload',
    required: ['person_id', 'role_id'],
    properties: [
        new OA\Property(property: 'person_id', type: 'string', example: '1'),
        new OA\Property(property: 'role_id', type: 'integer', example: 1),
    ],
    type: 'object'
)]

/**
 * Users
 */
#[OA\Schema(
    schema: 'StoreUserRequestPayload',
    required: ['pseudo', 'first_name', 'last_name', 'phone', 'email'],
    properties: [
        new OA\Property(property: 'pseudo', type: 'string', maxLength: 50, example: 'john_doe'),
        new OA\Property(property: 'first_name', type: 'string', maxLength: 100, example: 'John'),
        new OA\Property(property: 'last_name', type: 'string', maxLength: 100, example: 'Doe'),
        new OA\Property(property: 'phone', type: 'string', maxLength: 20, example: '+33600000000'),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'john@example.com'),
        new OA\Property(property: 'city', type: 'string', maxLength: 100, example: 'Paris', nullable: true),
        new OA\Property(property: 'car_id', type: 'integer', example: 12, nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UpdateUserRequestPayload',
    properties: [
        new OA\Property(property: 'id', description: 'Route id merged into payload', type: 'integer', example: 1),
        new OA\Property(property: 'pseudo', type: 'string', maxLength: 50, example: 'john_doe', nullable: true),
        new OA\Property(property: 'first_name', type: 'string', maxLength: 100, example: 'John', nullable: true),
        new OA\Property(property: 'last_name', type: 'string', maxLength: 100, example: 'Doe', nullable: true),
        new OA\Property(property: 'phone', type: 'string', maxLength: 20, example: '+33600000000', nullable: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', maxLength: 255, example: 'john@example.com', nullable: true),
    ],
    type: 'object'
)]

/**
 * Trips
 */
#[OA\Schema(
    schema: 'CancelReservationRequestPayload',
    required: ['person_id'],
    properties: [
        new OA\Property(property: 'person_id', type: 'integer', example: 1),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'ReserveTripRequestPayload',
    required: ['person_id'],
    properties: [
        new OA\Property(property: 'person_id', type: 'integer', example: 2),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'StoreTripRequestPayload',
    required: [
        'kms',
        'trip_datetime',
        'available_seats',
        'starting_address',
        'arrival_address',
    ],
    properties: [
        new OA\Property(property: 'kms', type: 'number', format: 'float', minimum: 0.01, example: 12.5),
        new OA\Property(property: 'trip_datetime', type: 'string', format: 'date-time', example: '2026-02-18T14:00:00+01:00'),
        new OA\Property(property: 'available_seats', type: 'integer', maximum: 9, minimum: 1, example: 3),
        new OA\Property(property: 'smoking_allowed', type: 'boolean', example: false, nullable: true),

        new OA\Property(
            property: 'starting_address',
            required: ['street_number', 'street_name', 'postal_code', 'city_name'],
            properties: [
                new OA\Property(property: 'street_number', type: 'string', maxLength: 50, example: '10'),
                new OA\Property(property: 'street_name', type: 'string', maxLength: 255, example: 'Rue de Rivoli'),
                new OA\Property(property: 'postal_code', type: 'string', maxLength: 20, example: '75001'),
                new OA\Property(property: 'city_name', type: 'string', maxLength: 255, example: 'Paris'),
            ],
            type: 'object'
        ),

        new OA\Property(
            property: 'arrival_address',
            required: ['street_number', 'street_name', 'postal_code', 'city_name'],
            properties: [
                new OA\Property(property: 'street_number', type: 'string', maxLength: 50, example: '20'),
                new OA\Property(property: 'street_name', type: 'string', maxLength: 255, example: 'Avenue de France'),
                new OA\Property(property: 'postal_code', type: 'string', maxLength: 20, example: '75013'),
                new OA\Property(property: 'city_name', type: 'string', maxLength: 255, example: 'Paris'),
            ],
            type: 'object'
        ),

        // Optional: admin can create trip for another driver (person_id)
        new OA\Property(property: 'person_id', type: 'integer', example: 1, nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'TripIndexQuery',
    properties: [
        new OA\Property(property: 'startingcity', type: 'string', maxLength: 255, example: 'Paris', nullable: true),
        new OA\Property(property: 'arrivalcity', type: 'string', maxLength: 255, example: 'Lyon', nullable: true),
        new OA\Property(property: 'tripdate', type: 'string', example: '2026-02-20 14:30', description: 'Accepted formats: Y-m-d or Y-m-d H:i', nullable: true),
        new OA\Property(property: 'triptime', type: 'string', format: 'time', example: '14:30', nullable: true),
        new OA\Property(property: 'per_page', type: 'integer', maximum: 100, minimum: 1, example: 15, nullable: true),
    ],
    type: 'object'
)]
#[OA\Schema(
    schema: 'UpdateTripRequestPayload',
    properties: [
        new OA\Property(property: 'kms', type: 'number', format: 'float', minimum: 0.01, example: 15.0, nullable: true),
        new OA\Property(property: 'trip_datetime', type: 'string', format: 'date-time', example: '2026-02-18T16:00:00+01:00', nullable: true),
        new OA\Property(property: 'available_seats', type: 'integer', maximum: 9, minimum: 1, example: 2, nullable: true),
        new OA\Property(property: 'smoking_allowed', type: 'boolean', example: true, nullable: true),

        new OA\Property(
            property: 'starting_address',
            properties: [
                new OA\Property(property: 'street_number', type: 'string', maxLength: 50, example: '10', nullable: true),
                new OA\Property(property: 'street_name', type: 'string', maxLength: 255, example: 'Rue de Rivoli', nullable: true),
                new OA\Property(property: 'postal_code', type: 'string', maxLength: 20, example: '75001', nullable: true),
                new OA\Property(property: 'city_name', type: 'string', maxLength: 255, example: 'Paris', nullable: true),
            ],
            type: 'object',
            nullable: true
        ),

        new OA\Property(
            property: 'arrival_address',
            properties: [
                new OA\Property(property: 'street_number', type: 'string', maxLength: 50, example: '20', nullable: true),
                new OA\Property(property: 'street_name', type: 'string', maxLength: 255, example: 'Avenue de France', nullable: true),
                new OA\Property(property: 'postal_code', type: 'string', maxLength: 20, example: '75013', nullable: true),
                new OA\Property(property: 'city_name', type: 'string', maxLength: 255, example: 'Paris', nullable: true),
            ],
            type: 'object',
            nullable: true
        ),
    ],
    type: 'object'
)]
final class Schemas {}
