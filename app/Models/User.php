<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property UserRole $role
 * @property string|null $avatar_path
 * @property bool $must_change_password
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password', 'role'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token', 'avatar_path'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Khớp với DB default của cột `role` — Eloquent không tự đọc lại default
     * cấp DB vào object sau khi insert, nên phải khai báo default ở đây để
     * $user->role luôn có giá trị đúng ngay trong bộ nhớ, không cần refresh().
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => UserRole::Employee->value,
        'must_change_password' => false,
    ];

    /** @var list<string> */
    protected $appends = ['avatar'];

    public function getAvatarAttribute(): ?string
    {
        $path = $this->attributes['avatar_path'] ?? null;

        return $path === null
            ? null
            : route('media.avatars.show', ['filename' => basename($path)], absolute: false);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
            'role' => UserRole::class,
        ];
    }

    /**
     * Góp ý do người dùng này gửi.
     *
     * @return HasMany<Feedback, $this>
     */
    public function feedbacksSubmitted(): HasMany
    {
        return $this->hasMany(Feedback::class, 'user_id');
    }

    /**
     * Góp ý được giao cho người dùng này phụ trách (chỉ có ý nghĩa với role handler/admin).
     *
     * @return HasMany<Feedback, $this>
     */
    public function feedbacksAssigned(): HasMany
    {
        return $this->hasMany(Feedback::class, 'assigned_to');
    }

    /**
     * Thông báo trong ứng dụng dành cho người dùng này — đặt tên khác
     * `notifications()` của trait `Notifiable` để tránh đụng bảng `notifications`
     * chuẩn của Laravel (A6).
     *
     * @return HasMany<FeedbackNotification, $this>
     */
    public function feedbackNotifications(): HasMany
    {
        return $this->hasMany(FeedbackNotification::class);
    }

    /**
     * Các thiết bị/trình duyệt đã đăng ký nhận web push của người dùng này.
     *
     * @return HasMany<PushSubscription, $this>
     */
    public function pushSubscriptions(): HasMany
    {
        return $this->hasMany(PushSubscription::class);
    }
}
