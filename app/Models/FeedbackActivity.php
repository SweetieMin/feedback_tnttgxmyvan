<?php

namespace App\Models;

use App\Enums\ActivityType;
use Database\Factories\FeedbackActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $feedback_id
 * @property int $actor_id
 * @property ActivityType $type
 * @property string|null $from_value
 * @property string|null $to_value
 * @property string|null $note
 * @property Carbon|null $created_at
 */
class FeedbackActivity extends Model
{
    /** @use HasFactory<FeedbackActivityFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = ['feedback_id', 'actor_id', 'type', 'from_value', 'to_value', 'note'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
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
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
