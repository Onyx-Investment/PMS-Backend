<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->nullable()
                ->constrained('projects')->cascadeOnDelete();
            $table->foreignId('client_id')->nullable()
                ->constrained('clients')->nullOnDelete();

            $table->enum('meeting_type', ['formal', 'informal'])->default('formal');
            $table->text('agenda')->nullable();
            $table->string('venue')->nullable();
            $table->dateTime('meeting_date');

            $table->foreignId('chair_person_id')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        // Attendees can be internal staff OR external client contacts —
        // exactly one of the two should be set per row, enforced in the
        // controller rather than a DB constraint (portable across engines).
        Schema::create('meeting_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')
                ->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()
                ->constrained('users')->cascadeOnDelete();
            $table->foreignId('client_contact_id')->nullable()
                ->constrained('client_contacts')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')->unique()
                ->constrained('meetings')->cascadeOnDelete();
            $table->text('summary')->nullable();
            $table->text('decisions')->nullable();
            $table->dateTime('next_meeting')->nullable();

            $table->foreignId('prepared_by')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()
                ->constrained('users')->nullOnDelete();

            $table->timestamps();
        });

        Schema::create('action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meeting_id')
                ->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('assigned_to')->nullable()
                ->constrained('users')->nullOnDelete();
            $table->text('description');
            $table->date('due_date')->nullable();
            $table->enum('status', ['open', 'in_progress', 'done', 'overdue'])
                ->default('open');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_items');
        Schema::dropIfExists('meeting_minutes');
        Schema::dropIfExists('meeting_attendees');
        Schema::dropIfExists('meetings');
    }
};
