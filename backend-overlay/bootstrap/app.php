<?php

use App\Workflow\WorkflowException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        apiPrefix: 'api/v1',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias(['api.token' => \App\Http\Middleware\ApiToken::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') || $r->expectsJson());
        // One JSON error format for the whole API: {"error":{"code","message","details"}}
        $exceptions->render(function (\Throwable $e, Request $r) {
            if (! $r->is('api/*')) return null;
            if ($e instanceof ValidationException) return response()->json(['error' => ['code' => 'validation', 'message' => 'তথ্যে ত্রুটি আছে', 'details' => $e->errors()]], 422);
            if ($e instanceof WorkflowException) return response()->json(['error' => ['code' => 'workflow', 'message' => $e->getMessage(), 'details' => $e->details]], 422);
            if ($e instanceof ModelNotFoundException) return response()->json(['error' => ['code' => 'not_found', 'message' => 'রেকর্ড পাওয়া যায়নি']], 404);
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            return response()->json(['error' => ['code' => 'error', 'message' => ($status >= 500 && ! config('app.debug')) ? 'সার্ভারে সমস্যা হয়েছে' : $e->getMessage()]], $status);
        });
    })->create();
