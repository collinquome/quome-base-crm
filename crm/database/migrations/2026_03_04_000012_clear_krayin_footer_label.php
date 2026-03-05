<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('core_config')
            ->where('code', 'general.settings.footer.label')
            ->update(['value' => '']);
    }

    public function down(): void
    {
        DB::table('core_config')
            ->where('code', 'general.settings.footer.label')
            ->update(['value' => 'Powered by <span style="color: rgb(14, 144, 217);"><a href="http://www.krayincrm.com" target="_blank">Krayin</a></span>, an open-source project by <span style="color: rgb(14, 144, 217);"><a href="https://webkul.com" target="_blank">Webkul</a></span>.']);
    }
};
