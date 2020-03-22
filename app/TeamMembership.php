<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class TeamMembership extends Model
{
    protected $fillable = [
        'user_id',
        'team_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }
}
