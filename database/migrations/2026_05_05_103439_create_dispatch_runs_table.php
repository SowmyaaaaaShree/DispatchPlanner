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
        Schema::create('dispatch_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_batch_id')->constrained('dispatch_batches')->onDelete('cascade');
            $table->string('city');
            $table->string('country', 2);
            $table->date('dispatch_date');
            $table->decimal('total_invoiced_value_local', 15, 2)->default(0);
            $table->string('total_invoiced_value_currency', 3);
            $table->json('weather_summary')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dispatch_runs');
    }
};
