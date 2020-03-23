<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Project extends Model
{
    protected $fillable = [
        'name',
        'team_id'
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function pendingDeployments()
    {
        return $this->hasMany(PendingDeployment::class);
    }

    public function deployments()
    {
        return $this->hasMany(Deployment::class);
    }

    public function workflows()
    {
        return $this->hasMany(Workflow::class);
    }

    protected static function booted()
    {
        static::creating(function (Project $project) {
            if ( ! $project->deploy_endpoint) {
                $project->deploy_endpoint = Str::uuid()->toString();
            }
        });
    }
}
