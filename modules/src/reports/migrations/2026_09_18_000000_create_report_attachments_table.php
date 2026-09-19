<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Фото-доказ до звіту — довільна кількість, оригінал зберігається
        // без стиснення чи ресайзу (диск 'public', ідентично логотипу
        // родини в DesignController): якість не мусить страждати, а самі
        // фото зазвичай — це скріншот гри чи чат, де важлива кожна деталь.
        Schema::create('report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('report_id')->constrained()->cascadeOnDelete();
            $table->string('disk_path');
            $table->string('original_name')->nullable();
            $table->unsignedInteger('size')->default(0);
            // Порядок у "альбомі" — той, у якому людина додавала файли.
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('report_attachments');
    }
};
