<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('purchase_phase')->default('interest')->index();
            $table->json('purchase_config')->nullable();
            $table->timestamp('payment_deadline')->nullable();
            $table->unsignedBigInteger('held_quantity')->default(0);
        });
        Schema::table('proposals', function (Blueprint $table): void {
            $table->unsignedBigInteger('minimum_quantity')->nullable();
            $table->timestamp('reserved_until')->nullable();
            $table->boolean('transport_included')->nullable();
            $table->unsignedBigInteger('transport_unit_cents')->nullable();
            $table->timestamp('supplier_confirmed_at')->nullable();
        });
        Schema::create('purchase_orders', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reference')->unique();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('participation_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('quantity');
            $table->string('status')->default('awaiting_payment');
            $table->string('receipt_status')->default('waiting');
            $table->string('delivery_method');
            $table->text('delivery_address')->nullable();
            $table->string('currency', 3)->default('MZN');
            foreach (['merchandise', 'commission', 'supplier_transport', 'delivery_fee', 'total'] as $amount) {
                $table->unsignedBigInteger($amount.'_cents');
            }
            $table->json('conditions');
            $table->string('terms_version');
            $table->timestamp('accepted_at');
            $table->timestamp('payment_deadline');
            $table->string('collection_code_hash')->nullable();
            $table->text('collection_code')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
            $table->index(['campaign_id', 'status']);
            $table->index(['user_id', 'id']);
        });
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->string('provider');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->boolean('holds_stock')->default(true);
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->index(['status', 'id']);
        });
        Schema::create('purchase_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_attempt_id')->unique()->constrained()->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->unsignedBigInteger('amount_cents');
            $table->string('status')->default('requested');
            $table->text('reason');
            $table->timestamps();
        });
        Schema::create('supply_orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->unique()->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->uuid('reference')->unique();
            $table->unsignedBigInteger('quantity');
            $table->unsignedBigInteger('received_quantity')->default(0);
            $table->string('status')->default('issued');
            $table->json('conditions');
            $table->timestamps();
        });
        Schema::create('purchase_issues', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('type');
            $table->text('description');
            $table->string('status')->default('open');
            $table->text('resolution')->nullable();
            $table->timestamps();
        });
        Schema::create('financial_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('purchase_order_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_key')->unique();
            $table->string('kind');
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3)->default('MZN');
            $table->string('reference');
            $table->text('reason');
            $table->timestamps();
            $table->index(['campaign_id', 'kind']);
        });
        Schema::create('test_gateway_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->string('kind');
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('amount_cents');
            $table->string('currency', 3);
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['test_gateway_transactions', 'financial_entries', 'purchase_issues', 'supply_orders', 'purchase_refunds', 'payment_attempts', 'purchase_orders'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('proposals', fn (Blueprint $table) => $table->dropColumn(['minimum_quantity', 'reserved_until', 'transport_included', 'transport_unit_cents', 'supplier_confirmed_at']));
        Schema::table('campaigns', fn (Blueprint $table) => $table->dropColumn(['purchase_phase', 'purchase_config', 'payment_deadline', 'held_quantity']));
    }
};
