<?php

namespace App\Http\Middleware;

use App\Models\About;
use App\Services\WhatsappService;
use Closure;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class CheckWorkAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */

    // public function handle(Request $request, Closure $next)
    // {
    //     $user = $request->user();
    //     if ($user->is_admin) {
    //         return $next($request);
    //     }

    //     $settings = About::first();

    //     if (!$settings) {

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Configuration des horaires introuvable'
    //         ], 500);
    //     }

    //     $now = now();

    //     $today = strtolower(
    //         $now->englishDayOfWeek
    //     );

    //     $workingDaysRaw = $settings->working_days;

    //     $workingDays = collect(

    //         is_array($workingDaysRaw)

    //             ? $workingDaysRaw

    //             : json_decode($workingDaysRaw, true)

    //     )
    //         ->map(fn($day) => strtolower(trim($day)))
    //         ->toArray();

    //     if (!in_array($today, $workingDays, true)) {

    //         $this->forceLogout($user);

    //         return response()->json([
    //             'success' => false,
    //             'status' => 403,
    //             'message' => 'Aujourd’hui est un jour non ouvrable'
    //         ], 403);
    //     }
    //     $opening = today()->setTimeFromTimeString(
    //         $settings->opening_time
    //     );

    //     $closing = today()->setTimeFromTimeString(
    //         $settings->closing_time
    //     );

    //     $realClosing = $closing
    //         ->copy()
    //         ->addMinutes(
    //             $settings->grace_minutes
    //         );

    //     if ($now->between($opening, $closing)) {

    //         return $next($request);
    //     }

    //     if ($now->between($closing, $realClosing)) {

    //         $allowedRoutes = [

    //             'api.v1.overtime.request',

    //             'api.v1.auth.logout'
    //         ];

    //         if (
    //             in_array(
    //                 $request->route()->getName(),
    //                 $allowedRoutes,
    //                 true
    //             )
    //         ) {

    //             return $next($request);
    //         }

    //         return response()->json([

    //             'success' => false,

    //             'status' => 403,

    //             'message' =>
    //             'Temps de travail terminé. Demandez des heures supplémentaires.'

    //         ], 403);
    //     }

    //     if (
    //         $user->overtime_until &&
    //         now()->lessThan($user->overtime_until)
    //     ) {

    //         return $next($request);
    //     }


    //     $cacheKey = 'after_hours_alert_' . $user->id;

    //     if (!Cache::has($cacheKey)) {
    //         Cache::put(
    //             $cacheKey,
    //             true,
    //             now()->addMinutes(10)
    //         );
    //     }

    //     $this->forceLogout($user);

    //     return response()->json([

    //         'success' => false,

    //         'status' => 403,

    //         'message' => 'Accès fermé'

    //     ], 403);
    // }

    // private function forceLogout($user): void
    // {
    //     if (
    //         $user &&
    //         method_exists($user, 'tokens') &&
    //         $user->tokens()->exists()
    //     ) {

    //         $user->tokens()->delete();
    //     }
    // }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $this->deny($request, null, 'Unauthenticated user');
        }

        if ($user->is_admin) {
            return $next($request);
        }

        $settings = About::first();

        if (!$settings) {
            return $this->deny($request, $user, 'Missing working configuration', 500);
        }
        $now = now()->addHour();

        $today = strtolower($now->format('l'));

        $workingDays = collect($settings->working_days ?? [])
            ->map(fn($day) => strtolower(trim($day)))
            ->toArray();

        if (!in_array($today, $workingDays, true)) {
            return $this->deny($request, $user, 'Jour non ouvrable');
        }
        $opening = Carbon::parse($settings->opening_time);
        $closing = Carbon::parse($settings->closing_time);

        $graceClosing = $closing->copy()->addMinutes($settings->grace_minutes ?? 0);

        if ($now->between($opening, $closing)) {
            return $next($request);
        }
        //         if ($now->between($closing, $graceClosing)) {

        //     if ($request->routeIs([
        //         'api.v1.overtime.request',
        //         'api.v1.overtime.update',
        //         'api.v1.overtime.index',
        //     ])) {
        //         return $next($request);
        //     }

        //     return $this->deny(
        //         $request,
        //         $user,
        //         'Durée de travail terminée (période de grâce)'
        //     );
        // }

        if ($now->between($closing, $graceClosing)) {

            $allowedRoutes = [
                'api.v1.overtime.request',
                'api.v1.overtime.update',
                'api.v1.overtime.index'
            ];

            $routeName = optional($request->route())->getName();

            if (in_array($routeName, $allowedRoutes, true)) {
                return $next($request);
            }

            return $this->deny($request, $user, 'Durée de travail terminée (période de grâce)');
        }

        if ($user->overtime_until && now()->lessThan($user->overtime_until)) {
            return $next($request);
        }
        return $this->deny($request, $user, 'Accès fermé');
    }

    /**
     * Centralized deny response + logging
     */
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
