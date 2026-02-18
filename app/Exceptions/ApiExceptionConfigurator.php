<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Configuration\Exceptions;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ApiExceptionConfigurator
{
    public static function register(Exceptions $exceptions): void
    {
        // 0) Your domain/business exceptions
        $exceptions->render(function (DomainException $e, Request $request) {

            return response()->json([
                'error'   => $e->codeName(),
                'details' => $e->getMessage(),
            ], $e->status() ?? Response::HTTP_BAD_REQUEST);
        });

        // 1) Validation exceptions (FormRequest)
        $exceptions->render(function (ValidationException $e, Request $request) {

            return response()->json([
                'error'   => 'VALIDATION_ERROR',
                'details' => 'The given data was invalid.',
                'fields'  => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        // 2) Policy/Gate authorization exceptions
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return response()->json([
                'error'   => 'FORBIDDEN',
                'details' => $e->getMessage() ?: 'Forbidden.',
            ], Response::HTTP_FORBIDDEN);
        });

        // 3) Model not found
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {

            $model = class_basename($e->getModel());
            $ids = $e->getIds();
            $id = is_array($ids) ? ($ids[0] ?? null) : $ids;

            return response()->json([
                'error'   => 'NOT_FOUND',
                'details' => "$model not found",
                'meta'    => [
                    'model' => $model,
                    'id'    => $id,
                ],
            ], Response::HTTP_NOT_FOUND);
        });

        // 3bis) NotFoundHttpException (route binding often ends here)
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            $prev = $e->getPrevious();

            // If it comes from Eloquent model binding, Laravel often stores it as "previous"
            if ($prev instanceof ModelNotFoundException) {
                $model = class_basename($prev->getModel());
                $ids = $prev->getIds();
                $id = is_array($ids) ? ($ids[0] ?? null) : $ids;

                return response()->json([
                    'error'   => 'NOT_FOUND',
                    'details' => "{$model} not found",
                    'meta'    => [
                        'model' => $model,
                        'id'    => $id,
                    ],
                ], Response::HTTP_NOT_FOUND);
            }

            // Otherwise: real route 404
            return response()->json([
                'error'   => 'NOT_FOUND',
                'details' => 'Route not found',
            ], Response::HTTP_NOT_FOUND);
        });

        // 4) Database query exceptions (PostgreSQL-focused, but safe)
        $exceptions->render(function (QueryException $e, Request $request) {

            // Postgres SQLSTATE is usually in $e->errorInfo[0]
            $sqlState = $e->errorInfo[0] ?? null;

            // 23505 = unique_violation
            if ($sqlState === '23505') {
                return response()->json([
                    'error'   => 'CONFLICT',
                    'details' => 'Unique constraint violation.',
                ], Response::HTTP_CONFLICT);
            }

            // 23503 = foreign_key_violation
            if ($sqlState === '23503') {
                return response()->json([
                    'error'   => 'CONFLICT',
                    'details' => 'Foreign key constraint violation.',
                ], Response::HTTP_CONFLICT);
            }

            // 22P02 = invalid_text_representation (bad UUID/int etc.)
            if ($sqlState === '22P02') {
                return response()->json([
                    'error'   => 'INVALID_INPUT',
                    'details' => 'Invalid input syntax.',
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'error'   => 'DATABASE_ERROR',
                'details' => 'Database query error.',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status =  $e->getStatusCode();
            // Keep production safe
            $message = config('app.debug') ? $e->getMessage() : 'Server Error';

            return response()->json([
                'error'   => class_basename($e),
                'details' => $message,
            ], $status);
        });
    }
}
