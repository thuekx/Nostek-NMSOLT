<?php

namespace App\Models;

use App\Http\Controllers\OrganizationController;
use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * UI-F1: POP/datacenter/area di dalam satu organisasi. `key` = `site_key` Control Center.
 * Site adalah filter dan pengelompokan, bukan batas keamanan (batasnya organisasi).
 *
 * Tidak memakai trait Auditable: `audit_logs.auditable_id` bertipe bigint, id site uuid.
 * Perubahan site dicatat {@see OrganizationController}.
 */
class Site extends Model
{
    use HasUuids;

    public const KINDS = ['pop', 'datacenter', 'area'];

    /** organization_id sengaja tidak fillable: diisi controller dari organisasi yang berlaku. */
    protected $fillable = ['key', 'name', 'kind', 'region', 'timezone'];

    protected static function booted(): void
    {
        static::addGlobalScope(new OrganizationScope);
    }

    /**
     * @return HasMany<SnmpOlt, $this>
     */
    public function olts(): HasMany
    {
        return $this->hasMany(SnmpOlt::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
