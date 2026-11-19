<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('organizer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('organizer_name');
            $table->string('title');
            $table->text('description');
            $table->string('category', 40);
            $table->string('status', 40)->default('rascunho')->index();
            $table->unsignedBigInteger('target_quantity');
            $table->string('unit', 60);
            $table->decimal('estimated_price', 14, 2)->nullable();
            $table->timestamp('deadline')->index();
            $table->string('location');
            $table->string('delivery_mode', 20);
            $table->string('image_url', 2048)->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
