<?php

namespace App\Http\Controllers;

use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Models\User;
use App\Support\PlatformAudit;
use App\Support\PlatformNotifier;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SupplierRegistrationController extends Controller
{
    public function create(Request $request): Response|RedirectResponse
    {
        if ($request->user()->supplier()->exists()) {
            return to_route('fornecedor.perfil');
        }

        return Inertia::render('consumidor/adesao-fornecedor');
    }

    public function store(SupplierRequest $request, PlatformNotifier $notifier): RedirectResponse
    {
        try {
            DB::transaction(function () use ($request, $notifier): void {
                User::query()->whereKey($request->user()->id)->update(['updated_at' => DB::raw('updated_at')]);
                if ($request->user()->supplier()->exists()) {
                    throw ValidationException::withMessages(['companyName' => 'Esta conta já tem um perfil comercial.']);
                }
                $supplier = Supplier::create($request->supplierData());
                PlatformAudit::record($request->user(), 'supplier.registration.submitted', $supplier, ['verified' => false]);
                foreach (User::query()->where('is_admin', true)->get() as $admin) {
                    $notifier->send($admin, 'supplier.registration:'.$supplier->id, 'Fornecedor por verificar', $supplier->company_name.' solicitou acesso ao fornecimento.', route('admin.fornecedores.index', absolute: false));
                }
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nuit' => 'O NUIT ou a conta já estão associados a um fornecedor.']);
        }
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perfil comercial enviado. Aguarde a verificação administrativa antes de apresentar propostas.']);

        return to_route('fornecedor.perfil');
    }
}
