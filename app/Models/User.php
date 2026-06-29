<?php
/**
 * FILE: backend/app/Models/User.php
 * STATUS: DIUBAH (tambah kolom google_id, phone_verified_at, remember_login, relasi baru)
 */


namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    protected $table = 'users';

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'avatar',
        'gender',
        'date_of_birth',
        'address',
        'city',
        'status',
        'google_id',
        'google_avatar',
        'phone_verified_at',
        'email_verified_at',
        'remember_login',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'date_of_birth' => 'date',
            'password' => 'hashed',
            'remember_login' => 'boolean',
        ];
    }

    public function isSiswa(): bool
    {
        return $this->role === 'siswa';
    }

    public function isTutor(): bool
    {
        return $this->role === 'tutor';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->avatar ? asset('storage/'.$this->avatar) : null;
    }

    public function getIsPhoneAliasEmailAttribute(): bool
    {
        return is_string($this->email)
            && str_starts_with($this->email, 'phone_')
            && str_ends_with($this->email, '@TUTORKU.local');
    }

    public function getEmailDisplayAttribute(): string
    {
        if ($this->is_phone_alias_email && $this->phone) {
            return $this->phone;
        }

        return $this->email;
    }

    public function settings(): HasOne
    {
        return $this->hasOne(UserSetting::class);
    }

    public function tutorProfile(): HasOne
    {
        return $this->hasOne(TutorProfile::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'student_id');
    }

    public function studyLogs(): HasMany
    {
        return $this->hasMany(StudyLog::class, 'student_id');
    }

    public function forumPosts(): HasMany
    {
        return $this->hasMany(ForumPost::class);
    }

    public function forumComments(): HasMany
    {
        return $this->hasMany(ForumComment::class);
    }

    public function achievements(): HasMany
    {
        return $this->hasMany(UserAchievement::class);
    }

    public function sentMessages(): HasMany
    {
        return $this->hasMany(ChatMessage::class, 'sender_id');
    }

    public function loginActivities(): HasMany
    {
        return $this->hasMany(LoginActivity::class);
    }

    public function favoriteTutors(): HasMany
    {
        return $this->hasMany(TutorFavorite::class);
    }

    public function tutorLikes(): HasMany
    {
        return $this->hasMany(TutorLike::class);
    }

    public function fcmTokens(): HasMany
    {
        return $this->hasMany(UserFcmToken::class);
    }
}
