<?php

namespace App\Models;

use App\Enums\PublicationTargetStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PublicationTargetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $scheduled_post_id
 * @property int $social_account_id
 * @property PublicationTargetStatus $status
 * @property int $attempts
 * @property string|null $remote_post_id
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $last_attempt_at
 * @property CarbonImmutable|null $dispatched_at
 * @property CarbonImmutable|null $retry_at
 * @property string $idempotency_key
 * @property string|null $failure_type
 * @property string|null $safe_error
 */
#[Fillable([
    'scheduled_post_id',
    'social_account_id',
    'status',
    'attempts',
    'remote_post_id',
    'published_at',
    'last_attempt_at',
    'dispatched_at',
    'retry_at',
    'idempotency_key',
    'failure_type',
    'safe_error',
])]
class PublicationTarget extends Model
{
    /** @use HasFactory<PublicationTargetFactory> */
    use HasFactory;

    /**
     * Get the scheduled content.
     *
     * @return BelongsTo<ScheduledPost, $this>
     */
    public function scheduledPost(): BelongsTo
    {
        return $this->belongsTo(ScheduledPost::class);
    }

    /**
     * Get the destination account.
     *
     * @return BelongsTo<SocialAccount, $this>
     */
    public function socialAccount(): BelongsTo
    {
        return $this->belongsTo(SocialAccount::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PublicationTargetStatus::class,
            'attempts' => 'integer',
            'published_at' => 'immutable_datetime',
            'last_attempt_at' => 'immutable_datetime',
            'dispatched_at' => 'immutable_datetime',
            'retry_at' => 'immutable_datetime',
        ];
    }
}
