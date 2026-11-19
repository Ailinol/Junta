<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupplierRequest;
use App\Models\Supplier;
use App\Support\PlatformAudit;
use App\Support\PlatformNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function store(SupplierRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $supplier = Supplier::create($request->supplierData());
            PlatformAudit::record($request->user(), 'supplier.created', $supplier, ['verified' => $supplier->verified]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fornecedor criado.']);

        return back();
    }

    public function update(SupplierRequest $request, Supplier $supplier, PlatformNotifier $notifier): RedirectResponse
    {
        DB::transaction(function () use ($request, $supplier, $notifier): void {
            $locked = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            $before = $locked->toArray();
            $locked->update($request->supplierData());
            if ($locked->wasChanged('verified') && $locked->user) {
                $notifier->send($locked->user, 'supplier.verification:'.$locked->id.':'.(int) $locked->verified.':'.now()->format('U.u'), $locked->verified ? 'Fornecedor verificado' : 'Verificação de fornecedor suspensa', $locked->verified ? 'O seu perfil comercial foi verificado. Já pode apresentar propostas.' : 'O acesso à apresentação de propostas foi suspenso. Consulte a administração.', route('fornecedor.perfil', absolute: false));
            }
            PlatformAudit::record($request->user(), 'supplier.updated', $locked, ['before' => $before, 'after' => $locked->toArray()]);
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fornecedor actualizado.']);

        return back();
    }

    public function destroy(Request $request, Supplier $supplier): RedirectResponse
    {
        DB::transaction(function () use ($request, $supplier): void {
            $locked = Supplier::query()->lockForUpdate()->findOrFail($supplier->id);
            if ($locked->proposals()->exists()) {
                throw ValidationException::withMessages(['record' => 'Este fornecedor tem propostas associadas e não pode ser removido.']);
            } PlatformAudit::record($request->user(), 'supplier.deleted', $locked, ['companyName' => $locked->company_name]);
            $locked->delete();
        });
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Fornecedor removido.']);

        return back();
    }
}
