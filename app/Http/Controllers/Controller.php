<?php

namespace App\Http\Controllers;

use App\Traits\RespondsWithApiJson;
use Illuminate\Http\Request;

abstract class Controller
{
    use RespondsWithApiJson;

    /**
     * Resolve the requested page size, clamped to a safe range.
     *
     * Prevents unbounded queries when a client requests a huge `per_page`
     * (e.g. 100000) and guards against zero/negative values.
     */
    protected function perPage(Request $request, int $default = 15, int $max = 100): int
    {
        return max(1, min($request->integer('per_page', $default), $max));
    }
}
