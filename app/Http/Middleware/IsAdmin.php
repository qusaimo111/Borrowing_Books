<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user() || $request->user()->role !== 'admin') {
            return response()->json([
                'status' => false,
                'message' => 'غير مصرح لك بالوصول، هذه الصلاحية لصاحب المكتبة فقط.',
            ], 403);
        }

        return $next($request);
    }
}