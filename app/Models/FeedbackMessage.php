<?php

namespace App\Models;

use App\Enums\MessageVisibility;
use App\Enums\UserRole;
use Database\Factories\FeedbackMessageFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $feedback_id
 * @property int $author_id
 * @property array<string, mixed> $body
 * @property MessageVisibility $visibility
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class FeedbackMessage extends Model
{
    /** @use HasFactory<FeedbackMessageFactory> */
    use HasFactory;

    protected $fillable = ['feedback_id', 'author_id', 'body', 'visibility'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'body' => 'array',
            'visibility' => MessageVisibility::class,
        ];
    }

    /**
     * @return BelongsTo<Feedback, $this>
     */
    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Lọc theo quyền xem của người gọi: nhân viên chỉ thấy message `public`,
     * handler/admin thấy cả `internal` (A4, mục 4.4).
     *
     * @param  Builder<FeedbackMessage>  $query
     * @return Builder<FeedbackMessage>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        return $query->when(
            $viewer->role === UserRole::Employee,
            fn (Builder $q) => $q->where('visibility', MessageVisibility::Public),
        );
    }
}
