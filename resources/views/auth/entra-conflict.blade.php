<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Anmeldung nicht möglich</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-dpsg-blau min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md bg-white rounded-lg shadow p-6">
        <h1 class="text-lg font-bold text-dpsg-blau mb-2">Anmeldung nicht möglich</h1>
        <p class="text-sm">Die Adresse <strong>{{ $email }}</strong> ist dem Notfall-Account vorbehalten. Der Notfall-Zugang ist bewusst vom Microsoft-365-Login getrennt und kann nicht mit einem M365-Konto verknüpft werden.</p>
        <p class="mt-3 text-sm">Lösung: Dem Notfall-Account per SSH eine eigene Adresse geben, z.&nbsp;B. <code>notfall@{{ config('dpsg.site.domain') }}</code>:</p>
        <pre class="mt-2 overflow-x-auto rounded bg-gray-100 p-2 text-xs">php artisan cms:create-superadmin --reset</pre>
        <p class="mt-3 text-sm">Danach funktioniert die Anmeldung mit Microsoft 365 wieder.</p>
    </div>
</body>
</html>
