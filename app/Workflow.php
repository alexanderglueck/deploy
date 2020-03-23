<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Workflow extends Model
{
    protected $fillable = [
        'project_id',
        'server_id',
        'event',
        'actions',
    ];

    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
