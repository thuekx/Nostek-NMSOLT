<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * UI-F1: POP/datacenter/area di dalam satu organisasi. `key` = `site_key` Control Center.
 * Site adalah filter dan pengelompokan, bukan batas keamanan (batasnya organisasi).
 */
class Site extends Model
{
    use HasUuids;

    protected $fillable = ['organization_id', 'key', 'name', 'kind', 'region', 'timezone'];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
