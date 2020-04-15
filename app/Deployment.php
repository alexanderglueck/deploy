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
        'actions',
        'received_at',
        'processed_at',
        'deployed_at',
        'canceled_at',
    ];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function log()
    {
        return $this->hasOne(Log::class);
    }

    /**
     * @return bool
     */
    public function isCanceled()
    {
        return $this->canceled_at != null;
    }

    /**
     * @return bool
     */
    public function isPending()
    {
        return $this->processed_at == null;
    }

    /**
     * @return bool
     */
    public function isDeployed()
    {
        return $this->deployed_at != null;
    }

    /**
     * @return bool
     */
    public function isDeploying()
    {
        return ! $this->isPending() && ! $this->isDeployed() && ! $this->isCanceled();
    }

    /**
     *
     */
    protected static function booted()
    {
        static::created(function (Deployment $deployment) {
            Log::create([
                'deployment_id' => $deployment->id
            ]);
        });
    }
}
