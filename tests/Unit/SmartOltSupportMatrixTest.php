<?php

namespace Tests\Unit;

use App\Models\SnmpOlt;
use App\Support\SmartOltSupport;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SmartOltSupportMatrixTest extends TestCase
{
    #[Test]
    public function known_inventory_families_route_to_the_reviewed_driver(): void
    {
        $cases = [
            ['ZTE C320', SmartOltSupport::DRIVER_ZTE],
            ['ZTE C600', SmartOltSupport::DRIVER_ZTE],
            ['C-Data FD1108S EPON', SmartOltSupport::DRIVER_CDATA_EPON],
            ['C-Data FD1604S GPON', SmartOltSupport::DRIVER_CDATA_GPON],
            ['HiOSO HA7304', SmartOltSupport::DRIVER_HIOSO_EPON],
        ];

        foreach ($cases as [$vendor, $expected]) {
            $olt = new SnmpOlt(['name' => "LAB {$vendor}", 'vendor' => $vendor]);

            $this->assertSame($expected, SmartOltSupport::driverKey($olt), $vendor);
        }
    }

    #[Test]
    public function unsupported_vendor_families_fail_closed(): void
    {
        foreach (['V-SOL V1600D', 'Unreviewed OLT'] as $vendor) {
            $olt = new SnmpOlt(['name' => "LAB {$vendor}", 'vendor' => $vendor]);
            $driver = SmartOltSupport::driverKey($olt);
            $capabilities = SmartOltSupport::capabilities($driver, $olt);

            $this->assertSame(SmartOltSupport::DRIVER_UNKNOWN, $driver, $vendor);
            $this->assertFalse($capabilities['supports_snmp_rx'], $vendor);
            $this->assertFalse($capabilities['supports_provisioning'], $vendor);
            $this->assertFalse($capabilities['supports_reboot'], $vendor);
            $this->assertFalse($capabilities['supports_onu_toggle'], $vendor);
            $this->assertFalse($capabilities['supports_config_save'], $vendor);
        }
    }
}
