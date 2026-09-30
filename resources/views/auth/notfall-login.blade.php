<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Notfall-Login</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-dpsg-blau min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-sm bg-white rounded-lg shadow p-6">
        <h1 class="text-lg font-bold text-dpsg-blau mb-1">Notfall-Login</h1>
        <p class="text-sm text-gray-600 mb-4">
            Nur verwenden, wenn die Anmeldung über Microsoft 365 nicht möglich ist.
            Jede Anmeldung hier löst eine Alarmmail aus.
        </p>

        @if ($errors->any())
            <div class="mb-4 text-sm text-dpsg-rot">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST" action="{{ route('notfall-login.attempt') }}" class="space-y-3">
            @csrf
            <div>
                <label class="block text-sm font-medium" for="email">E-Mail</label>
                <input class="mt-1 w-full rounded border-gray-300" type="email" name="email" id="email" required autofocus>
            </div>
            <div>
                <label class="block text-sm font-medium" for="password">Passwort</label>
                <input class="mt-1 w-full rounded border-gray-300" type="password" name="password" id="password" required>
            </div>
            <div>
                <label class="block text-sm font-medium" for="totp">TOTP-Code (Authenticator-App)</label>
                <input class="mt-1 w-full rounded border-gray-300" type="text" inputmode="numeric" pattern="[0-9]*" name="totp" id="totp" required>
            </div>
            <button type="submit" class="w-full rounded bg-dpsg-rot text-white py-2 font-medium">
                Anmelden
            </button>
        </form>
    </div>
</body>
</html>
