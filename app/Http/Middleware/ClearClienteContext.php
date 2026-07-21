<?php

namespace App\Http\Middleware;

use App\Support\Tenant\ClienteContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ClearClienteContext
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            return $next($request);
        } finally {
            ClienteContext::clear();
        }
    }
}
