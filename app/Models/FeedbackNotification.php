<?php

namespace App\Models;

use Database\Factories\FeedbackNotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $feedback_id
 * @property string $type
 * @property array<string, mixed> $data
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 */
class FeedbackNotification extends Model
{
    /** @use HasFactory<FeedbackNotificationFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    protected $fillable = ['user_id', 'feedback_id', 'type', 'data', 'read_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Feedback, $this>
     */
    public function feedback(): BelongsTo
    {
        return $this->belongsTo(Feedback::class);
    }
}
