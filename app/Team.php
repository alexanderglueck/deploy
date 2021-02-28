<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'name'
    ];

    /**
     * @param User $user
     * @return mixed
     */
    public function addMember(User $user)
    {
        return TeamMembership::create([
            'user_id' => $user->id,
            'team_id' => $this->id
        ]);
    }

    /**
     * @param User $user
     * @return mixed
     */
    public function removeMember(User $user)
    {
        return TeamMembership::where([
            'user_id' => $user->id,
            'team_id' => $this->id
        ])->delete();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function members()
    {
        return $this->belongsToMany(User::class, 'team_memberships')->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function projects()
    {
        return $this->hasMany(Project::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function servers()
    {
        return $this->hasMany(Server::class);
    }
}
