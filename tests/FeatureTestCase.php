<?php

namespace Tests;

use Database\Seeders\RolSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

/**
 * Base de las pruebas de funcionalidad: base de datos limpia (migraciones
 * reales), roles sembrados y disco 'public' simulado (no se guardan fotos de verdad).
 */
abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolSeeder::class);
        Storage::fake('public');
    }
}
