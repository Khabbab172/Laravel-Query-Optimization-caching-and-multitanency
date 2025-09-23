<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('option_id')->constrained('form_options')->cascadeOnDelete();
            $table->bigInteger('tenant_id')->unsigned()->nullable();
            $table->index('tenant_id');
            $table->string('value')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'option_id']);
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_data');
    }
};
