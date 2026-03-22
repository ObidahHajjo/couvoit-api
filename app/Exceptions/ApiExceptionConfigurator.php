<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Foundation\Configuration\Exceptions;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Configures JSON exception rendering for the API layer.
 */
final class ApiExceptionConfigurator
{
    /**
     * Register API exception renderers.
     */
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
                'details' => __('api.errors.validation'),
                'fields'  => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        // 2) Policy/Gate authorization exceptions
        $exceptions->render(function (AuthorizationException $e, Request $request) {
            return response()->json([
                'error'   => 'FORBIDDEN',
                'details' => $e->getMessage() ?: __('api.errors.forbidden'),
            ], Response::HTTP_FORBIDDEN);
        });

        // 3) Model not found
        $exceptions->render(function (ModelNotFoundException $e, Request $request) {

            $model = class_basename($e->getModel());
            $ids = $e->getIds();
            $id = is_array($ids) ? ($ids[0] ?? null) : $ids;

            return response()->json([
                'error'   => 'NOT_FOUND',
                'details' => __('api.errors.model_not_found', ['model' => $model]),
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
                    'details' => __('api.errors.model_not_found', ['model' => $model]),
                    'meta'    => [
                        'model' => $model,
                        'id'    => $id,
                    ],
                ], Response::HTTP_NOT_FOUND);
            }

            // Otherwise: real route 404
            return response()->json([
                'error'   => 'NOT_FOUND',
                'details' => __('api.errors.route_not_found'),
            ], Response::HTTP_NOT_FOUND);
        });

        // 4) Database query exceptions (PostgreSQL-focused, but safe)
        $exceptions->render(function (QueryException $e, Request $request) {

            // Postgres SQLSTATE is usually in $e->errorInfo[0]
            $sqlState = $e->errorInfo[0] ?? null;

            if ($sqlState === '23505') {

                $message = $e->errorInfo[2] ?? '';

                $constraint = null;
                $fields = null;
                $values = null;

                // Extract constraint name
                if (preg_match('/unique constraint "([^"]+)"/', $message, $m)) {
                    $constraint = $m[1];
                }

                // Extract duplicated fields + values
                if (preg_match('/Key \(([^)]+)\)=\(([^)]+)\)/', $message, $m)) {
                    $fields = explode(', ', $m[1]);
                    $values = explode(', ', $m[2]);
                }

                return response()->json([
                    'error' => 'CONFLICT',
                    'constraint' => $constraint,
                    'duplicated_fields' => $fields,
                    'duplicated_values' => $values,
                ], Response::HTTP_CONFLICT);
            }

            // 23503 = foreign_key_violation
            if ($sqlState === '23503') {
                return response()->json([
                    'error'   => 'CONFLICT',
                    'details' => __('api.errors.foreign_key_constraint'),
                ], Response::HTTP_CONFLICT);
            }

            // 22P02 = invalid_text_representation (bad UUID/int etc.)
            if ($sqlState === '22P02') {
                return response()->json([
                    'error'   => 'INVALID_INPUT',
                    'details' => __('api.errors.invalid_input_syntax'),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            return response()->json([
                'error'   => 'DATABASE_ERROR',
                'details' => __('api.errors.database_query'),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });

        $exceptions->render(function (AccessDeniedHttpException $e, Request $request) {
            return response()->json([
                'error'   => 'FORBIDDEN',
                'details' => $e->getMessage() ?: __('api.errors.forbidden'),
            ], Response::HTTP_FORBIDDEN);
        });

        $exceptions->render(function (HttpExceptionInterface $e, Request $request) {
            $status =  $e->getStatusCode();
            // Keep production safe
            $message = config('app.debug') ? $e->getMessage() : __('api.errors.server_error');

            return response()->json([
                'error'   => class_basename($e),
                'details' => $message,
            ], $status);
        });
    }
}
