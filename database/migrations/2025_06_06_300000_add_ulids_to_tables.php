<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Adds a public, non-sequential `ulid` identifier to every model that is exposed
 * in URLs or JSON, so integer primary keys (and therefore row counts) never leak.
 *
 * Idempotent and production-safe: skips columns that already exist and backfills
 * existing rows before adding the unique index.
 */
return new class extends Migration
{
    /**
     * Tables that gain a public ULID.
     *
     * @var array<int, string>
     */
    private array $tables = [
        'users',
        'teams',
        'projects',
        'servers',
        'workflows',
        'deployments',
    ];

    public function up(): void
    {
        foreach ($this->tables as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'ulid')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->ulid('ulid')->nullable()->after('id');
            });

            // Backfill existing rows with a unique ULID.
            DB::table($table)->select('id')->whereNull('ulid')->orderBy('id')
                ->chunkById(500, function ($rows) use ($table) {
                    foreach ($rows as $row) {
                        DB::table($table)->where('id', $row->id)->update([
                            'ulid' => strtolower((string) Str::ulid()),
                        ]);
                    }
                });

            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->unique('ulid');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'ulid')) {
                Schema::table($table, function (Blueprint $blueprint) use ($table) {
                    $blueprint->dropUnique($table.'_ulid_unique');
                    $blueprint->dropColumn('ulid');
                });
            }
        }
    }
};
