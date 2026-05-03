<?php

namespace App\Http\Middleware;

use App\Models\About;
use App\Services\WhatsappService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckWorkAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();

        if ($user->is_admin) {
            return $next($request);
        }

        $settings = About::first();

        if (!$settings) {
            return response()->json([
                'success' => false,
                'message' => 'Configuration des horaires introuvable'
            ], 500);
        }

        $now = now();

        $today = strtolower($now->englishDayOfWeek);

        $workingDays = $settings->working_days ?? [];

        if (!in_array($today, $workingDays)) {

            $this->forceLogout($user);

            return response()->json([
                'success' => false,
                'message' => 'Aujourd’hui est un jour non ouvrable'
            ], 403);
        }

        $opening = today()->setTimeFromTimeString($settings->opening_time);
        $closing = today()->setTimeFromTimeString($settings->closing_time);

        $realClosing = $closing->copy()
            ->addMinutes($settings->grace_minutes);

        if ($now->between($opening, $closing)) {
            return $next($request);
        }

        if ($now->between($closing, $realClosing)) {

            $allowedRoutes = [
                'api.v1.overtime.request',
                'api.v1.auth.logout'
            ];

            if (in_array($request->route()->getName(), $allowedRoutes)) {
                return $next($request);
            }

            return response()->json([
                'success' => false,
                'message' => 'Temps de travail terminé. Demandez des heures supplémentaires.'
            ], 403);
        }


        if ($user->overtime_until && now()->lessThan($user->overtime_until)) {
            return $next($request);
        }


        $cacheKey = 'after_hours_alert_' . $user->id;

        if (!Cache::has($cacheKey)) {

            WhatsappService::send(
                "⛔ TENTATIVE ACCÈS HORS HORAIRE\n\n" .
                    "👤 Utilisateur : {$user->name}\n" .
                    "📅 Heure : {$now}\n" .
                    "🌐 IP : {$request->ip()}"
            );

            Cache::put($cacheKey, true, now()->addMinutes(10));
        }


        $this->forceLogout($user);

        return response()->json([
            'success' => false,
            'status' => 403,
            'message' => 'Accès fermé'
        ], 403);
    }

    private function forceLogout($user)
    {
        if ($user && $user->tokens()->exists()) {
            $user->tokens()->delete();
        }
    }

    // public function handle(Request $request, Closure $next)
    // {
    //     $user = Auth::user();

    //     if ($user->is_admin) {

    //         return $next($request);
    //     }

    //     $settings = About::first();

    //     $now = now();


    //     $today = strtolower($now->englishDayOfWeek);

    //     $workingDays = $settings->working_days ?? [];

    //     if (!in_array($today, $workingDays)) {

    //         return response()->json([

    //             'success' => false,

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
    //                 $allowedRoutes
    //             )
    //         ) {

    //             return $next($request);
    //         }

    //         return response()->json([

    //             'success' => false,

    //             'message' =>
    //             'Temps de travail terminé. Demandez des heures supplémentaires.'

    //         ], 403);
    //     }


    //     if (
    //         $user->overtime_until &&
    //         now()->lessThan(
    //             $user->overtime_until
    //         )
    //     ) {

    //         return $next($request);
    //     }


    //     $message =
    //         "⛔ TENTATIVE ACCÈS HORS HORAIRE\n\n" .

    //         "👤 Utilisateur : " .
    //         $user->name . "\n" .

    //         "📅 Heure : " .
    //         now() . "\n" .

    //         "🌐 IP : " .
    //         $request->ip();

    //     WhatsappService::send($message);
    //     if ($request->user()?->currentAccessToken()) {

    //         $request->user()
    //             ->currentAccessToken()
    //             ->delete();
    //     }

    //     return response()->json([

    //         'success' => false,
    //         'status' => 403,
    //         'message' => 'Accès fermé'

    //     ], 403);
    // }
}
