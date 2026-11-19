<?php

namespace App\Http\Middleware;

use App\Support\AdminData;
use App\Support\OpenAiClient;
use App\Support\PlatformData;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'ai' => ['enabled' => app(OpenAiClient::class)->enabled()],
            'auth' => [
                'user' => $request->user(),
                'permissions' => [
                    'administrator' => $request->user()?->can('administer-platform') ?? false,
                    'organizer' => $request->user()?->can('organize-campaigns') ?? false,
                    'supplier' => $request->user()?->can('supply-campaigns') ?? false,
                ],
            ],
            'business' => fn (): array => app(PlatformData::class)->forRequest($request),
            'adminNavigation' => fn (): ?array => $request->user()?->can('administer-platform') && ($request->routeIs('admin.*') || ($request->routeIs('notifications.index') && $request->query('area') === 'admin')) ? app(AdminData::class)->navigation($request->user()) : null,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
