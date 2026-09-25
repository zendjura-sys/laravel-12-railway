<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Знімки родини (події, бої, зустрічі) для каруселі на головній —
        // окремо від фото-доказів у звітах (Reports) і від аватарок
        // учасників: сюди завантажує лише адмін через Дизайн → Галерея.
        Schema::create('family_gallery_photos', function (Blueprint $table) {
            $table->id();
            $table->string('path');
            $table->string('caption')->nullable();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('family_gallery_photos');
    }
};
