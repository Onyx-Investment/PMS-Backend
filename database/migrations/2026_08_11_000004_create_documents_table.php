<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')->cascadeOnDelete();

            $table->enum('category', [
                'proposal', 'report', 'minutes', 'presentation', 'spreadsheet', 'manual',
            ])->default('report');

            $table->unsignedInteger('version')->default(1);
            // Storage-relative path (e.g. documents/12/report_v1.pdf), not a URL —
            // build the download URL from Storage::url() at read time so the
            // disk (local/S3) can change without touching stored data.
            $table->string('file_path');
            $table->string('original_filename');
            $table->unsignedBigInteger('file_size')->nullable();

            $table->foreignId('uploaded_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('documents');
    }
};
