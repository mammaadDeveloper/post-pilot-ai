<?php

namespace App\Models;

use App\Enums\SocialAccountStatus;
use App\Enums\SocialPlatform;
use Carbon\CarbonImmutable;
use Database\Factories\SocialAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $user_id
 * @property SocialPlatform $platform
 * @property string $remote_account_id
 * @property string|null $account_name
 * @property string|null $username
 * @property string|null $access_token
 * @property string|null $refresh_token
 * @property CarbonImmutable|null $token_expires_at
 * @property array<int, string>|null $scopes
 * @property array<string, mixed>|null $metadata
 * @property SocialAccountStatus $status
 * @property CarbonImmutable|null $disconnected_at
 */
#[Fillable([
    'user_id',
    'platform',
    'remote_account_id',
    'account_name',
    'username',
    'access_token',
    'refresh_token',
    'token_expires_at',
    'scopes',
    'metadata',
    'status',
    'disconnected_at',
])]
#[Hidden(['access_token', 'refresh_token'])]
class SocialAccount extends Model
{
    /** @use HasFactory<SocialAccountFactory> */
    use HasFactory;

    /**
     * Get the account owner.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get publication targets for this account.
     *
     * @return HasMany<PublicationTarget, $this>
     */
    public function publicationTargets(): HasMany
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
            'platform' => SocialPlatform::class,
            'status' => SocialAccountStatus::class,
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'immutable_datetime',
            'scopes' => 'array',
            'metadata' => 'array',
            'disconnected_at' => 'immutable_datetime',
        ];
    }
}
