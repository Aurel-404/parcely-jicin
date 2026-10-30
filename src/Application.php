<?php

declare(strict_types=1);

namespace Parcely;

use Parcely\Controller\HealthController;
use Parcely\Database\Database;
use Parcely\Http\HttpException;
use Parcely\Http\Request;
use Parcely\Http\Response;
use Parcely\Http\Router;

/**
 * Wires routes to controllers and turns every failure into a JSON error response.
 */
final readonly class Application
{
    private Router $router;

    public function __construct(private Config $config, Database $db)
    {
        $health = new HealthController($db);

        $this->router = new Router();
        $this->router->get('/api/health', $health->check(...));
    }

    public function handle(Request $request): Response
    {
        try {
            return $this->router->dispatch($request);
        } catch (HttpException $e) {
            return Response::json(['error' => $e->getMessage()], $e->status, $e->headers);
        } catch (\Throwable $e) {
            error_log((string) $e);
            $message = $this->config->isDebug() ? $e->getMessage() : 'Internal server error';

            return Response::json(['error' => $message], 500);
        }
    }
}
