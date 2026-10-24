<?php

namespace App\Support;

use App\Models\Scopes\OrganizationScope;
use Illuminate\Database\Connection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * UI-F1d: PostgreSQL Row Level Security, the second guard under {@see OrganizationScope}.
 *
 * A web/API request switches its connection to the tenant role (`SET ROLE`, no BYPASSRLS) and sets
 * `app.org_id` (+ `app.installation` for the installation organisation). The policies created by
 * migration 2026_10_25_020000 then only return that organisation's rows, even for raw SQL or
 * `withoutGlobalScopes()`. Without an organisation (guest, user without membership) the policies
 * return nothing: fail closed.
 *
 * Console, queue and scheduler never enter the tenant role: they run as the owner and see every OLT.
 * SQLite (local dev, unit tests) and a database without the role are a no-op.
 */
class TenantDatabase
{
    /** Pengaturan sesi PostgreSQL yang dibaca policy. */
    public const ORG_SETTING = 'app.org_id';

    public const INSTALLATION_SETTING = 'app.installation';

    private ?bool $available = null;

    private bool $entered = false;

    private ?string $organizationId = null;

    private bool $installation = false;

    /** Apakah RLS bisa dipakai di koneksi ini (PostgreSQL, diaktifkan, role ada). */
    public function available(): bool
    {
        if ($this->available !== null) {
            return $this->available;
        }

        if (! config('nostek.rls.enabled', true) || $this->connection()->getDriverName() !== 'pgsql') {
            return $this->available = false;
        }

        return $this->available = $this->connection()
            ->table('pg_roles')
            ->where('rolname', static::role())
            ->exists();
    }

    public function entered(): bool
    {
        return $this->entered;
    }

    /** Masuk ke role tenant untuk organisasi ini (null = tidak ada organisasi = tidak ada baris). */
    public function enter(?string $organizationId, bool $installation): void
    {
        if (! $this->available()) {
            return;
        }

        $connection = $this->connection();
        $connection->statement('SET ROLE '.static::quotedRole());
        $this->entered = true;
        $this->set($organizationId, $installation);
    }

    /** Perbarui organisasi saat konteks berubah di tengah request (bot Telegram, status publik). */
    public function set(?string $organizationId, bool $installation): void
    {
        if (! $this->entered) {
            return;
        }

        $this->connection()->select(
            'select set_config(?, ?, false), set_config(?, ?, false)',
            [self::ORG_SETTING, $organizationId ?? '', self::INSTALLATION_SETTING, $installation ? 'on' : 'off'],
        );
        $this->organizationId = $organizationId;
        $this->installation = $installation;
    }

    /**
     * Jalankan satu tulisan sistem untuk organisasi yang sudah ditentukan aplikasi (baris audit login,
     * login gagal, logout: aktornya belum/tidak lagi menjadi konteks request), lalu kembali ke
     * organisasi request. Tetap di role tenant; policy tetap memeriksa baris yang ditulis.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function asOrganization(?string $organizationId, bool $installation, callable $callback): mixed
    {
        if (! $this->entered || ($organizationId === $this->organizationId && $installation === $this->installation)) {
            return $callback();
        }

        [$previous, $previousInstallation] = [$this->organizationId, $this->installation];
        $this->set($organizationId, $installation);

        try {
            return $callback();
        } finally {
            try {
                $this->set($previous, $previousInstallation);
            } catch (QueryException $e) {
                // Transaksi gagal: rollback-nya ikut membatalkan set_config di atas.
                if (! $this->connection()->transactionLevel()) {
                    throw $e;
                }
            }
        }
    }

    /** Kembali ke role pemilik dan kosongkan organisasi (dipanggil di akhir setiap request). */
    public function leave(): void
    {
        if (! $this->entered) {
            return;
        }

        $this->entered = false;
        $this->organizationId = null;
        $this->installation = false;
        $connection = $this->connection();

        try {
            $connection->statement('RESET ROLE');
            $connection->select(
                'select set_config(?, ?, false), set_config(?, ?, false)',
                [self::ORG_SETTING, '', self::INSTALLATION_SETTING, 'off'],
            );
        } catch (QueryException $e) {
            // Transaksi yang sudah gagal menolak perintah apa pun; rollback-nya ikut membatalkan
            // SET ROLE/set_config yang dibuat di dalamnya. Jangan tutupi error aslinya.
            if (! $connection->transactionLevel()) {
                throw $e;
            }
        }
    }

    public static function role(): string
    {
        $role = (string) config('nostek.rls.role', 'nmsolt_tenant');

        if (! preg_match('/^[a-z_][a-z0-9_]{0,62}$/', $role)) {
            throw new InvalidArgumentException('NMSOLT_RLS_ROLE must be a lower-case PostgreSQL identifier.');
        }

        return $role;
    }

    public static function quotedRole(): string
    {
        return '"'.static::role().'"';
    }

    private function connection(): Connection
    {
        return DB::connection();
    }
}
