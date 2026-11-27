<?php

namespace Tests\Feature;

use Tests\FeatureTestCase;

/**
 * Prueba de humo: la página de inicio carga para un visitante sin sesión.
 * (Antes fallaba con error 500 porque la base de pruebas estaba vacía.)
 */
class ExampleTest extends FeatureTestCase
{
    public function test_la_pagina_de_inicio_responde_correctamente(): void
    {
        // Los estilos y scripts compilados con Vite no existen en el entorno de pruebas.
        $this->withoutVite();

        $this->get('/')->assertOk();
    }
}
