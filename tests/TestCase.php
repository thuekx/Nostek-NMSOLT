<?php

namespace Tests;

use App\Support\DeviceWriteGate;
use App\Support\DeviceWritePolicy;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Static caches outlive one test in the same PHP process.
        DeviceWritePolicy::flush();
        DeviceWriteGate::flush();
    }
}
