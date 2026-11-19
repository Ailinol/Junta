<?php

namespace App\Models;

use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property positive-int $id
 * @property int|null $user_id
 * @property string $company_name
 * @property string $nuit
 * @property string $contact_name
 * @property string $email
 * @property string $phone
 * @property string $location
 * @property list<string> $categories
 * @property string|null $description
 * @property bool $verified
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'company_name', 'nuit', 'contact_name', 'email', 'phone', 'location', 'categories', 'description', 'verified'])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected function casts(): array
    {
        return ['categories' => 'array', 'verified' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Proposal, $this> */
    public function proposals(): HasMany
    {
        return $this->hasMany(Proposal::class);
    }
}
