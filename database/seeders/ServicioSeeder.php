<?php

namespace Database\Seeders;

use App\Enums\AreaServicio;
use App\Enums\Rubro;
use App\Models\Servicio;
use Illuminate\Database\Seeder;

class ServicioSeeder extends Seeder
{
    /**
     * Los trabajos que hace el taller.
     *
     * Salen del material de identidad («Taller Alfa»): nombre, área, duración y
     * la descripción tal como se muestran en la grilla del sitio público.
     *
     * Los que llevan `agendable => false` se cotizan con el vehículo en el
     * taller: aparecen en la grilla, pero invitan a escribir por WhatsApp en
     * vez de reservar.
     *
     * @var list<array{slug: string, nombre: string, area: AreaServicio, duracion: int, descripcion: string, agendable?: bool}>
     */
    private const SERVICIOS = [
        [
            'slug' => 'service-completo',
            'nombre' => 'Service completo',
            'area' => AreaServicio::Mantenimiento,
            'duracion' => 90,
            'descripcion' => 'Aceite, cuatro filtros, bujías y revisión de 30 puntos.',
        ],
        [
            'slug' => 'aceite-y-filtro',
            'nombre' => 'Aceite y filtro',
            'area' => AreaServicio::Mantenimiento,
            'duracion' => 45,
            'descripcion' => 'Cambio de aceite y filtro con el lubricante que pide el fabricante.',
        ],
        [
            'slug' => 'bateria',
            'nombre' => 'Batería',
            'area' => AreaServicio::Mantenimiento,
            'duracion' => 30,
            'descripcion' => 'Prueba de carga y alternador; cambio en el momento si hace falta.',
        ],
        [
            'slug' => 'pastillas-de-freno',
            'nombre' => 'Pastillas de freno',
            'area' => AreaServicio::Frenos,
            'duracion' => 90,
            'descripcion' => 'Cambio de pastillas, limpieza de bujes y purga si corresponde.',
        ],
        [
            'slug' => 'alineacion-y-balanceo',
            'nombre' => 'Alineación y balanceo',
            'area' => AreaServicio::Frenos,
            'duracion' => 60,
            'descripcion' => 'Alineación de tren delantero y balanceo de las cuatro ruedas.',
        ],
        [
            'slug' => 'scanner-obd2',
            'nombre' => 'Diagnóstico con scanner',
            'area' => AreaServicio::Diagnostico,
            'duracion' => 30,
            'descripcion' => 'Lectura de fallas OBD2 con informe escrito y presupuesto.',
        ],
        [
            'slug' => 'pre-inspeccion',
            'nombre' => 'Pre-inspección técnica',
            'area' => AreaServicio::Diagnostico,
            'duracion' => 45,
            'descripcion' => 'Checklist previo a la inspección obligatoria, con informe.',
        ],
        [
            'slug' => 'aire-acondicionado',
            'nombre' => 'Carga de aire acondicionado',
            'area' => AreaServicio::Clima,
            'duracion' => 60,
            'descripcion' => 'Vacío, carga de gas y prueba de estanqueidad del circuito.',
        ],
        [
            'slug' => 'correa-de-distribucion',
            'nombre' => 'Correa de distribución',
            'area' => AreaServicio::Motor,
            'duracion' => 240,
            'descripcion' => 'Kit de distribución completo con bomba de agua incluida.',
            'agendable' => false,
        ],
    ];

    /**
     * Los lavados, por tamaño de vehículo.
     *
     * El trabajo es el mismo en los dos: lo que cambia es el tiempo que lleva,
     * y de ahí sale la diferencia de precio. Van sin área —el lavadero no se
     * clasifica por especialidad— y con precio de lista, que es lo que la
     * página muestra en cada tarjeta.
     *
     * @var list<array{slug: string, nombre: string, duracion: int, precio: int, descripcion: string}>
     */
    private const LAVADOS = [
        [
            'slug' => 'lavado-auto',
            'nombre' => 'Auto',
            'duracion' => 45,
            'precio' => 500,
            'descripcion' => 'Hatchback, sedán y utilitarios chicos. Lavado exterior + aspirado interior.',
        ],
        [
            'slug' => 'lavado-camioneta',
            'nombre' => 'Camioneta',
            'duracion' => 60,
            'precio' => 700,
            'descripcion' => 'SUV, pick-up y camionetas. Lavado exterior + aspirado interior.',
        ],
    ];

    /**
     * Cargar los servicios de los dos rubros.
     *
     * Idempotente por slug: correrlo de nuevo actualiza los textos y no
     * duplica. No pisa `activo` ni la foto, que se editan desde el panel.
     */
    public function run(): void
    {
        foreach (self::SERVICIOS as $orden => $datos) {
            $this->guardar($datos['slug'], [
                'rubro' => Rubro::Taller,
                'nombre' => $datos['nombre'],
                'area' => $datos['area'],
                'duracion' => $datos['duracion'],
                'descripcion' => $datos['descripcion'],
                'agendable' => $datos['agendable'] ?? true,
                'orden' => $orden,
            ]);
        }

        foreach (self::LAVADOS as $orden => $datos) {
            $this->guardar($datos['slug'], [
                'rubro' => Rubro::Lavadero,
                'nombre' => $datos['nombre'],
                'area' => null,
                'duracion' => $datos['duracion'],
                'precio' => $datos['precio'],
                'descripcion' => $datos['descripcion'],
                'agendable' => true,
                'orden' => $orden,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function guardar(string $slug, array $datos): void
    {
        $servicio = Servicio::firstOrNew(['slug' => $slug]);

        $servicio->fill($datos);

        $servicio->save();
    }
}
