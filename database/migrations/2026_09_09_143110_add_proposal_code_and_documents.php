<?php
// database/migrations/xxxx_xx_xx_add_proposal_code_and_documents.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddProposalCodeAndDocuments extends Migration
{
    public function up()
    {
        Schema::table('proposals', function (Blueprint $table) {
            $table->string('proposal_code')->unique()->nullable()->after('id');
            $table->string('title')->nullable()->after('lead_id');
            $table->dropColumn('version');
        });

        Schema::create('proposal_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained()->onDelete('cascade');
            $table->string('document_name');
            $table->string('file_path');
            $table->integer('file_size')->nullable();
            $table->string('mime_type')->nullable();
            $table->foreignId('uploaded_by')->constrained('users');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('proposal_documents');
        
        Schema::table('proposals', function (Blueprint $table) {
            $table->dropColumn(['proposal_code', 'title']);
            $table->integer('version')->default(1);
        });
    }
}