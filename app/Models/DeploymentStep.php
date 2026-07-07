<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeploymentStep extends Model
{
    use HasFactory;
    use HasPublicUlid;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'deployment_id',
        'position',
        'type',
        'config',
        'status',
        'exit_code',
        'output',
        'started_at',
        'finished_at',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'deployment_id',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * Append output to this step as it is produced.
     */
    public function appendOutput(string $chunk): void
    {
        $this->update([
            'output' => ($this->output ?? '').$chunk,
        ]);
    }

    /**
     * @return BelongsTo
     */
    public function deployment()
    {
        return $this->belongsTo(Deployment::class);
    }
}
