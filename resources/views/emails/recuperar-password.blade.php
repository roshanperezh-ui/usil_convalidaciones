<x-mail::message>
# Recupera tu contraseña

Hola **{{ $user->nombre }}**, recibimos una solicitud para restablecer tu contraseña en **USIL Convalidaciones**.

Haz clic en el siguiente botón para crear una nueva contraseña:

<x-mail::button :url="$url" color="primary">
Restablecer contraseña
</x-mail::button>

Por seguridad, este enlace caduca en **60 minutos**. Si no solicitaste este cambio, ignora este correo: tu contraseña seguirá siendo la misma.

Gracias,<br>
**USIL Convalidaciones**
</x-mail::message>
