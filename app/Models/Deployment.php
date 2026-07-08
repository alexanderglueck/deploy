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
        'default_branch',
        'repository',
        'commit_sha',
        'image',
        'image_available_at',
        'image_checked_at',
        'rollback_of_id',
        'retry_of_id',
        'triggered_by',
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
        'is_rollback',
        'is_retry',
        'triggered_by_name',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'project_id',
        'rollback_of_id',
        'retry_of_id',
        'triggered_by',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'processed_at' => 'datetime',
            'deployed_at' => 'datetime',
            'failed_at' => 'datetime',
            'canceled_at' => 'datetime',
            'image_available_at' => 'datetime',
            'image_checked_at' => 'datetime',
        ];
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isRollback(): Attribute
    {
        return Attribute::get(fn (): bool => $this->rollback_of_id !== null);
    }

    /**
     * @return Attribute<bool, never>
     */
    protected function isRetry(): Attribute
    {
        return Attribute::get(fn (): bool => $this->retry_of_id !== null);
    }

    /**
     * Who triggered this deployment: a user's name, or null for webhooks.
     *
     * @return Attribute<string|null, never>
     */
    protected function triggeredByName(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->triggeredBy?->name);
    }

    public function triggeredBy()
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function retryOf()
    {
        return $this->belongsTo(self::class, 'retry_of_id');
    }

    /**
     * Whether the SHA-tagged image is (as of the last reconcile) still
     * present on the target, making an instant rollback possible.
     */
    public function hasAvailableImage(): bool
    {
        return $this->image !== null && $this->image_available_at !== null;
    }

    public function rollbackOf()
    {
        return $this->belongsTo(self::class, 'rollback_of_id');
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
