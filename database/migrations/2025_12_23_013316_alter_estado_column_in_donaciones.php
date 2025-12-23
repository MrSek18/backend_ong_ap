<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::table('donaciones', function (Blueprint $table) {
            $table->string('estado')->default('pending')->change();
        });
    }

    public function down(): void
    {
        Schema::table('donaciones', function (Blueprint $table) {
            $table->enum('estado', ['pending', 'approved', 'rejected'])->default('pending')->change();
        });
    }

};
