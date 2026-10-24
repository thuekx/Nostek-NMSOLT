<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\SnmpOlt;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Titik tunggal untuk menulis baris audit. Otomatis menangkap aktor (user),
 * IP, dan user-agent dari request berjalan bila ada.
 */
class AuditLogger
{
    /**
     * @param  array<string, mixed>  $properties
     */
    public static function log(
        string $event,
        ?Model $auditable = null,
        array $properties = [],
        ?string $description = null,
        ?Authenticatable $actor = null,
    ): ?AuditLog {
        $actor ??= auth()->user();
        $request = request();
        $organizationId = static::organizationFor($auditable, $actor);
        $context = app(OrganizationContext::class);

        // UI-F1d: login/logout/login gagal menulis untuk organisasi aktor, bukan konteks request.
        return app(TenantDatabase::class)->asOrganization(
            $organizationId,
            $organizationId === null || $context->isPrimary($organizationId),
            fn () => static::write($event, $auditable, $properties, $description, $actor, $organizationId, $request),
        );
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private static function write(
        string $event,
        ?Model $auditable,
        array $properties,
        ?string $description,
        ?Authenticatable $actor,
        ?string $organizationId,
        ?Request $request,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => $actor?->getAuthIdentifier(),
            'organization_id' => $organizationId,
            'user_name' => $actor?->name,
            'event' => $event,
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'description' => $description,
            'properties' => $properties === [] ? null : $properties,
            'ip_address' => $request?->ip(),
            'user_agent' => $request !== null ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
        ]);
    }

    /**
     * UI-F1a: organisasi pemilik baris audit. Subjek ber-OLT menentukan (OLT itu sendiri atau
     * baris ber-`snmp_olt_id`), lalu organisasi aktor. NULL = baris instalasi (sistem tanpa
     * subjek organisasi), hanya terlihat oleh organisasi instalasi.
     */
    private static function organizationFor(?Model $auditable, ?Authenticatable $actor): ?string
    {
        if ($auditable instanceof SnmpOlt && $auditable->organization_id !== null) {
            return $auditable->organization_id;
        }

        $oltId = $auditable?->getAttribute('snmp_olt_id');
        if ($oltId !== null) {
            $organizationId = DB::table('snmp_olts')->where('id', $oltId)->value('organization_id');
            if ($organizationId !== null) {
                return (string) $organizationId;
            }
        }

        return $actor instanceof User ? app(OrganizationContext::class)->organizationOf($actor) : null;
    }

    /**
     * Tulis audit untuk perubahan model (created/updated/deleted) dengan
     * deskripsi yang dibangun dari label & judul model.
     *
     * @param  array<string, mixed>  $properties
     */
    public static function model(string $event, Model $model, array $properties = []): ?AuditLog
    {
        $label = method_exists($model, 'auditLabel') ? $model->auditLabel() : class_basename($model);
        $title = method_exists($model, 'auditTitle') ? (string) $model->auditTitle() : '#'.$model->getKey();

        $verb = [
            AuditLog::EVENT_CREATED => 'Menambahkan',
            AuditLog::EVENT_UPDATED => 'Memperbarui',
            AuditLog::EVENT_DELETED => 'Menghapus',
        ][$event] ?? $event;

        // description tetap disimpan dalam bahasa Indonesia (fallback pencarian LIKE & baris lama
        // sebelum kolom ini ada) — tapi frontend TAK memakainya untuk render bila subject_label/
        // subject_title tersedia (lihat resources/js/lib/audit.js): frasa dirangkai ulang sesuai
        // locale VIEWER saat ditampilkan, bukan locale penulis saat event terjadi.
        $description = trim(sprintf('%s %s %s', $verb, $label, $title));
        $properties = ['subject_label' => $label, 'subject_title' => $title] + $properties;

        return static::log($event, $model, $properties, $description);
    }
}
