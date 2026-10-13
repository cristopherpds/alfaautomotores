<?php

namespace App\Ai\Tools;

use App\Models\Producto;
use App\Models\Vehiculo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * Buscar en el stock público: autos del catálogo y productos de movilidad y
 * bicicletas. Nunca devuelve borradores ni vendidos (sale de `publicos()` y
 * `deSeccion()`, los mismos que alimentan el sitio).
 *
 * La gente escribe con errores («hailux», «corola»), así que la búsqueda es
 * tolerante: cada palabra se compara exacta, por prefijo, por distancia de
 * edición y por cómo suena.
 */
class BuscarStock extends Herramienta
{
    private const MAX_RESULTADOS = 6;

    public function name(): string
    {
        return 'buscar_stock';
    }

    public function description(): Stringable|string
    {
        return 'Busca en el stock real de Alfa: autos y camionetas, motos, movilidad eléctrica y bicicletas. Devuelve modelo, año, km, precio y el link de la ficha. Usala antes de contestar cualquier pregunta por un vehículo o producto. Con consulta vacía lista lo disponible de ese tipo.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'consulta' => $schema->string()->description('Lo que busca la persona, tal como lo escribió: marca, modelo, año, tipo ("hilux 2018", "una moto", "suv automática").'),
            'tipo' => $schema->string()->enum(['vehiculo', 'movilidad', 'bicicleta', 'todo'])->description('vehiculo = autos y camionetas; movilidad = motos, scooters, monopatines y eléctricos; bicicleta = bicis sin motor.'),
        ];
    }

    public function handle(Request $request): Stringable|string
    {
        $consulta = (string) ($request['consulta'] ?? '');
        $tipo = (string) ($request['tipo'] ?? 'todo');
        $palabras = self::palabras($consulta);

        $candidatos = collect();

        if (in_array($tipo, ['vehiculo', 'todo'], true)) {
            $candidatos = $candidatos->merge(Vehiculo::publicos()->map(fn (Vehiculo $vehiculo): array => [
                'texto' => implode(' ', [$vehiculo->marca, $vehiculo->modelo, $vehiculo->version, $vehiculo->anio, $vehiculo->tipo->value, $vehiculo->comb->value, $vehiculo->trans->value]),
                'resultado' => [
                    'tipo' => 'vehículo',
                    'titulo' => $vehiculo->titulo(),
                    'anio' => $vehiculo->anio,
                    'km' => $vehiculo->km,
                    'precio' => $vehiculo->moneda->value.' '.number_format($vehiculo->precio, 0, ',', '.'),
                    'combustible' => $vehiculo->comb->value,
                    'transmision' => $vehiculo->trans->value,
                    'estado' => $vehiculo->estado->value === 'reservado' ? 'reservado (con seña)' : 'disponible',
                    'link' => route('vehiculos.show', $vehiculo->slug),
                ],
            ]));
        }

        foreach (['movilidad' => false, 'bicicleta' => true] as $seccion => $bicicletas) {
            if (! in_array($tipo, [$seccion, 'todo'], true)) {
                continue;
            }

            $candidatos = $candidatos->merge(Producto::deSeccion($bicicletas)->map(fn (Producto $producto): array => [
                'texto' => implode(' ', [$producto->nombre, $producto->familia->label(), $producto->resumen]),
                'resultado' => [
                    'tipo' => $producto->familia->label(),
                    'titulo' => $producto->nombre,
                    'precio' => $producto->tienePrecio()
                        ? $producto->moneda->value.' '.number_format((int) $producto->precio, 0, ',', '.')
                        : 'a consultar',
                    'estado' => $producto->estado->label(),
                    'link' => route('productos.show', $producto->slug),
                ],
            ]));
        }

        $encontrados = $palabras === []
            ? $candidatos
            : $candidatos
                ->map(fn (array $candidato): array => [...$candidato, 'puntaje' => self::puntaje($palabras, $candidato['texto'])])
                ->filter(fn (array $candidato): bool => $candidato['puntaje'] > 0)
                ->sortByDesc('puntaje');

        return $this->json([
            'consulta' => $consulta,
            'resultados' => $encontrados->take(self::MAX_RESULTADOS)->pluck('resultado')->values()->all(),
            'hay_mas' => $encontrados->count() > self::MAX_RESULTADOS,
            'total_en_stock' => $candidatos->count(),
            'nota' => $encontrados->isEmpty()
                ? 'No hay nada que coincida. Decilo derecho y ofrecé el catálogo o algo parecido; no inventes unidades.'
                : null,
        ]);
    }

    /**
     * Las palabras de una consulta, sin acentos ni relleno.
     *
     * @return list<string>
     */
    public static function palabras(string $texto): array
    {
        $relleno = ['hay', 'algun', 'alguna', 'tienen', 'tenes', 'busco', 'quiero', 'una', 'uno', 'un', 'el', 'la', 'los', 'las', 'de', 'del', 'que', 'con', 'para', 'por', 'auto', 'autos', 'precio', 'cuanto', 'sale', 'esta', 'disponible', 'me', 'interesa', 'y', 'o', 'en'];

        return collect(preg_split('/[^a-z0-9]+/', Str::lower(Str::ascii($texto))) ?: [])
            ->filter(fn (string $palabra): bool => strlen($palabra) >= 2 && ! in_array($palabra, $relleno, true))
            ->values()
            ->all();
    }

    /**
     * Cuántas palabras de la consulta aparecen, con tolerancia a errores.
     *
     * @param  list<string>  $palabras
     */
    public static function puntaje(array $palabras, string $texto): int
    {
        $delCandidato = self::palabras($texto);
        $puntaje = 0;

        foreach ($palabras as $palabra) {
            foreach ($delCandidato as $otra) {
                if (self::parecidas($palabra, $otra)) {
                    $puntaje++;

                    break;
                }
            }
        }

        return $puntaje;
    }

    private static function parecidas(string $a, string $b): bool
    {
        if ($a === $b) {
            return true;
        }

        if (ctype_digit($a) || ctype_digit($b)) {
            return false;
        }

        if (strlen($a) >= 3 && (str_starts_with($b, $a) || str_starts_with($a, $b))) {
            return true;
        }

        $largo = max(strlen($a), strlen($b));
        $tolerancia = $largo >= 7 ? 2 : ($largo >= 4 ? 1 : 0);

        if ($tolerancia > 0 && levenshtein($a, $b) <= $tolerancia) {
            return true;
        }

        // «hailux» y «hilux» suenan igual aunque estén a dos letras.
        return $largo >= 4 && metaphone($a) !== '' && metaphone($a) === metaphone($b);
    }
}
