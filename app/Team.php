<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    public function members()
    {
        return $this->belongsToMany(User::class, 'team_memberships')->withTimestamps();
    }

    public function addMember(User $user)
    {
        return TeamMembership::create([
            'user_id' => $user->id,
            'team_id' => $this->id
        ]);
    }

    public function removeMember(User $user)
    {
        return TeamMembership::where([
            'user_id' => $user->id,
            'team_id' => $this->id
        ])->delete();
    }

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
