<?php
/*
 |--------------------------------------------------------------------------
 | Fragmento para config/auth.php
 |--------------------------------------------------------------------------
 | Reemplazar el provider 'users' por el modelo y tabla del proyecto.
 */
return [
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model'  => App\Models\User::class, // mapeado a la tabla 'usuarios'
        ],
    ],
];
