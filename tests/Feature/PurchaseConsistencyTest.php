<?php

use App\Models\Campaign;
use App\Models\Participation;
use App\Models\Proposal;
use App\Models\User;
use App\Support\PurchaseData;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\HttpKernel\Exception\HttpException;

test('campaign read contracts expose the effective purchase stage without replacing historic states', function (string $phase, string $stage) {
    $this->withoutVite();
    $consumer = User::factory()->create();
    $campaign = Campaign::factory()->create(['status' => 'proposta_selecionada', 'purchase_phase' => $phase]);
    Participation::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $consumer->id]);

    $this->actingAs($consumer)->get(route('consumidor.acompanhamento.show', ['id' => $campaign->id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('business.campaigns.0.effectiveStage', $stage)
            ->where('purchase.effectiveStage', $stage)->where('purchase.status', 'proposta_selecionada'));
})->with([['interest', 'selection'], ['payment', 'payment'], ['review', 'review'], ['supply', 'supply'], ['completed', 'completed'], ['cancelled', 'cancelled']]);

test('tracking without an identifier redirects to the most recent own participation deterministically', function () {
    $consumer = User::factory()->create();
    $old = Participation::factory()->create(['user_id' => $consumer->id]);
    $recent = Participation::factory()->create(['user_id' => $consumer->id]);
    Participation::factory()->create();

    $this->actingAs($consumer)->get(route('consumidor.acompanhamento'))
        ->assertRedirect(route('consumidor.acompanhamento.show', ['id' => $recent->campaign_id]));
    $this->get(route('consumidor.acompanhamento.show', ['id' => $recent->campaign_id]))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('purchase.id', $recent->campaign_id));
    expect($old->campaign_id)->not->toBe($recent->campaign_id);
});

test('tracking without any participation returns an empty state and no other account purchase', function () {
    $this->withoutVite();
    Participation::factory()->create();
    $this->actingAs(User::factory()->create())->get(route('consumidor.acompanhamento'))->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('purchase', null)->has('business.participations', 0));
});

test('public catalogue and detail use the same publication rule while own historical tracking remains accessible', function (string $status) {
    $this->withoutVite();
    $consumer = User::factory()->create();
    $campaign = Campaign::factory()->create(['status' => $status]);
    Participation::factory()->create(['campaign_id' => $campaign->id, 'user_id' => $consumer->id]);
    $visible = in_array($status, ['ativa', 'em_avaliacao'], true);

    $this->get(route('campanhas.index'))->assertOk()->assertInertia(fn (Assert $page) => $page->has('business.campaigns', $visible ? 1 : 0));
    $response = $this->get(route('campanhas.show', ['id' => $campaign->id]));
    if ($visible) {
        $response->assertOk();
    } else {
        $response->assertNotFound();
    }
    if ($status !== 'rascunho') {
        $this->actingAs($consumer)->get(route('consumidor.acompanhamento.show', ['id' => $campaign->id]))->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('purchase.id', $campaign->id));
    }
})->with(['rascunho', 'ativa', 'em_avaliacao', 'proposta_selecionada', 'confirmada', 'em_fornecimento', 'concluida', 'cancelada']);

test('administrative campaign maintenance cannot cancel reopen or bypass official commercial transitions', function (string $from, string $to) {
    $admin = User::factory()->create(['is_admin' => true]);
    $campaign = Campaign::factory()->create(['organizer_id' => $admin->id, 'status' => $from]);
    $this->actingAs($admin)->patch(route('admin.campanhas.update', $campaign), [
        'organizerId' => $admin->id, 'title' => $campaign->title, 'description' => $campaign->description,
        'category' => $campaign->category, 'status' => $to, 'unit' => $campaign->unit,
        'targetQuantity' => $campaign->target_quantity, 'deadline' => now()->addDays(3)->toIso8601String(),
        'location' => $campaign->location, 'deliveryMode' => $campaign->delivery_mode,
    ])->assertSessionHasErrors('status');
    expect($campaign->fresh()->status)->toBe($from);
})->with([['ativa', 'cancelada'], ['ativa', 'em_avaliacao'], ['em_avaliacao', 'ativa'], ['cancelada', 'ativa']]);

test('private purchase data cannot be requested by a different account', function () {
    $campaign = Campaign::factory()->create();
    $consumer = User::factory()->create();
    expect(fn () => app(PurchaseData::class)->consumer($campaign, $consumer))
        ->toThrow(HttpException::class);
});

test('administration cannot rewrite published conditions after interest or an offer has been recorded', function (bool $offer) {
    $admin = User::factory()->create(['is_admin' => true]);
    $campaign = Campaign::factory()->create(['organizer_id' => $admin->id]);
    if ($offer) {
        Proposal::factory()->create(['campaign_id' => $campaign->id]);
    } else {
        Participation::factory()->create(['campaign_id' => $campaign->id]);
    }
    $this->actingAs($admin)->patch(route('admin.campanhas.update', $campaign), [
        'organizerId' => $admin->id, 'title' => 'Changed after commitment', 'description' => $campaign->description,
        'category' => $campaign->category, 'status' => 'ativa', 'unit' => $campaign->unit,
        'targetQuantity' => 100, 'deadline' => now()->addDays(3)->toIso8601String(),
        'location' => $campaign->location, 'deliveryMode' => $campaign->delivery_mode,
    ])->assertSessionHasErrors('campaign');
    expect($campaign->fresh()->title)->toBe($campaign->title);
})->with([true, false]);
