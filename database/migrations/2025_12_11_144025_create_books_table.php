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
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('edition')->nullable();
            $table->string('publisher')->nullable();
            $table->date('year')->nullable();
            $table->string('format')->nullable();
            $table->integer('pages')->nullable();
            $table->string('country')->nullable();
            $table->string('isbn')->unique();
            $table->timestamps();
            
            // Індекси для швидкого пошуку
            $table->index('title');
            $table->index('year');
            $table->index('publisher');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};
