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
        Schema::create('tests', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('a_variant')->nullable();
            $table->string('b_variant')->nullable();
            $table->string('c_variant')->nullable();
            $table->string('d_variant')->nullable();
            $table->string('correct_answer')->nullable();
            $table->string('key')->nullable();
            $table->integer('test_number')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tests');
    }
};
