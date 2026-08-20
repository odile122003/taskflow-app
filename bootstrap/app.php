<?php

use App\Http\Middleware\EnsureProjectIsNotArchived;
use App\Http\Middleware\LogRequestDuration;
use App\Http\Middleware\SetCurrentTeam;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'project.active' => EnsureProjectIsNotArchived::class,
            'team.current' => SetCurrentTeam::class,
        ]);

        $middleware->append(LogRequestDuration::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Format homogène pour /api/* uniquement : les pages web gardent le
        // rendu par défaut (page de debug complète en local, page HTML en
        // prod). Sans ça, une exception non prévue renverrait la trace de
        // débogage complète (chemins de fichiers, stack trace) au client API
        // dès que APP_DEBUG=true — repéré en testant une 404 réelle.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }

            $status = match (true) {
                $e instanceof AuthenticationException => 401,
                $e instanceof AuthorizationException => 403,
                $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => 404,
                $e instanceof ValidationException => 422,
                $e instanceof HttpExceptionInterface => $e->getStatusCode(),
                default => 500,
            };

            // En dessous de 500 : le message vient d'une Policy, d'une règle
            // de validation ou du framework — déjà pensé pour être lu par le
            // client. À 500 : jamais le message réel hors debug (pourrait
            // exposer un détail interne, ex. une requête SQL).
            $message = $status < 500 || config('app.debug')
                ? ($e->getMessage() ?: 'Une erreur est survenue.')
                : 'Une erreur interne est survenue.';

            $payload = ['message' => $message];

            if ($e instanceof ValidationException) {
                $payload['errors'] = $e->errors();
            }

            return response()->json($payload, $status);
        });
    })->create();
