<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckRole
{
  public function handle(Request $request, Closure $next, ...$roles): Response
  {
    if (!Auth::check() || !in_array(Auth::user()->role, $roles)) {
      abort(403, 'ANDA TIDAK MEMILIKI AKSES.');
    }

    return $next($request);
  }
}
