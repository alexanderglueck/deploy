<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'name',
        'repository',
        'default_branch',
        'git_base',
        'git_token_user',
        'git_token',
        'team_id',
    ];

    /**
     * Internal identifiers are never exposed to the front-end; the public
     * `ulid` is used instead. The webhook secret is only shown where the
     * controller passes it explicitly, and the git token is never shown at
     * all -- `has_git_token` says whether one is stored.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'team_id',
        'webhook_secret',
        'git_token',
    ];

    /**
     * @var array<int, string>
     */
    protected $appends = [
        'has_git_token',
    ];

    protected function casts(): array
    {
        return [
            'webhook_secret' => 'encrypted',
            'git_token' => 'encrypted',
        ];
    }

    /**
     * Whether a git token is stored, without revealing or decrypting it --
     * enough for a form to show "stored" and offer to replace it.
     */
    public function getHasGitTokenAttribute(): bool
    {
        return filled($this->attributes['git_token'] ?? null);
    }

    /**
     * Whether an incoming webhook's repository matches this project. Projects
     * without a configured repository accept any.
     */
    public function matchesRepository(?string $repository): bool
    {
        if ($this->repository === null) {
            return true;
        }

        return $repository !== null && strcasecmp($this->repository, $repository) === 0;
    }

    /**
     * @return BelongsTo
     */
    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return HasMany
     */
    public function deployments()
    {
        return $this->hasMany(Deployment::class)->latest();
    }

    /**
     * @return HasMany
     */
    public function workflows()
    {
        return $this->hasMany(Workflow::class);
    }

    protected static function booted()
    {
        static::creating(function (Project $project) {
            if (! $project->deploy_endpoint) {
                $project->deploy_endpoint = Str::uuid()->toString();
            }

            if (! $project->webhook_secret) {
                $project->webhook_secret = Str::random(40);
            }
        });
    }
}
