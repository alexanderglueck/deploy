<?php

namespace App\Models;

use App\Models\Concerns\HasPublicUlid;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One Docker dashboard lifecycle action (start/stop/restart/...) run against
 * a container — the audit trail behind the "Recent actions" card.
 */
class ContainerAction extends Model
{
    use HasPublicUlid;

    protected $fillable = [
        'server_id',
        'user_id',
        'container',
        'action',
        'successful',
        'output',
    ];

    /**
     * The accessors to append to the model's array / JSON form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'user_name',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'id',
        'server_id',
        'user_id',
        'user',
    ];

    protected function casts(): array
    {
        return [
            'successful' => 'boolean',
        ];
    }

    protected function userName(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->user?->name);
    }

    public function server(): BelongsTo
    {
        return $this->belongsTo(Server::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
