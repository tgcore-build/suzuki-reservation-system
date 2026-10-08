<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ご予約 - {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    <div class="max-w-lg mx-auto px-4 py-8">
        <h1 class="text-center text-xl font-semibold mb-6">{{ config('app.name') }}　ご予約</h1>
        @yield('content')
    </div>
</body>
</html>
