<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class Controller
{
    /** Rows per page for list screens. */
    protected int $perPage = 15;

    /** Apply ?sort=&direction= from the request, limited to a whitelist of columns. */
    protected function sorted(Builder $query, Request $request, array $allowed, string $default = 'created_at', string $direction = 'desc'): Builder
    {
        $column = in_array($request->query('sort'), $allowed, true) ? $request->query('sort') : $default;
        $direction = in_array($request->query('direction'), ['asc', 'desc'], true) ? $request->query('direction') : $direction;

        return $query->orderBy($column, $direction)->orderBy($query->getModel()->getQualifiedKeyName(), 'desc');
    }

    /** Escape a search term for a LIKE query. */
    protected function like(?string $term): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], trim((string) $term)).'%';
    }

    /** Standard AJAX / API success response. */
    protected function ok(mixed $data = [], string $message = 'OK', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }
}
