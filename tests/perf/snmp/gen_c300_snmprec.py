#!/usr/bin/env python3
"""NMS-07-E4: synthetic ZTE C300 SNMP agent data (snmpsim .snmprec) for the Go poller bench.

Writes the tables cmd/nos-snmp-poller reads: system group, IF-MIB ifDescr/ifName/ifOperStatus
for the GPON ports, the ZTE ONU tables (.3902.1012.3.28) and the ONU RX table (.3.50.12).
64 ONUs per port, 16 ports per slot. Synthetic values only.

    gen_c300_snmprec.py <onus> <out.snmprec>
"""
import random
import sys


def oid_key(oid):
    return tuple(int(p) for p in oid.split("."))


def main():
    onus, out = int(sys.argv[1]), sys.argv[2]
    rng = random.Random(7)
    rec = {
        "1.3.6.1.2.1.1.1.0": "4|ZXA10 C300 synthetic bench agent",
        "1.3.6.1.2.1.1.2.0": "6|1.3.6.1.4.1.3902.1082.1001.300.1.1",
        "1.3.6.1.2.1.1.3.0": "67|123456",
        "1.3.6.1.2.1.1.5.0": "4|OLT-BENCH",
    }
    ports = (onus + 63) // 64
    for p in range(ports):
        slot, port = 1 + p // 16, 1 + p % 16
        if_index = 268500000 + p  # IF-MIB index; the poller derives slot/port from the name
        name = f"gpon_1/{slot}/{port}"
        rec[f"1.3.6.1.2.1.2.2.1.2.{if_index}"] = f"4|{name}"
        rec[f"1.3.6.1.2.1.2.2.1.8.{if_index}"] = "2|1"
        rec[f"1.3.6.1.2.1.31.1.1.1.1.{if_index}"] = f"4|{name}"
        # TRF-1: ifHCInOctets / ifHCOutOctets (Counter64, snmprec tag 70) for the traffic GETs.
        rec[f"1.3.6.1.2.1.31.1.1.1.6.{if_index}"] = f"70|{rng.getrandbits(40)}"
        rec[f"1.3.6.1.2.1.31.1.1.1.10.{if_index}"] = f"70|{rng.getrandbits(44)}"
    for i in range(onus):
        p, onu_id = i // 64, 1 + i % 64
        slot, port = 1 + p // 16, 1 + p % 16
        prefix = 0x10000000 | (slot << 16) | (port << 8)
        sfx = f"{prefix}.{onu_id}"
        sn = b"ZTEG" + rng.getrandbits(32).to_bytes(4, "big")
        base = "1.3.6.1.4.1.3902.1012.3.28"
        rec[f"{base}.1.1.1.{sfx}"] = "4|F670L"
        rec[f"{base}.1.1.2.{sfx}"] = f"4|Pelanggan Sintetis {i}"
        rec[f"{base}.1.1.3.{sfx}"] = f"4|Jl. Contoh No. {i}"
        rec[f"{base}.1.1.5.{sfx}"] = "4x|" + sn.hex()
        rec[f"{base}.1.1.17.{sfx}"] = "2|1"
        rec[f"{base}.2.1.4.{sfx}"] = "2|" + ("3" if rng.random() > 0.1 else "1")  # 3 Working, 1 LOS
        rec[f"{base}.2.1.7.{sfx}"] = "2|9"
        # dBm = raw * 0.002 - 30  ->  -18 .. -27 dBm
        rec[f"1.3.6.1.4.1.3902.1012.3.50.12.1.1.10.{sfx}.1"] = f"2|{rng.randint(1500, 6000)}"
    with open(out, "w", encoding="ascii") as fh:
        for oid in sorted(rec, key=oid_key):
            fh.write(f"{oid}|{rec[oid]}\n")


if __name__ == "__main__":
    main()
