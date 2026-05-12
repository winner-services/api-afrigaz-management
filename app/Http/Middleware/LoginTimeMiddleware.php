<?php

namespace App\Http\Middleware;

use App\Models\About;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Symfony\Component\HttpFoundation\Response;

class LoginTimeMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->is('api/v1/auth/login')) {
            return $next($request);
        }

        $user = User::where('email', $request->email)
            ->orWhere('phone', $request->email)
            ->first();
        if ($user && $user->is_admin) {
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
        // dd($now->toDateTimeString());

        $today = strtolower($now->format('l'));

        $workingDays = collect($settings->working_days ?? [])
            ->map(fn($day) => strtolower(trim($day)))
            ->toArray();

        if (! in_array($today, $workingDays, true)) {

            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'Jour non ouvrable'
            ], 403);
        }

        $opening = Carbon::parse($settings->opening_time);
        $closing = Carbon::parse($settings->closing_time);

        $grace = $closing
            ->copy()
            ->addMinutes($settings->grace_minutes ?? 0);
            // dd($now->toDateTimeString(), $opening->toDateTimeString(), $closing->toDateTimeString(), $grace->toDateTimeString());

        if (! $now->between($opening, $grace)) {

            return response()->json([
                'success' => false,
                'status' => 403,
                'message' => 'Connexion interdite en dehors des heures de service'
            ], 403);
        }

        return $next($request);
    }
}
