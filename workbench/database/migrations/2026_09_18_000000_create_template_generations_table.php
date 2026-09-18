<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_generations', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('category');
            $table->string('format');
            $table->string('color_mode');
            $table->string('layout');
            $table->string('status');
            $table->string('current_step');
            $table->json('brief_json')->nullable();
            $table->json('raw_config')->nullable();
            $table->json('config')->nullable();
            $table->longText('image_prompt')->nullable();
            $table->string('image_path')->nullable();
            $table->string('preview_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_generations');
    }
};
