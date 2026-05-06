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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispatch_batch_id')->constrained('dispatch_batches')->onDelete('cascade');
            $table->string('order_id');
            $table->timestamp('placed_at');
            $table->text('destination_address');
            $table->string('destination_country', 2); // IN or DE
            $table->decimal('total_value', 15, 2);
            $table->string('total_value_currency', 3);
            $table->integer('weight_grams');
            $table->enum('payment_mode', ['prepaid', 'cod']);
            $table->string('geocoded_city')->nullable();
            $table->decimal('geocoded_lat', 10, 7)->nullable();
            $table->decimal('geocoded_lon', 10, 7)->nullable();
            $table->date('planned_dispatch_date')->nullable();
            $table->boolean('is_deferred')->default(false);
            $table->string('deferred_reason')->nullable();
            $table->decimal('invoiced_value_local', 15, 2)->nullable();
            $table->string('invoiced_value_currency', 3)->nullable();
            $table->enum('status', ['pending', 'processed', 'failed'])->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
