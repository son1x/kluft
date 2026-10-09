<!doctype html>
<html lang="de">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <meta name="robots" content="noindex, nofollow" />
    <title>Anmelden – {{ config('dpsg.site.short_name', config('dpsg.site.name')) }}</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-dpsg-blau min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-sm bg-white rounded-lg shadow p-6">
        <x-static-image :src="config('dpsg.site.logo')" :alt="config('dpsg.site.name')" class="mb-4 h-16 w-auto" />
        <h1 class="text-lg font-bold text-dpsg-blau mb-1">Anmelden im Redaktionsbereich</h1>

        @if (! $email)
            <p class="text-sm text-gray-600 mb-4">Gib die Adresse deines Zugangs ein – deine eigene oder die eures Funktionspostfachs. Du bekommst einen Code per E-Mail, ein Passwort gibt es nicht.</p>
            <form method="POST" action="{{ route('auth.code.send') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium" for="email">E-Mail-Adresse</label>
                    <input class="mt-1 w-full rounded border-gray-300" type="email" name="email" id="email" autocomplete="email" required autofocus value="{{ old('email') }}">
                    @error('email')<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full rounded bg-dpsg-blau text-white py-2 font-medium">Code schicken</button>
            </form>
        @else
            <p class="text-sm text-gray-600 mb-4">Wenn es für <strong>{{ $email }}</strong> einen Zugang gibt, ist jetzt ein sechsstelliger Code unterwegs. Er gilt {{ $minutes }} Minuten.</p>
            <form method="POST" action="{{ route('auth.code.verify') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-sm font-medium" for="code">Code aus der E-Mail</label>
                    <input class="mt-1 w-full rounded border-gray-300 text-lg tracking-[0.3em]" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]*" maxlength="7" name="code" id="code" required autofocus>
                    @error('code')<p class="mt-1 text-sm text-dpsg-rot">{{ $message }}</p>@enderror
                </div>
                <button type="submit" class="w-full rounded bg-dpsg-blau text-white py-2 font-medium">Anmelden</button>
            </form>
            <p class="mt-3 text-sm"><a class="underline" href="{{ route('auth.code', ['neu' => 1]) }}">Andere Adresse oder neuen Code anfordern</a></p>
        @endif

        @if ($entra)
            <div class="mt-5 border-t border-gray-200 pt-4">
                <a href="{{ route('auth.entra.redirect') }}" class="block w-full rounded border border-dpsg-blau py-2 text-center font-medium text-dpsg-blau">Mit Microsoft 365 anmelden</a>
            </div>
        @endif

        <div class="mt-4 border-t border-gray-200">
            @include('kluft.fusszeile')
        </div>
    </div>
</body>
</html>
