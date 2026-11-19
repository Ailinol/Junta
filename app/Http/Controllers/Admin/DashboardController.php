<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\AdminData;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, AdminData $data): Response
    {
        return Inertia::render('admin/dashboard', ['adminDashboard' => $data->dashboard($request)]);
    }

    public function history(Request $request, AdminData $data): Response
    {
        return Inertia::render('admin/historico', ['adminHistory' => $data->history($request)]);
    }
}
