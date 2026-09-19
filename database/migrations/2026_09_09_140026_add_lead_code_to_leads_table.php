<?php
// database/migrations/xxxx_xx_xx_add_lead_code_to_leads_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddLeadCodeToLeadsTable extends Migration
{
    public function up()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->string('lead_code')->unique()->nullable()->after('id');
            $table->index('lead_code');
        });
    }

    public function down()
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropIndex(['lead_code']);
            $table->dropColumn('lead_code');
        });
    }
}