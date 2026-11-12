<?php

namespace App\Models;

use App\Enums\FeedbackStatus;
use Database\Factories\FeedbackFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property int $category_id
 * @property int|null $assigned_to
 * @property string $title
 * @property array<string, mixed> $content
 * @property string|null $excerpt
 * @property FeedbackStatus $status
 * @property string|null $closed_reason
 * @property Carbon|null $first_response_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $closed_at
 * @property int $reopened_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Feedback extends Model
{
    /** @use HasFactory<FeedbackFactory> */
    use HasFactory;

    protected $table = 'feedbacks';

    protected $fillable = [
        'user_id',
        'category_id',
        'assigned_to',
        'title',
        'content',
        'excerpt',
        'status',
        'closed_reason',
        'first_response_at',
        'resolved_at',
        'closed_at',
        'reopened_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'content' => 'array',
            'status' => FeedbackStatus::class,
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
            'reopened_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return HasMany<FeedbackMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(FeedbackMessage::class);
    }

    /**
     * @return HasMany<FeedbackActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(FeedbackActivity::class);
    }

    /**
     * Rút gọn nội dung Tiptap JSON thành văn bản thuần (tối đa 160 ký tự) để
     * hiển thị danh sách/dashboard mà không cần parse JSON ở client (mục 2.3).
     *
     * @param  array<string, mixed>  $content
     */
    public static function excerptFromContent(array $content): string
    {
        return Str::limit(trim(self::extractPlainText($content)), 160);
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function extractPlainText(array $node): string
    {
        if (($node['type'] ?? null) === 'text') {
            return (string) ($node['text'] ?? '');
        }

        $parts = [];

        foreach ((array) ($node['content'] ?? []) as $child) {
            if (is_array($child)) {
                $parts[] = self::extractPlainText($child);
            }
        }

        return implode(' ', array_filter($parts, fn (string $part): bool => $part !== ''));
    }
}
