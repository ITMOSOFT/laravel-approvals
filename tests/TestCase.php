<?php

namespace ItmoSoft\Approvals\Tests;

use ItmoSoft\Approvals\ApprovalsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [ApprovalsServiceProvider::class];
    }
}
