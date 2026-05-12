<?php

namespace Illuminate\Tests\Integration\Generators;

class ServiceMakeCommandTest extends TestCase
{
    protected $files = [
        'app/Services/FooService.php',
        'app/Services/Buzz/BingService.php',
        'app/Services/SingletonService.php',
    ];

    public function testItCanGenerateServiceFile()
    {
        $this->artisan('make:service', ['name' => 'FooService'])
            ->assertExitCode(0);

        $this->assertFileContains([
            'namespace App\Services;',
            'class FooService',
            'public function __construct(',
        ], 'app/Services/FooService.php');
    }

    public function testItCanGenerateNamspacedServiceFile()
    {
        $this->artisan('make:service', ['name' => 'Buzz/BingService'])
            ->assertExitCode(0);

        $this->assertFileContains([
            'namespace App\Services\Buzz;',
            'class BingService',
            'public function __construct(',
        ], 'app/Services/Buzz/BingService.php');
    }

    public function testItCanGenerateSingletonServiceFile()
    {
        $this->artisan('make:service', ['name' => 'SingletonService', '--singleton' => true])
            ->assertExitCode(0);

        $this->assertFileContains([
            'namespace App\Services;',
            'use Illuminate\Container\Attributes\Singleton;',
            '#[Singleton]',
            'class SingletonService',
            'public function __construct(',
        ], 'app/Services/SingletonService.php');
    }
}
