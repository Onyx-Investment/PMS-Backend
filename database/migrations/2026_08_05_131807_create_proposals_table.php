<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lead_id')
                ->constrained('leads')->cascadeOnDelete();

            $table->string('proposal_no');
            $table->unsignedInteger('version')->default(1);
            $table->date('submission_date')->nullable();

            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'won', 'lost', 'converted'])
                ->default('draft');

            $table->foreignId('prepared_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();

            $table->timestamps();

            // proposal_no is stable across versions of the same proposal;
            // the pair is what's actually unique.
            $table->unique(['proposal_no', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposals');
    }
};