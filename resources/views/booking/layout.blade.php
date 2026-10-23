<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'ご予約') - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    <div class="max-w-3xl mx-auto px-4 py-8">
        <h1 class="text-center text-xl font-semibold mb-6">{{ config('app.name') }}　@yield('title', 'ご予約')</h1>
        @yield('content')
        <footer class="mt-10 text-center text-xs text-gray-500">
            <a href="{{ route('privacy') }}" class="underline">プライバシーポリシー</a>
        </footer>
    </div>
</body>
</html>
