<?php

namespace App\Http\Middleware;

use App\Support\LinkPreviewBot;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance as Middleware;

class PreventRequestsDuringMaintenance extends Middleware
{
    /**
     * The URIs that should be reachable while maintenance mode is enabled.
     *
     * @var array<int, string>
     */
    protected $except = [
        //
    ];

    protected function inExceptArray($request): bool
    {
        if ($request->isMethod('GET') && LinkPreviewBot::matchesRequest($request)) {
            return true;
        }

        return parent::inExceptArray($request);
    }
}
