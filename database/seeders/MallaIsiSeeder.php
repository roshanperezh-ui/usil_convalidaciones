<?php

namespace Database\Seeders;

use App\Models\Carrera;
use App\Models\CursoUsil;
use App\Models\MallaCurricular;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Malla curricular real de Ingeniería de Sistemas de Información — Plan 2023-01
 * (Facultad de Ingeniería, USIL). 10 ciclos, cursos obligatorios y 3 líneas de
 * electivos. Créditos y prerrequisitos según el plan oficial.
 *
 * Ejecutar: php artisan db:seed --class=MallaIsiSeeder
 */
class MallaIsiSeeder extends Seeder
{
    public function run(): void
    {
        $isi = Carrera::where('codigo', 'ISI')->first();
        if (! $isi) {
            $this->command->warn('No existe la carrera ISI. Ejecuta primero UsilPregradoSeeder.');
            return;
        }
        $admin = User::where('email', 'admin@usil.edu.pe')->firstOrFail();

        // [ciclo, nombre, créditos, prerrequisito (nombre o null), esElectivo]
        $cursos = [
            // ---- Ciclo 1 ----
            [1, 'Fundamentos de programación', 3, null, false],
            [1, 'Fundamentos en competencias digitales', 3, null, false],
            [1, 'Matemática', 4, null, false],
            [1, 'Lenguaje y comunicación I', 4, null, false],
            [1, 'Realidad nacional y globalización', 3, null, false],
            [1, 'English I', 5, null, false],
            // ---- Ciclo 2 ----
            [2, 'Programación orientada a objetos I', 4, 'Fundamentos de programación', false],
            [2, 'Matemática discreta', 2, 'Matemática', false],
            [2, 'Cálculo de una variable', 4, 'Matemática', false],
            [2, 'Lenguaje y comunicación II', 4, 'Lenguaje y comunicación I', false],
            [2, 'Administración para los negocios', 3, null, false],
            [2, 'English II', 5, 'English I', false],
            // ---- Ciclo 3 ----
            [3, 'Programación y estructuras de datos', 4, 'Fundamentos de programación', false],
            [3, 'Gestión de procesos', 4, null, false],
            [3, 'Álgebra lineal computacional', 4, 'Matemática', false],
            [3, 'Electricidad y ondas', 2, 'Cálculo de una variable', false],
            [3, 'Principios de economía', 3, null, false],
            [3, 'English III', 5, 'English II', false],
            // ---- Ciclo 4 ----
            [4, 'Análisis y diseño de sistemas I', 4, 'Gestión de procesos', false],
            [4, 'Gerenciamiento de datos I', 4, 'Programación y estructuras de datos', false],
            [4, 'Arquitectura de computadoras', 2, 'Electricidad y ondas', false],
            [4, 'Estadística descriptiva e inferencia estadística', 4, 'Matemática', false],
            [4, 'Fundamentos contables y financieros', 3, 'Principios de economía', false],
            [4, 'English IV', 5, 'English III', false],
            // ---- Ciclo 5 ----
            [5, 'Programación orientada a objetos II', 4, 'Programación orientada a objetos I', false],
            [5, 'Gerenciamiento de datos II', 4, 'Gerenciamiento de datos I', false],
            [5, 'Arquitectura empresarial', 2, 'Gestión de procesos', false],
            [5, 'Interacción humano computador', 4, null, false],
            [5, 'Ética y ciudadanía', 3, 'Realidad nacional y globalización', false],
            [5, 'Marketing', 3, 'Administración para los negocios', false],
            // ---- Ciclo 6 ----
            [6, 'Análisis y diseño de sistemas II', 4, 'Análisis y diseño de sistemas I', false],
            [6, 'Gobierno de datos', 2, 'Gerenciamiento de datos II', false],
            [6, 'Gobierno de TI', 2, null, false],
            [6, 'Sistemas operativos', 4, 'Arquitectura empresarial', false],
            [6, 'Metodología de la investigación científica', 4, 'Estadística descriptiva e inferencia estadística', false],
            // ---- Ciclo 7 ----
            [7, 'Desarrollo basado en plataformas', 4, 'Programación orientada a objetos II', false],
            [7, 'Gestión de sistemas de información', 4, 'Gobierno de TI', false],
            [7, 'Gestión del conocimiento', 2, 'Gobierno de datos', false],
            [7, 'Redes y telecomunicaciones I', 4, 'Sistemas operativos', false],
            [7, 'Fundamentos del liderazgo sostenible', 3, 'Ética y ciudadanía', false],
            // ---- Ciclo 8 ----
            [8, 'Agentes inteligentes', 4, 'Programación orientada a objetos II', false],
            [8, 'Seguridad de la información', 4, 'Gobierno de datos', false],
            [8, 'Gestión de proyectos para computación', 2, 'Metodología de la investigación científica', false],
            [8, 'Cloud computing', 4, 'Redes y telecomunicaciones I', false],
            [8, 'Oportunidades de negocios', 3, 'Fundamentos contables y financieros', false],
            // ---- Ciclo 9 ----
            [9, 'Internet of things', 4, 'Arquitectura de computadoras', false],
            [9, 'Visualización de datos', 4, 'Desarrollo basado en plataformas', false],
            [9, 'Proyecto para computación I', 4, 'Gestión de proyectos para computación', false],
            [9, 'Tecnologías emergentes', 2, null, false],
            [9, 'Computación en la sociedad', 2, 'Cloud computing', false],
            // ---- Ciclo 10 ----
            [10, 'Big data y analítica de datos', 4, 'Cloud computing', false],
            [10, 'Estrategias de sistemas de información', 4, null, false],
            [10, 'Proyecto para computación II', 4, 'Proyecto para computación I', false],
            [10, 'Emprendimiento e innovación tecnológica', 2, 'Gestión de proyectos para computación', false],
            [10, 'Desarrollo de negocios electrónicos', 2, 'Tecnologías emergentes', false],

            // ===== Electivos: Analítica de datos no estructurados =====
            [6, 'Procesamiento digital de señales', 4, null, true],
            [7, 'Procesamiento de imágenes digitales', 4, 'Procesamiento digital de señales', true],
            [8, 'Visión computacional', 4, 'Procesamiento de imágenes digitales', true],
            [9, 'Tópicos en procesamiento de lenguaje natural', 4, 'Visión computacional', true],
            [10, 'Tópicos en analítica de datos no estructurados', 4, null, true],
            // ===== Electivos: Tecnologías de Información =====
            [6, 'Robótica I', 4, null, true],
            [7, 'Robótica II', 4, 'Robótica I', true],
            [8, 'Redes y Telecomunicaciones II', 4, 'Redes y telecomunicaciones I', true],
            [9, 'Redes y Telecomunicaciones III', 4, 'Redes y Telecomunicaciones II', true],
            [10, 'Gestión de la ciberseguridad', 4, 'Redes y Telecomunicaciones III', true],
            // ===== Electivos: Gestión Integral de la sostenibilidad =====
            [7, 'Bases para la gestión estratégica de la sostenibilidad', 4, null, true],
            [8, 'Gestión empresarial sostenible', 4, null, true],
            [9, 'Gestión de emprendimientos socioambientales', 4, null, true],
            [10, 'Gestión del sector público y sociedad civil para el desarrollo sostenible', 4, null, true],
        ];

        DB::transaction(function () use ($isi, $admin, $cursos) {
            $malla = MallaCurricular::firstOrNew([
                'carrera_id' => $isi->id, 'anio' => 2023, 'version' => '2023-01',
            ]);
            $malla->fill([
                'modalidad'    => 'presencial',
                'periodo'      => '2023-01',
                'activa'       => true,
                'origen_carga' => 'manual',
                'usuario_id'   => $admin->id,
            ])->save();

            // Idempotencia: limpia el currículo previo de ESTA malla.
            foreach ($malla->ciclos as $ciclo) {
                CursoUsil::withTrashed()->where('ciclo_id', $ciclo->id)->forceDelete();
            }
            $malla->ciclos()->delete();

            // RN-02: solo una malla activa por carrera.
            MallaCurricular::where('carrera_id', $isi->id)->whereKeyNot($malla->id)->update(['activa' => false]);

            // Ciclos 1..10
            $ciclos = [];
            for ($n = 1; $n <= 10; $n++) {
                $ciclos[$n] = $malla->ciclos()->create(['numero' => $n, 'nombre' => "Ciclo {$n}"]);
            }

            // Pase 1: crear cursos
            $porNombre = [];
            $i = 0;
            foreach ($cursos as [$ciclo, $nombre, $creditos, $prereq, $electivo]) {
                $i++;
                $curso = $ciclos[$ciclo]->cursos()->create([
                    'codigo'      => sprintf('ISI%03d', $i),
                    'nombre'      => $nombre,
                    'creditos'    => $creditos,
                    'es_electivo' => $electivo,
                    'tipo_curso'  => 'teorico_practico',
                ]);
                $porNombre[$nombre] = $curso;
            }

            // Pase 2: prerrequisitos por nombre
            foreach ($cursos as [$ciclo, $nombre, $creditos, $prereq, $electivo]) {
                if ($prereq && isset($porNombre[$prereq]) && isset($porNombre[$nombre])) {
                    $porNombre[$nombre]->update(['prerequisito_id' => $porNombre[$prereq]->id]);
                }
            }
        });

        $this->command->info('Malla ISI 2023-01 cargada: 10 ciclos, ' . count($cursos) . ' cursos (con prerrequisitos). Marcada como activa.');
    }
}
