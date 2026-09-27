<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Фото-доказ до скарги — той самий підхід, що й report_attachments:
        // оригінал без стиснення, порядок — позицією.
        Schema::create('union_complaint_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('union_complaint_id')->constrained()->cascadeOnDelete();
            $table->string('disk_path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('size')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('union_complaint_attachments');
    }
};
