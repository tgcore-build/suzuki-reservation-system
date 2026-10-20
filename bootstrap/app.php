<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render などのプロキシ経由でも、HTTPS や接続元IPを正しく判定する
        // 信頼するのは、プライベートIP（Render の内部のプロキシ）からの接続だけ。'*' にすると、
        // お客様が X-Forwarded-For ヘッダーに好きなIPを書いて、IPごとの回数制限をすり抜けられてしまう
        $middleware->trustProxies(at: array_filter(array_map('trim', explode(',', (string) env(
            'TRUSTED_PROXIES',
            '10.0.0.0/8,172.16.0.0/12,192.168.0.0/16,127.0.0.1',
        )))));
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
