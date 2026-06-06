<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Workflow extends Model
{
    use HasFactory;
    use HasPublicUlid;

    protected $fillable = [
        'project_id',
        'server_id',
        'event',
        'actions',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'project_id',
        'server_id',
    ];

    /**
     * @return BelongsTo
     */
    public function server()
    {
        return $this->belongsTo(Server::class);
    }

    /**
     * @return BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
