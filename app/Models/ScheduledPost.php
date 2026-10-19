<?php

namespace App\Models;

use App\Enums\ScheduledPostStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ScheduledPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property string $idempotency_key
 * @property string $content
 * @property string|null $media_disk
 * @property string|null $media_path
 * @property string|null $media_mime_type
 * @property string|null $media_file_name
 * @property CarbonImmutable $scheduled_at
 * @property string $timezone
 * @property ScheduledPostStatus $status
 * @property CarbonImmutable|null $published_at
 * @property string|null $safe_error
 */
#[Fillable([
    'user_id',
    'idempotency_key',
    'content',
    'media_disk',
    'media_path',
    'media_mime_type',
    'media_file_name',
    'scheduled_at',
    'timezone',
    'status',
    'published_at',
    'safe_error',
])]
class ScheduledPost extends Model
{
    /** @use HasFactory<ScheduledPostFactory> */
    use HasFactory;

    /**
     * Get the post owner.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get each destination and publication outcome.
     *
     * @return HasMany<PublicationTarget, $this>
     */
    public function targets(): HasMany
    {
        return $this->hasMany(PublicationTarget::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'immutable_datetime',
            'published_at' => 'immutable_datetime',
            'status' => ScheduledPostStatus::class,
        ];
    }
}
