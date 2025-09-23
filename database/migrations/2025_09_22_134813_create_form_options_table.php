<?php

use Illuminate\Container\Attributes\DB;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB as FacadesDB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('form_options', function (Blueprint $table) {
            $table->id();
            $table->string('label');
            $table->bigInteger('tenant_id')->unsigned()->nullable();
            $table->index('tenant_id');
            $table->timestamps();
        });

        // Add FULLTEXT index (Laravel Blueprint does not support it)
        FacadesDB::statement('ALTER TABLE form_options ADD FULLTEXT fulltext_label (label)');

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('form_options');
    }
};
