<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A project-scoped environment variable, in the spirit of GitLab's CI/CD
 * variables: defined once on the project, forwarded into every deployment it
 * runs.
 *
 * Values are encrypted at rest and never returned by the API -- responses carry
 * the key and its flags, which is enough to see what a project defines without
 * turning the variable list into a way to read secrets back out.
 */
class ProjectVariable extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'project_id',
        'key',
        'value',
        'build_arg',
        'masked',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'project_id',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'encrypted',
            'build_arg' => 'boolean',
            'masked' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
