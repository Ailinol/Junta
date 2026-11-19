<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->restrictOnDelete();
            $table->foreignId('supplier_id')->constrained()->restrictOnDelete();
            $table->decimal('unit_price', 14, 2);
            $table->unsignedBigInteger('available_quantity');
            $table->decimal('total_estimated', 18, 2);
            $table->unsignedInteger('delivery_days');
            $table->string('delivery_mode', 20);
            $table->timestamp('valid_until');
            $table->string('status', 40)->default('submetida')->index();
            $table->text('notes')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};
