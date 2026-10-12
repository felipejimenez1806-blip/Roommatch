import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import { existsSync, readdirSync } from 'node:fs';

/**
 * Lista los archivos de una carpeta con una extensión dada (sin recursión).
 * Si la carpeta no existe devuelve [].
 */
const listar = (dir, ext) =>
    existsSync(dir)
        ? readdirSync(dir, { withFileTypes: true })
              .filter((f) => f.isFile() && f.name.endsWith(ext))
              .map((f) => `${dir}/${f.name}`)
        : [];

// Tailwind queda instalado y configurado, pero su CSS no se compila hasta que
// se use de verdad. Para activarlo: quitar 'resources/css/app.css' de esta lista
// y cargarlo con @vite('resources/css/app.css') donde se necesite.
const SIN_COMPILAR = ['resources/css/app.css'];

// Cada archivo de estas carpetas es un punto de entrada. Una página nueva
// = un archivo nuevo en pages/, sin tocar esta configuración.
//   resources/css/*.css        -> estilos globales (layout)
//   resources/css/pages/*.css  -> estilos por página
//   resources/js/*.js          -> scripts globales (layout)
//   resources/js/pages/*.js    -> scripts por página
const entradas = [
    ...listar('resources/css', '.css'),
    ...listar('resources/css/pages', '.css'),
    ...listar('resources/js', '.js'),
    ...listar('resources/js/pages', '.js'),
].filter((archivo) => !SIN_COMPILAR.includes(archivo));

export default defineConfig(({ command }) => ({
    // Base relativa SOLO en el build: las rutas internas (imágenes y fuentes
    // dentro del CSS, imports entre chunks) se resuelven respecto al archivo
    // que las pide, no desde la raíz del dominio. Así funciona igual en un
    // subdirectorio (/roommatch-laravel/public) que en la raíz (Azure).
    // En desarrollo (npm run dev) se deja el valor por defecto del plugin.
    ...(command === 'build' ? { base: './' } : {}),
    plugins: [
        laravel({
            input: entradas,
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
}));
