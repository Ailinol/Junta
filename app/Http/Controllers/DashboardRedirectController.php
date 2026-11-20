<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();
        $destination = match (true) {
            $user->can('administer-platform') => 'admin.dashboard',
            $user->can('organize-campaigns') => 'organizador.dashboard',
            $user->can('supply-campaigns') => 'fornecedor.dashboard',
            default => 'consumidor.dashboard',
        };

        return to_route($destination);
    }
}
