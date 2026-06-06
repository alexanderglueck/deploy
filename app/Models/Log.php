<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Log extends Model
{
    use HasFactory;

    protected $fillable = [
        'deployment_id',
        'log',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'deployment_id',
    ];

    /**
     * @return BelongsTo
     */
    public function deployment()
    {
        return $this->belongsTo(Deployment::class);
    }
}
