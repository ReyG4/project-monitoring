<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        $database = getenv('DB_DATABASE') ?: ($_ENV['DB_DATABASE'] ?? '');

        if ($database !== 'project_monitoring_test') {
            throw new RuntimeException(
                'Test dihentikan: DB_DATABASE harus project_monitoring_test, bukan "'.$database.'". Periksa phpunit.xml.'
            );
        }

        parent::setUp();
    }
}