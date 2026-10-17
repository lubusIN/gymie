<?php

namespace App\Models;

use App\Enums\Status;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property string|null $emergency_contact
 * @property Carbon|null $joining_date
 */
class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'photo',
        'name',
        'email',
        'status',
        'password',
        'contact',
        'emergency_contact',
        'dob',
        'joining_date',
        'gender',
        'address',
        'country',
        'city',
        'state',
        'pincode',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'dob' => 'date',
            'joining_date' => 'date',
            'status' => Status::class,
        ];
    }

    /**
     * Get the followUps for the user.
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    /**
     * Get the enquiries for the user.
     */
    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    /**
     * Get activity recorded for actions performed by this staff member.
     */
    public function staffActivities(): MorphMany
    {
        return $this->morphMany(StaffActivity::class, 'causer')
            ->where('log_name', StaffActivity::LOG_NAME);
    }

    /**
     * Get attendance recorded for this staff member.
     */
    public function staffAttendances(): HasMany
    {
        return $this->hasMany(StaffAttendance::class, 'staff_id');
    }

    /**
     * Get leave requests submitted for this staff member.
     */
    public function staffLeaves(): HasMany
    {
        return $this->hasMany(StaffLeave::class, 'staff_id');
    }

    /**
     * Get the URL for the user's Filament avatar.
     *
     * @return string|null The URL of the user's avatar or null if not set.
     */
    public function getFilamentAvatarUrl(): ?string
    {
        return $this->photo ? Storage::disk('public')->url((string) $this->photo) : null;
    }

    /**
     * Determine if the user can access the Filament panel.
     *
     * @param  Panel  $panel  The Filament panel instance.
     * @return bool True if the user can access the panel, false otherwise.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->status?->value === 'active';
    }
}
