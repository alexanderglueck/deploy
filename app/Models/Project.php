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
        'team_id',
    ];

    /**
     * Internal identifiers are never exposed to the front-end; the public
     * `ulid` is used instead.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'team_id',
    ];

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
        });
    }
}
