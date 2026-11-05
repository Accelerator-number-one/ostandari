<?php

namespace Tests;

abstract class TestCase extends \Illuminate\Foundation\Testing\TestCase
{
    protected bool $seedAdministratorFixture = true;

    public function seed($class = 'Database\\Seeders\\DatabaseSeeder')
    {
        if ($this->seedAdministratorFixture && $class === \Database\Seeders\DatabaseSeeder::class) {
            $class = \Tests\Support\PortalTestSeeder::class;
        }

        return parent::seed($class);
    }

    public function createApplication()
    {
        $app = parent::createApplication();
        $connection = $app['db']->connection();
        if ($connection->getDriverName() !== 'mysql' || $connection->getDatabaseName() !== 'ostandari_testing') {
            throw new \LogicException('Tests require the dedicated MySQL database ostandari_testing. Run php artisan config:clear and configure .env.testing.');
        }

        return $app;
    }
}
