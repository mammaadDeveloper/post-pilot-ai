<?php

namespace App\Models;

use Database\Factories\BrandProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string $brand_name
 * @property string|null $description
 * @property string|null $industry
 * @property string|null $target_audience
 * @property string|null $preferred_tone
 * @property array<int, string> $preferred_languages
 * @property string|null $content_guidelines
 * @property string|null $avoid_terms
 */
#[Fillable([
    'brand_name',
    'description',
    'industry',
    'target_audience',
    'preferred_tone',
    'preferred_languages',
    'content_guidelines',
    'avoid_terms',
])]
class BrandProfile extends Model
{
    /** @use HasFactory<BrandProfileFactory> */
    use HasFactory;

    /**
     * Get the user who owns the brand profile.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'preferred_languages' => 'array',
        ];
    }
}
