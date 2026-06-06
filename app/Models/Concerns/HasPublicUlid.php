<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUlids;

/**
 * Gives a model a random, non-sequential public identifier in its `ulid`
 * column — used for route-model binding and serialization — while the integer
 * primary key stays internal. This keeps row counts and identifiers opaque.
 *
 * It composes Laravel's HasUlids (lowercase ULID generation + format-validated
 * route binding) and only points it at the `ulid` column instead of the key.
 * Because the key name stays `id`, the primary key remains an auto-incrementing
 * bigint, and the value is assigned in Model::performInsert() (not a `creating`
 * event), so it survives Event::fake()/saveQuietly().
 */
trait HasPublicUlid
{
    use HasUlids;

    /**
     * Generate the ULID for the public `ulid` column, not the primary key.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['ulid'];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }
}
