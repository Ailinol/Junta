<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\PurchaseFinanceController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AiReviewController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\CampaignPurchaseController;
use App\Http\Controllers\ContactPreferencesController;
use App\Http\Controllers\DashboardRedirectController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\OrganizerApplicationController;
use App\Http\Controllers\ParticipationController;
use App\Http\Controllers\PlatformController;
use App\Http\Controllers\ProposalController;
use App\Http\Controllers\ProposalSelectionController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\SupplierProfileController;
use App\Http\Controllers\SupplierRegistrationController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;

Route::get('/', PlatformController::class)->defaults('page', 'welcome')->name('home');
Route::middleware('auth')->group(function (): void {
    Route::get('/notificacoes', PlatformController::class)->defaults('page', 'notificacoes')->name('notifications.index');
    Route::get('/adesao-fornecedor', [SupplierRegistrationController::class, 'create'])->name('fornecedor.adesao.create');
    Route::post('/adesao-fornecedor', [SupplierRegistrationController::class, 'store'])->middleware('throttle:6,1')->name('fornecedor.adesao.store');
});

Route::prefix('ia')->middleware(['auth', 'throttle:6,1'])->name('ai.')->group(function (): void {
    Route::post('/campanha', [AiReviewController::class, 'campaign'])->name('campaign');
    Route::post('/proposta', [AiReviewController::class, 'proposal'])->name('proposal');
    Route::post('/campanhas/{campaign}/comparar', [AiReviewController::class, 'compare'])->name('compare');
    Route::post('/campanhas/{campaign}/previsao', [AiReviewController::class, 'forecast'])->name('forecast');
    Route::post('/analises/{analysis}/revisao', [AiReviewController::class, 'decision'])->name('decision');
});
Route::get('/campanhas', PlatformController::class)->defaults('page', 'campanhas/index')->name('campanhas.index');
Route::get('/campanhas/{id}', PlatformController::class)->defaults('page', 'campanhas/detalhes')->name('campanhas.show');
Route::get('/como-funciona', PlatformController::class)->defaults('page', 'publico/como-funciona')->name('publico.como-funciona');
Route::get('/fornecedores', PlatformController::class)->defaults('page', 'publico/fornecedores')->name('publico.fornecedores');

Route::prefix('consumidor')->middleware(['auth'])->group(function (): void {
    Route::get('/explorar', PlatformController::class)->defaults('page', 'consumidor/explorar')->name('consumidor.explorar');
    Route::get('/campanhas/{id}', PlatformController::class)->whereNumber('id')->defaults('page', 'consumidor/campanhas/detalhes')->name('consumidor.campanhas.show');
    Route::get('/dashboard', PlatformController::class)->defaults('page', 'consumidor/dashboard')->name('consumidor.dashboard');
    Route::get('/campanhas', PlatformController::class)->defaults('page', 'consumidor/campanhas/index')->name('consumidor.campanhas.index');
    Route::get('/campanhas/participar', PlatformController::class)->defaults('page', 'consumidor/campanhas/participar')->name('consumidor.campanhas.participar');
    Route::get('/acompanhamento', PlatformController::class)->defaults('page', 'consumidor/acompanhamento')->name('consumidor.acompanhamento');
    Route::get('/acompanhamento/{id}', PlatformController::class)->defaults('page', 'consumidor/acompanhamento')->name('consumidor.acompanhamento.show');
    Route::get('/historico', PlatformController::class)->defaults('page', 'consumidor/historico')->name('consumidor.historico');
    Route::get('/perfil', PlatformController::class)->defaults('page', 'consumidor/perfil')->name('consumidor.perfil');
    Route::get('/notificacoes', PlatformController::class)->defaults('page', 'consumidor/notificacoes')->name('consumidor.notificacoes');
    Route::get('/candidatura-organizador', PlatformController::class)->defaults('page', 'consumidor/candidatura-organizador')->name('consumidor.candidatura');
    Route::post('/candidatura-organizador', [OrganizerApplicationController::class, 'store'])->name('consumidor.candidatura.store');
    Route::post('/campanhas/{campaign}/participacoes', [ParticipationController::class, 'store'])->name('consumidor.campanhas.aderir');
    Route::patch('/perfil', [ContactPreferencesController::class, 'update'])->name('consumidor.perfil.update');
    Route::patch('/notificacoes/lidas', [NotificationController::class, 'readAll'])->name('consumidor.notificacoes.readAll');
    Route::patch('/notificacoes/{notification}', [NotificationController::class, 'update'])->name('consumidor.notificacoes.update');
    Route::delete('/notificacoes/{notification}', [NotificationController::class, 'destroy'])->name('consumidor.notificacoes.destroy');
});

Route::prefix('organizador')->middleware(['auth', 'can:organize-campaigns'])->group(function (): void {
    Route::post('/campanhas/{campaign}/cancelar', [CampaignController::class, 'cancel'])->name('organizador.campanhas.cancel');
    Route::get('/campanhas/{campaign}/editar', [CampaignController::class, 'edit'])->name('organizador.campanhas.edit');
    Route::patch('/campanhas/{campaign}', [CampaignController::class, 'update'])->name('organizador.campanhas.update');
    Route::get('/dashboard', PlatformController::class)->defaults('page', 'organizador/dashboard')->name('organizador.dashboard');
    Route::get('/campanhas', PlatformController::class)->defaults('page', 'organizador/campanhas/index')->name('organizador.campanhas.index');
    Route::get('/campanhas/criar', PlatformController::class)->defaults('page', 'organizador/campanhas/criar')->name('organizador.campanhas.criar');
    Route::get('/campanhas/{id}', PlatformController::class)->defaults('page', 'organizador/campanhas/detalhes')->name('organizador.campanhas.show');
    Route::get('/campanhas/{id}/participantes', PlatformController::class)->defaults('page', 'organizador/campanhas/participantes')->name('organizador.campanhas.participantes');
    Route::get('/campanhas/{id}/propostas', PlatformController::class)->defaults('page', 'organizador/campanhas/propostas')->name('organizador.campanhas.propostas');
    Route::post('/campanhas', [CampaignController::class, 'store'])->name('organizador.campanhas.store');
    Route::post('/campanhas/{campaign}/propostas/{proposal}/seleccionar', [ProposalSelectionController::class, 'store'])->name('organizador.campanhas.seleccionar');
});

Route::prefix('fornecedor')->middleware(['auth', 'can:supply-campaigns'])->group(function (): void {
    Route::get('/propostas/{proposal}/editar', [ProposalController::class, 'edit'])->name('fornecedor.propostas.edit');
    Route::patch('/propostas/{proposal}', [ProposalController::class, 'update'])->name('fornecedor.propostas.update');
    Route::post('/propostas/{proposal}/retirar', [ProposalController::class, 'withdraw'])->name('fornecedor.propostas.withdraw');
    Route::get('/dashboard', PlatformController::class)->defaults('page', 'fornecedor/dashboard')->name('fornecedor.dashboard');
    Route::get('/oportunidades', PlatformController::class)->defaults('page', 'fornecedor/oportunidades/index')->name('fornecedor.oportunidades.index');
    Route::get('/oportunidades/{id}', PlatformController::class)->defaults('page', 'fornecedor/oportunidades/detalhes')->name('fornecedor.oportunidades.show');
    Route::get('/propostas/criar', PlatformController::class)->defaults('page', 'fornecedor/propostas/criar')->name('fornecedor.propostas.criar');
    Route::get('/propostas/{proposal}', [ProposalController::class, 'show'])->name('fornecedor.propostas.show');
    Route::post('/propostas/{proposal}/problema', [ProposalController::class, 'reportProblem'])->middleware('throttle:5,1')->name('fornecedor.propostas.problem');
    Route::get('/propostas', PlatformController::class)->defaults('page', 'fornecedor/propostas/index')->name('fornecedor.propostas.index');
    Route::get('/perfil', PlatformController::class)->defaults('page', 'fornecedor/perfil')->name('fornecedor.perfil');
    Route::post('/propostas', [ProposalController::class, 'store'])->name('fornecedor.propostas.store');
    Route::patch('/perfil', [SupplierProfileController::class, 'update'])->name('fornecedor.perfil.update');
});

Route::prefix('admin')->middleware(['auth', 'can:administer-platform'])->group(function (): void {
    Route::get('/compras/exportar', [PurchaseFinanceController::class, 'export'])->name('admin.compras.export');
    Route::get('/campanhas', PlatformController::class)->defaults('page', 'admin/campanhas/index')->name('admin.campanhas.index');
    Route::get('/fornecedores', PlatformController::class)->defaults('page', 'admin/fornecedores/index')->name('admin.fornecedores.index');
    Route::get('/propostas', PlatformController::class)->defaults('page', 'admin/propostas/index')->name('admin.propostas.index');
    Route::get('/relatorios', PlatformController::class)->defaults('page', 'admin/relatorios')->name('admin.relatorios');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('admin.dashboard');
    Route::get('/utilizadores', [UserController::class, 'index'])->name('admin.utilizadores.index');
    Route::get('/candidaturas', [App\Http\Controllers\Admin\OrganizerApplicationController::class, 'index'])->name('admin.candidaturas.index');
    Route::get('/historico', [DashboardController::class, 'history'])->name('admin.historico');
    Route::post('/fornecedores', [SupplierController::class, 'store'])->name('admin.fornecedores.store');
    Route::patch('/fornecedores/{supplier}', [SupplierController::class, 'update'])->name('admin.fornecedores.update');
    Route::delete('/fornecedores/{supplier}', [SupplierController::class, 'destroy'])->name('admin.fornecedores.destroy');
    Route::post('/campanhas', [App\Http\Controllers\Admin\CampaignController::class, 'store'])->name('admin.campanhas.store');
    Route::patch('/campanhas/{campaign}', [App\Http\Controllers\Admin\CampaignController::class, 'update'])->name('admin.campanhas.update');
    Route::delete('/campanhas/{campaign}', [App\Http\Controllers\Admin\CampaignController::class, 'destroy'])->name('admin.campanhas.destroy');
    Route::post('/propostas', [App\Http\Controllers\Admin\ProposalController::class, 'store'])->name('admin.propostas.store');
    Route::patch('/propostas/{proposal}', [App\Http\Controllers\Admin\ProposalController::class, 'update'])->name('admin.propostas.update');
    Route::delete('/propostas/{proposal}', [App\Http\Controllers\Admin\ProposalController::class, 'destroy'])->name('admin.propostas.destroy');
    Route::patch('/candidaturas/{application}', [App\Http\Controllers\Admin\OrganizerApplicationController::class, 'update'])->name('admin.candidaturas.update');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('dashboard', DashboardRedirectController::class)->name('dashboard');
});
require __DIR__.'/settings.php';

Route::middleware('auth')->group(function (): void {
    Route::patch('/consumidor/participacoes/{participation}', [ParticipationController::class, 'update'])->name('consumidor.participacoes.update');
    Route::delete('/consumidor/participacoes/{participation}', [ParticipationController::class, 'destroy'])->name('consumidor.participacoes.destroy');
    Route::post('/consumidor/campanhas/{campaign}/pedido', [PurchaseController::class, 'store'])->name('consumidor.pedidos.store');
    Route::get('/consumidor/pedidos/{order}/pagar', [PurchaseController::class, 'checkout'])->name('consumidor.pedidos.checkout');
    Route::post('/consumidor/pedidos/{order}/pagar', [PurchaseController::class, 'pay'])->name('consumidor.pedidos.pay');
    Route::post('/consumidor/pedidos/{order}/conferir', [PurchaseController::class, 'reconcile'])->name('consumidor.pedidos.reconcile');
    Route::post('/consumidor/pedidos/{order}/simular', [PurchaseController::class, 'simulate'])->name('consumidor.pedidos.simulate');
    Route::post('/consumidor/pedidos/{order}/ocorrencias', [PurchaseController::class, 'issue'])->name('consumidor.pedidos.issue');
    Route::get('/organizador/campanhas/{campaign}/compra', [CampaignPurchaseController::class, 'show'])->name('organizador.compra.show');
    Route::post('/organizador/campanhas/{campaign}/fechar-interesse', [CampaignPurchaseController::class, 'closeInterest'])->name('organizador.compra.closeInterest');
    Route::post('/organizador/campanhas/{campaign}/abrir-pagamentos', [CampaignPurchaseController::class, 'open'])->name('organizador.compra.open');
    Route::post('/organizador/campanhas/{campaign}/apurar', [CampaignPurchaseController::class, 'close'])->name('organizador.compra.close');
    Route::post('/organizador/campanhas/{campaign}/receber', [CampaignPurchaseController::class, 'receive'])->name('organizador.compra.receive');
    Route::post('/organizador/pedidos/{order}/separar', [PurchaseController::class, 'prepare'])->name('organizador.pedidos.prepare');
    Route::post('/organizador/pedidos/{order}/entregar', [PurchaseController::class, 'handoff'])->middleware('throttle:20,1')->name('organizador.pedidos.handoff');
    Route::get('/fornecedor/campanhas/{campaign}/confirmacao', [ProposalController::class, 'legacyConfirmation'])->name('fornecedor.compra');
});

Route::prefix('admin')->middleware(['auth', 'can:administer-platform'])->group(function (): void {
    Route::get('/compras', [PurchaseFinanceController::class, 'index'])->name('admin.compras.index');
    Route::post('/compras/{campaign}/financeiro', [PurchaseFinanceController::class, 'record'])->name('admin.compras.record');
    Route::post('/compras/ocorrencias/{issue}/resolver', [PurchaseFinanceController::class, 'resolve'])->name('admin.compras.resolve');
    Route::post('/compras/{campaign}/cancelar', [CampaignPurchaseController::class, 'cancel'])->name('admin.compras.cancel');
    Route::post('/compras/{campaign}/concluir', [CampaignPurchaseController::class, 'complete'])->name('admin.compras.complete');
});

Route::post('/payments/test/events', [PurchaseController::class, 'webhook'])
    ->withoutMiddleware(PreventRequestForgery::class)
    ->middleware('throttle:60,1')->name('payments.test.events');
