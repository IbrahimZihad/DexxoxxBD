<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

interface MiddlewareContract
{
    public function handle(Request $request, Closure $next): Response;
}
