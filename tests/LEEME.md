# Guía de pruebas de RoomMatch (Laravel)

Requisito previo (una sola vez): crear la base de pruebas en MySQL.

    CREATE DATABASE roommatch_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

Ejecutar todas las pruebas:

    php artisan test

Solo una historia:

    php artisan test --filter=HU01RegistroTest

Solo las unitarias:

    php artisan test --testsuite=Unit

Las pruebas usan la base `roommatch_testing` (nunca la de desarrollo). Cada vez que se
ejecutan se crean las tablas con las migraciones reales y cada prueba deja la base limpia.

| Archivo | Historia | Casos |
|---|---|---|
| Unit/TelefonoTest.php | Validador de teléfonos | formato +57 y celulares inválidos |
| Feature/HU01RegistroTest.php | HU-01 Registro | CP-01 a CP-06 |
| Feature/HU02InicioSesionTest.php | HU-02 Inicio de sesión | CP-07 a CP-16 |
| Feature/HU04PublicacionesTest.php | HU-04 Publicaciones | CP-17 a CP-25 |
| Feature/HU09ReservasTest.php | HU-09 Reservas | CP-26 a CP-34 |
| Feature/ExampleTest.php | Página de inicio | prueba de humo |
