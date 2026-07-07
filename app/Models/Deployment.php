<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Deployment extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'project_id',
        'event',
        'ref',
        'repository',
        'commit_sha',
        'actions',
        'received_at',
        'processed_at',
        'deployed_at',
        'failed_at',
        'canceled_at',
    ];

    /**
     * The accessors to append to the model's array / JSON form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'status',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'project_id',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'deployed_at' => 'datetime',
            'failed_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    /**
     * A coarse lifecycle status used by the UI (and to drive log polling).
     *
     * @return Attribute<string, never>
     */
    protected function status(): Attribute
    {
        return Attribute::get(function (): string {
            return match (true) {
                $this->isCanceled() => 'canceled',
                $this->isFailed() => 'failed',
                $this->isDeployed() => 'deployed',
                $this->isPending() => 'pending',
                default => 'deploying',
            };
        });
    }

    /**
     * Whether this deployment is still in flight (queued or running).
     */
    public function isActive(): bool
    {
        return ! $this->isDeployed() && ! $this->isFailed() && ! $this->isCanceled();
    }

    /**
     * @return BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return HasOne
     */
    public function log()
    {
        return $this->hasOne(Log::class);
    }

    public function steps()
    {
        return $this->hasMany(DeploymentStep::class)->orderBy('position');
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

    public function isFailed(): bool
    {
        return $this->failed_at != null;
    }

    /**
     * @return bool
     */
    public function isDeploying()
    {
        return ! $this->isPending() && ! $this->isDeployed() && ! $this->isFailed() && ! $this->isCanceled();
    }

    /**
     * Append output to this deployment's log.
     */
    public function appendLog(string $chunk): void
    {
        $log = $this->log;

        $log->update([
            'log' => $log->log.$chunk,
        ]);
    }

    protected static function booted()
    {
        static::created(function (Deployment $deployment) {
            Log::create([
                'deployment_id' => $deployment->id,
            ]);
        });
    }
}
