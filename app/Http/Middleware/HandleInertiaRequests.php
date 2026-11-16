<?php

namespace App\Http\Middleware;

use App\Models\AttributionDossier;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'csrf_token' => csrf_token(),   
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
                'warning' => $request->session()->get('warning'),
                'info' => $request->session()->get('info')
            ],
            'dossiers_en_cours' => function () use ($request) {
                $user = $request->user();
                if (! $user) {
                    return 0;
                }

                $query = AttributionDossier::query()
                    ->where('statut', AttributionDossier::STATUT_ATTRIBUE);

                if ($user->role !== 'admin') {
                    $query->where('user_id', $user->id);
                }

                return $query->count();
            },
            'chatUsers' => function () use ($request) {
                $user = $request->user();
                if (!$user) {
                    return [];
                }

                return User::query()
                    ->where('id', '!=', $user->id)
                    ->orderBy('name')
                    ->get(['id', 'name']);
            },
        ];
    }
}
