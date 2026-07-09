<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>Convalidaciones USIL</title>
    @vite(['resources/js/app.js'])
    @inertiaHead
</head>
<body class="h-full bg-slate-50 text-slate-800 antialiased">
    @inertia
</body>
</html>
