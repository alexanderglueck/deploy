<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PendingDeployment extends Model
{
    protected $fillable = [
        'project_id',
        'event',
        'ref',
        'repository'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
