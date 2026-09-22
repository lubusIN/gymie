<?php

namespace App\Models;

use App\Enums\Status;
use App\Models\Concerns\CascadesSoftDeletes;
use Database\Factories\EnquiryFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $name
 * @property string|null $email
 * @property string|null $contact
 * @property Carbon|null $date
 * @property string|null $gender
 * @property Carbon|null $dob
 * @property Status|null $status
 * @property string|null $address
 * @property string|null $country
 * @property string|null $city
 * @property string|null $state
 * @property string|null $pincode
 * @property array<int, mixed>|null $interested_in
 * @property string|null $source
 * @property string|null $goal
 * @property Carbon|null $start_by
 * @property-read User|null $user
 * @property-read Collection<int, FollowUp> $followUps
 */
class Enquiry extends Model
{
    /** @use HasFactory<EnquiryFactory> */
    use CascadesSoftDeletes, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'name',
        'email',
        'contact',
        'date',
        'gender',
        'dob',
        'status',
        'address',
        'country',
        'city',
        'state',
        'pincode',
        'interested_in',
        'source',
        'goal',
        'start_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'interested_in' => 'array',
            'date' => 'date',
            'dob' => 'date',
            'start_by' => 'date',
            'status' => Status::class,
        ];
    }

    /**
     * Get the followUps for the enquiry.
     */
    /**
     * @return HasMany<FollowUp, $this>
     */
    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    /**
     * Get the user for the enquiry.
     */
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relationship method names to cascade when deleting/restoring.
     *
     * @return list<string>
     */
    protected static function relationsToCascade(): array
    {
        return ['followUps'];
    }
}
