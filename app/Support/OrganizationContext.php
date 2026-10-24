<?php

namespace App\Support;

use App\Models\Scopes\PartnerOltScope;
use App\Models\User;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * UI-F1: organisasi yang sedang berlaku untuk request/job ini.
 *
 * - User login (web, API token, webhook bot partner): organisasi pilihannya
 *   (`users.current_organization_id`) bila ia anggota, selain itu keanggotaan pertamanya.
 *   Tanpa keanggotaan = tidak ada organisasi = tidak melihat apa pun.
 * - Bot Telegram global (tamu yang dibatasi {@see PartnerOltScope::restrictGuestToGlobalOlts()}):
 *   organisasi instalasi.
 * - Console/queue tanpa auth (polling, alarm, retensi): tidak dibatasi.
 *
 * Didaftarkan `scoped` (AppServiceProvider): satu instance per request/job, sehingga cache di sini
 * tidak terbawa ke job berikutnya di worker yang berumur panjang.
 */
class OrganizationContext
{
    private ?string $primaryId = null;

    /** @var array<int, ?string> */
    private array $userOrganization = [];

    /** @var array<int, list<string>> */
    private array $memberships = [];

    /** Organisasi instalasi (`is_installation`, dibuat migrasi UI-F1a, tepat satu). */
    public function primaryId(): ?string
    {
        return $this->primaryId ??= DB::table('organizations')
            ->where('is_installation', true)
            ->value('id');
    }

    /** Apakah query saat ini harus dibatasi ke satu organisasi. */
    public function restricted(): bool
    {
        return auth()->user() !== null || PartnerOltScope::guestRestrictedToGlobal();
    }

    /** Organisasi yang berlaku; null = tidak ada (user tanpa keanggotaan) atau konteks console. */
    public function currentId(): ?string
    {
        $user = auth()->user();

        if ($user instanceof User) {
            return $this->organizationOf($user);
        }

        return PartnerOltScope::guestRestrictedToGlobal() ? $this->primaryId() : null;
    }

    /** Organisasi untuk data baru: milik user yang membuat, selain itu organisasi instalasi. */
    public function currentOrPrimaryId(): ?string
    {
        return $this->currentId() ?? $this->primaryId();
    }

    public function isPrimary(?string $organizationId): bool
    {
        return $organizationId !== null && $organizationId === $this->primaryId();
    }

    public function organizationOf(User $user): ?string
    {
        if (array_key_exists($user->id, $this->userOrganization)) {
            return $this->userOrganization[$user->id];
        }

        $ids = $this->membershipsOf($user);
        $chosen = $user->current_organization_id;

        return $this->userOrganization[$user->id] = in_array($chosen, $ids, true) ? $chosen : ($ids[0] ?? null);
    }

    /**
     * @return list<string>
     */
    public function membershipsOf(User $user): array
    {
        // Query tabel pivot langsung (bukan relasi) supaya tidak memicu global scope apa pun.
        return $this->memberships[$user->id] ??= DB::table('organization_user')
            ->where('user_id', $user->id)
            ->orderBy('created_at')
            ->orderBy('organization_id')
            ->pluck('organization_id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }

    public function isMember(User $user, ?string $organizationId): bool
    {
        return $organizationId !== null && in_array($organizationId, $this->membershipsOf($user), true);
    }

    /** Subquery id user anggota organisasi yang berlaku (daftar user, audit log, penerima). */
    public function memberIdsQuery(?string $organizationId = null): QueryBuilder
    {
        return DB::table('organization_user')
            ->where('organization_id', $organizationId ?? $this->currentId())
            ->select('user_id');
    }

    /** Dipanggil setelah keanggotaan berubah di request yang sama. */
    public function forget(?User $user = null): void
    {
        if ($user === null) {
            $this->userOrganization = $this->memberships = [];
            $this->primaryId = null;

            return;
        }

        unset($this->userOrganization[$user->id], $this->memberships[$user->id]);
    }
}
