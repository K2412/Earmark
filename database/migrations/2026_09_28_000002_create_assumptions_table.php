<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Task #1258: an effective-dated, source-labelled catalogue of Canadian
     * planning assumptions. System rows (household_id null) ship as defaults; a
     * household can add its own row for the same key to override them without
     * touching application code. Calculators read resolved values through the
     * catalogue service.
     */
    public function up(): void
    {
        Schema::create('assumptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignId('household_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('jurisdiction')->default('CA');
            $table->bigInteger('value');
            $table->string('unit'); // cents | bps | count
            $table->unsignedSmallInteger('effective_year');
            $table->string('source_url')->nullable();
            $table->date('source_date')->nullable();
            $table->string('catalogue_version');
            $table->string('notes')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['key', 'jurisdiction', 'effective_year']);
        });

        $now = now();
        $defaults = [
            ['key' => 'tfsa_limit', 'value' => 700000, 'unit' => 'cents'],
            ['key' => 'rrsp_limit', 'value' => 3210000, 'unit' => 'cents'],
            ['key' => 'fhsa_limit', 'value' => 800000, 'unit' => 'cents'],
            ['key' => 'inflation_rate', 'value' => 200, 'unit' => 'bps'],
            ['key' => 'cpp_max_monthly', 'value' => 143000, 'unit' => 'cents'],
            ['key' => 'oas_max_monthly', 'value' => 71300, 'unit' => 'cents'],
        ];

        foreach ($defaults as $default) {
            DB::table('assumptions')->insert([
                'id' => (string) Str::ulid(),
                'household_id' => null,
                'key' => $default['key'],
                'jurisdiction' => 'CA',
                'value' => $default['value'],
                'unit' => $default['unit'],
                'effective_year' => 2026,
                'source_url' => 'https://www.canada.ca/en/revenue-agency.html',
                'source_date' => '2026-01-01',
                'catalogue_version' => '2026.1',
                'notes' => 'System default estimate',
                'created_by_user_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('assumptions');
    }
};
