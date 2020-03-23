<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Deployment extends Model
{
    protected $fillable = [
        'project_id',
        'event',
        'ref',
        'repository',
        'processed_at',
        'deployed_at'
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function inProgress()
    {
        return $this->deployed_at == null;
    }
}
