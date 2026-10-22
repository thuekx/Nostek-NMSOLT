<?php

namespace Tests\Concerns;

/**
 * Host part for synthetic OLT IPs. A random 2..250 value collided on the
 * unique (ip, snmp_port) key about once in 249 runs when a test created two
 * OLTs (CI run 39781162265); a sequence cannot collide within one test.
 */
trait SequentialOltHost
{
    protected function nextOltHost(): int
    {
        static $next = 0;

        return 2 + ($next++ % 249);
    }
}
