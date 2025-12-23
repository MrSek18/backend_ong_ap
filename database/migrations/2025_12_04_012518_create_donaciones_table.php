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
        Schema::create('donaciones', function (Blueprint $table) {
            $table->id();
            $table->string('payment_id')->nullable(); // ID del pago en Mercado Pago
            $table->decimal('monto', 10, 2);          // transaction_amount
            $table->string('estado')->default('pending');
            $table->enum('plan', ['unica', 'mensual']); // lógica de donación
            $table->string('email');                   // payer.email
            $table->string('identification_type');     // payer.identification.type
            $table->string('identification_number');   // payer.identification.number
            $table->string('payment_method_id');       // payment_method_id
            $table->integer('installments');           // installments
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('donaciones');
    }
};
