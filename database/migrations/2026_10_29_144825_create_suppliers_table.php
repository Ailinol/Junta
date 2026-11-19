<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suppliers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('company_name');
            $table->string('nuit', 40)->unique();
            $table->string('contact_name');
            $table->string('email');
            $table->string('phone', 40);
            $table->string('location');
            $table->json('categories');
            $table->text('description')->nullable();
            $table->boolean('verified')->default(false)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suppliers');
    }
};
