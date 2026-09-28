<?php

namespace App\Http\Controllers\Panel;

use App\Enums\EstadoProducto;
use App\Enums\FamiliaProducto;
use App\Enums\Moneda;
use App\Http\Controllers\Controller;
use App\Http\Requests\Productos\ProductoStoreRequest;
use App\Http\Requests\Productos\ProductoUpdateRequest;
use App\Models\Producto;
use App\Models\ProductoImagen;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El catálogo de movilidad y el de bicicletas, desde el panel.
 *
 * Un solo ABM para las dos páginas públicas: el sidebar abre el índice con
 * `?seccion=movilidad` o `?seccion=bicicletas` y la frontera es la misma de
 * siempre, `FamiliaProducto::esBicicleta()`. Calcado de `VehiculoController`
 * sin destacados ni acciones en lote.
 */
class ProductoController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int, Middleware>
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:viewAny,'.Producto::class, only: ['index']),
            new Middleware('can:create,'.Producto::class, only: ['create', 'store']),
            new Middleware('can:update,producto', only: ['edit', 'update']),
            new Middleware('can:delete,producto', only: ['destroy']),
        ];
    }

    /**
     * Show every product of one section, drafts included.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();
        $bicicletas = $this->esSeccionBicicletas($request);

        return Inertia::render('panel/productos/index', [
            'seccion' => $bicicletas ? 'bicicletas' : 'movilidad',
            'productos' => Producto::query()
                ->where('familia', $bicicletas ? '=' : '!=', FamiliaProducto::Bicicleta)
                ->with('fotos')
                ->withCount('fotos')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Producto $producto): array => $this->toListItem($producto, $actor))
                ->all(),
            'familias' => array_values(array_filter(
                FamiliaProducto::options(),
                fn (array $opcion): bool => FamiliaProducto::from($opcion['value'])->esBicicleta() === $bicicletas,
            )),
            'estados' => EstadoProducto::options(),
            'puedeCrear' => $actor->can('create', Producto::class),
        ]);
    }

    /**
     * Show the form to add a product.
     *
     * La sección de la que se viene elige la familia inicial: desde
     * Bicicletas el alta arranca como bicicleta.
     */
    public function create(Request $request): Response
    {
        $bicicletas = $this->esSeccionBicicletas($request);

        return Inertia::render('panel/productos/create', [
            'seccion' => $bicicletas ? 'bicicletas' : 'movilidad',
            'familiaInicial' => $bicicletas ? FamiliaProducto::Bicicleta->value : null,
            'opciones' => $this->opciones(),
        ]);
    }

    /**
     * Add a product to the catalogue.
     *
     * Se vuelve a la edición y no al listado: recién ahí se le pueden cargar
     * las fotos, que necesitan que el producto ya exista.
     */
    public function store(ProductoStoreRequest $request): RedirectResponse
    {
        $producto = Producto::create($request->validated());

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Producto creado. Ahora podés cargarle las fotos.'),
        ]);

        return to_route('panel.productos.edit', $producto);
    }

    /**
     * Show the form to edit a product, along with its gallery.
     */
    public function edit(Producto $producto): Response
    {
        $producto->load('fotos');

        return Inertia::render('panel/productos/edit', [
            'seccion' => $this->seccionDe($producto),
            'producto' => $this->toFormItem($producto),
            'opciones' => $this->opciones(),
            'maxImagenes' => Producto::MAX_IMAGENES,
        ]);
    }

    /**
     * Update the given product.
     *
     * Vuelve a la sección de la familia guardada: si se pasó una bicicleta
     * eléctrica a bicicleta, el listado que la muestra es el otro.
     */
    public function update(ProductoUpdateRequest $request, Producto $producto): RedirectResponse
    {
        $producto->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Producto actualizado.')]);

        return to_route('panel.productos.index', ['seccion' => $this->seccionDe($producto)]);
    }

    /**
     * Remove the given product from the catalogue.
     */
    public function destroy(Producto $producto): RedirectResponse
    {
        $seccion = $this->seccionDe($producto);

        $producto->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Producto eliminado.')]);

        return to_route('panel.productos.index', ['seccion' => $seccion]);
    }

    /**
     * Si el pedido es de la sección de bicicletas. Cualquier otro valor, o
     * ninguno, es movilidad.
     */
    private function esSeccionBicicletas(Request $request): bool
    {
        return $request->query('seccion') === 'bicicletas';
    }

    /**
     * La sección del panel —y del sitio público— donde va el producto.
     */
    private function seccionDe(Producto $producto): string
    {
        return $producto->familia->esBicicleta() ? 'bicicletas' : 'movilidad';
    }

    /**
     * Las opciones de los selectores de la ficha.
     *
     * @return array<string, array<int, array<string, string>>>
     */
    private function opciones(): array
    {
        return [
            'familias' => FamiliaProducto::options(),
            'estados' => EstadoProducto::options(),
            'monedas' => Moneda::options(),
        ];
    }

    /**
     * Formatear un producto para la tabla, resolviendo con la policy real lo
     * que el usuario puede hacer con él.
     *
     * @return array{id: int, slug: string, nombre: string, familia: string, codigo: string|null, precio: int|null, moneda: string, estado: string, portada: string|null, imagenes_count: int, can: array{update: bool, delete: bool}}
     */
    private function toListItem(Producto $producto, User $actor): array
    {
        return [
            'id' => $producto->id,
            'slug' => $producto->slug,
            'nombre' => $producto->nombre,
            'familia' => $producto->familia->value,
            'codigo' => $producto->codigo,
            'precio' => $producto->precio,
            'moneda' => $producto->moneda->value,
            'estado' => $producto->estado->value,
            'portada' => $producto->fotos->first()?->url(),
            'imagenes_count' => (int) $producto->fotos_count,
            'can' => [
                'update' => $actor->can('update', $producto),
                'delete' => $actor->can('delete', $producto),
            ],
        ];
    }

    /**
     * Formatear un producto para el formulario de edición y su galería.
     *
     * @return array<string, mixed>
     */
    private function toFormItem(Producto $producto): array
    {
        return [
            'id' => $producto->id,
            'slug' => $producto->slug,
            'nombre' => $producto->nombre,
            'familia' => $producto->familia->value,
            'codigo' => $producto->codigo,
            'precio' => $producto->precio,
            'moneda' => $producto->moneda->value,
            'estado' => $producto->estado->value,
            'resumen' => $producto->resumen,
            'desc' => $producto->desc,
            'specs' => $producto->specs,
            'colores' => $producto->colores,
            'fotos' => $producto->fotos
                ->map(fn (ProductoImagen $foto): array => [
                    'id' => $foto->id,
                    'url' => $foto->url(),
                ])
                ->all(),
        ];
    }
}
