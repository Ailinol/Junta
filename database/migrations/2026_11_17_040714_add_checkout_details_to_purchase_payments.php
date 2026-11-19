<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->timestamp('accepted_at')->nullable()->change();
        });
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->string('payment_method')->nullable();
            $table->text('payment_phone')->nullable();
            $table->timestamp('next_check_at')->nullable()->index();
            $table->timestamp('escalated_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('purchase_orders')->whereNull('accepted_at')->exists()) {
            throw new RuntimeException('Cannot restore required acceptance timestamps while unaccepted orders exist. No data was changed.');
        }
        Schema::table('purchase_orders', function (Blueprint $table): void {
            $table->timestamp('accepted_at')->nullable(false)->change();
        });
        Schema::table('payment_attempts', function (Blueprint $table): void {
            $table->dropIndex(['next_check_at']);
            $table->dropColumn(['payment_method', 'payment_phone', 'next_check_at', 'escalated_at']);
        });
    }
};
