<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Workflow extends Model
{
    use HasFactory;
    use HasPublicUlid;

    /**
     * Branch value matching any pushed branch.
     */
    public const BRANCH_ANY = '*';

    protected $fillable = [
        'project_id',
        'server_id',
        'event',
        'branch',
        'actions',
    ];

    /**
     * Whether this workflow applies to the deployment's branch. Null matches
     * the repository's default branch (from the webhook payload, falling back
     * to the project setting); '*' matches everything.
     */
    public function matchesBranch(Deployment $deployment): bool
    {
        if ($this->branch === self::BRANCH_ANY) {
            return true;
        }

        $branch = Str::startsWith($deployment->ref, 'refs/heads/')
            ? Str::after($deployment->ref, 'refs/heads/')
            : null;

        if (filled($this->branch)) {
            return $branch === $this->branch;
        }

        // Default-branch mode. Non-branch refs (manual deploys) count as the
        // default branch — that is what they end up cloning.
        if ($branch === null) {
            return true;
        }

        $default = $deployment->default_branch ?? $deployment->project?->default_branch;

        // Unknown default branch (generic triggers without the field):
        // match anything rather than silently breaking legacy callers.
        return $default === null || $branch === $default;
    }

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'project_id',
        'server_id',
    ];

    /**
     * @return HasMany
     */
    public function steps()
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('position');
    }

    /**
     * @return BelongsTo
     */
    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
