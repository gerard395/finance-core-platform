<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inloggen – Finance Core</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900 antialiased">
    <main class="flex min-h-screen items-center justify-center px-4 py-10">
        <section class="w-full max-w-md rounded-2xl bg-white p-6 shadow-sm ring-1 ring-slate-200 sm:p-8" aria-labelledby="login-title">
            <p class="text-sm font-semibold uppercase tracking-wider text-blue-700">Finance Core</p>
            <h1 id="login-title" class="mt-2 text-2xl font-semibold">Inloggen</h1>
            <p class="mt-2 text-sm text-slate-600">Gebruik uw account om de financiële omgeving te openen.</p>

            <form method="POST" action="{{ route('login.store') }}" class="mt-8 space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium">E-mailadres</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                        class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/30">
                    @error('email')
                        <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium">Wachtwoord</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password"
                        class="mt-2 block min-h-11 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-600/30">
                    @error('password')
                        <p class="mt-2 text-sm text-red-700" role="alert">{{ $message }}</p>
                    @enderror
                </div>

                <button type="submit" class="min-h-11 w-full rounded-lg bg-blue-700 px-4 py-2.5 font-semibold text-white hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-700 focus:ring-offset-2">
                    Inloggen
                </button>
            </form>
        </section>
    </main>
</body>
</html>
