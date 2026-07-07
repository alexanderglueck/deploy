<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkflowStep extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'workflow_id',
        'position',
        'type',
        'config',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'workflow_id',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }

    /**
     * @return BelongsTo
     */
    public function workflow()
    {
        return $this->belongsTo(Workflow::class);
    }
}
