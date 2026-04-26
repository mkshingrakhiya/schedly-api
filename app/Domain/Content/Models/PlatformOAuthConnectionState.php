<?php

namespace App\Domain\Content\Models;

use App\Models\Concerns\HasUuid;
use App\Models\User;
use App\Models\Workspace;
use Database\Factories\PlatformOAuthConnectionStateFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $uuid
 * @property int $workspace_id
 * @property int $user_id
 * @property int $platform_id
 * @property Carbon $expires_at
 */
#[UseFactory(PlatformOAuthConnectionStateFactory::class)]
class PlatformOAuthConnectionState extends Model
{
    /** @use HasFactory<PlatformOAuthConnectionStateFactory> */
    use HasFactory, HasUuid;

    /**
     * @var string
     */
    protected $table = 'platform_oauth_connection_states';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'workspace_id',
        'user_id',
        'platform_id',
        'expires_at',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
