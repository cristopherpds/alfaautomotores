<?php

namespace App\Http\Controllers\Panel;

use App\Enums\TipoFondo;
use App\Http\Controllers\Controller;
use App\Http\Requests\HeroSlides\HeroSlideRequest;
use App\Models\HeroSlide;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Los slides del hero de la portada.
 *
 * Qué se ve en el sitio lo decide `HeroSlide::vigentes()`; acá se cargan, se
 * programan y se apagan. Sin ninguno vigente, la portada cae en
 * `HeroSlide::porDefecto()`.
 */
class HeroSlideController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.HeroSlide::class, only: ['index']),
            new Middleware('can:create,'.HeroSlide::class, only: ['create', 'store']),
            new Middleware('can:update,slide', only: ['edit', 'update']),
            new Middleware('can:delete,slide', only: ['destroy']),
        ];
    }

    /**
     * Show the home page slides.
     */
    public function index(Request $request): Response
    {
        /** @var User $actor */
        $actor = $request->user();

        return Inertia::render('panel/hero/index', [
            'slides' => HeroSlide::ordenados()
                ->get()
                ->map(fn (HeroSlide $slide): array => $this->toListItem($slide))
                ->all(),
            'puedeGestionar' => $actor->can('create', HeroSlide::class),
        ]);
    }

    /**
     * Show the form to add a slide.
     */
    public function create(): Response
    {
        return Inertia::render('panel/hero/create', [
            'tiposFondo' => TipoFondo::options(),
        ]);
    }

    /**
     * Store a new slide.
     */
    public function store(HeroSlideRequest $request): RedirectResponse
    {
        $slide = new HeroSlide($request->safe()->except('fondo'));
        $slide->fondo = $this->guardarFondo($request);
        $slide->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Slide creado.')]);

        return to_route('panel.hero.index');
    }

    /**
     * Show the form to edit a slide.
     */
    public function edit(HeroSlide $slide): Response
    {
        return Inertia::render('panel/hero/edit', [
            'slide' => $this->toListItem($slide),
            'tiposFondo' => TipoFondo::options(),
        ]);
    }

    /**
     * Update a slide. The background is replaced only if a new one comes in.
     */
    public function update(HeroSlideRequest $request, HeroSlide $slide): RedirectResponse
    {
        $slide->fill($request->safe()->except('fondo'));

        if ($request->hasFile('fondo')) {
            /* El fondo viejo se va con el nuevo: si no, queda huérfano en el
               disco sin que nada lo nombre. */
            Storage::disk('public')->delete($slide->fondo);
            $slide->fondo = $this->guardarFondo($request);
        }

        $slide->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Slide actualizado.')]);

        return to_route('panel.hero.index');
    }

    /**
     * Remove a slide, background file included.
     */
    public function destroy(HeroSlide $slide): RedirectResponse
    {
        $slide->borrarConArchivo();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Slide eliminado.')]);

        return to_route('panel.hero.index');
    }

    /**
     * Guardar el fondo en el disco y devolver su ruta.
     *
     * `store()` devuelve `false` si el disco falla: quedarse callado dejaría un
     * slide apuntando a la nada.
     */
    private function guardarFondo(HeroSlideRequest $request): string
    {
        $ruta = $request->file('fondo')->store(HeroSlide::CARPETA, 'public');

        if ($ruta === false) {
            throw new RuntimeException('No se pudo guardar el fondo del slide.');
        }

        return $ruta;
    }

    /**
     * La fila que consume la tabla del panel, y la base del formulario de
     * edición.
     *
     * @return array<string, mixed>
     */
    private function toListItem(HeroSlide $slide): array
    {
        return [
            ...$slide->datos(),
            'boton1_texto' => $slide->boton1_texto,
            'boton1_url' => $slide->boton1_url,
            'boton2_texto' => $slide->boton2_texto,
            'boton2_url' => $slide->boton2_url,
            'activo' => $slide->activo,
            'desde' => $slide->desde?->toDateString(),
            'hasta' => $slide->hasta?->toDateString(),
            'orden' => $slide->orden,
            'estado' => $slide->estado(),
        ];
    }
}
