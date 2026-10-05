<?php

namespace Database\Seeders;

use App\Enums\TipoFondo;
use App\Models\HeroSlide;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

class HeroSlideSeeder extends Seeder
{
    /**
     * Cargar como primer slide el hero que tenía la portada antes de ser
     * administrable: el video de la ruta y sus textos.
     *
     * Es idempotente: el video se copia una sola vez y el slide se busca por
     * su ruta.
     */
    public function run(): void
    {
        $ruta = HeroSlide::CARPETA.'/hero-ruta.mp4';
        $origen = public_path('assets/hero-ruta.mp4');

        if (! Storage::disk('public')->exists($ruta) && is_file($origen)) {
            Storage::disk('public')->put($ruta, (string) file_get_contents($origen));
        }

        $porDefecto = HeroSlide::porDefecto();

        HeroSlide::query()->firstOrCreate(['fondo' => $ruta], [
            'tipo_fondo' => TipoFondo::Video,
            'eyebrow' => $porDefecto['eyebrow'],
            'titulo' => $porDefecto['titulo'],
            'bajada' => $porDefecto['bajada'],
            'boton1_texto' => 'Ver catálogo',
            'boton1_url' => '/catalogo',
            'activo' => true,
            'orden' => 0,
        ]);
    }
}
