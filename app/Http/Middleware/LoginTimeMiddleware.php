<?php

namespace App\Http\Middleware;

use App\Models\About;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LoginTimeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = User::where('email', $request->email)
            ->orWhere('phone', $request->email)
            ->first();
        if (! $request->routeIs('login')) {
            return $next($request);
        }

        $settings = About::first();

        if (! $settings) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration introuvable'
            ], 500);
        }

        $now = now()->addHour();
        $today = strtolower($now->format('l'));
        $workingDays = collect($settings->working_days ?? [])
            ->map(fn($day) => strtolower(trim($day)))
            ->toArray();

        if (!in_array($today, $workingDays, true)) {
            return response()->json([
                'success' => false,
                'status' => 422,
                'message' => 'Non working day'
            ], 500);
        }

        $opening = Carbon::parse($settings->opening_time);
        $closing = Carbon::parse($settings->closing_time);

        $grace = $closing->copy()->addMinutes($settings->grace_minutes ?? 0);


        if ($user && $user->is_admin) {
            return $next($request);
        }

        if (! $now->between($opening, $grace)) {
            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'Connexion interdite en dehors des heures de service'
            ], 403);
        }

        return $next($request);
    }

    private function deny(Request $request, $user = null, string $reason = 'Blocked', int $status = 403): Response
    {
        Log::warning('ACCESS BLOCKED', [
            'user_id'   => $user?->id,
            'email'     => $user?->email,
            'ip'        => $request->ip(),
            'route'     => optional($request->route())->getName(),
            'url'       => $request->fullUrl(),
            'method'    => $request->method(),
            'reason'    => $reason,
            'time'      => now()->toDateTimeString(),
        ]);

        if ($user) {
            $cacheKey = "access_blocked_{$user->id}";
            if (!Cache::has($cacheKey)) {
                Cache::put($cacheKey, true, now()->addMinutes(10));
            }
        }

        return response()->json([
            'success' => false,
            'status'  => $status,
            'message' => $reason,
        ], $status);
    }
}
