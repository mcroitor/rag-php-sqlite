<?php

namespace App\Web;

use App\Engine\Storage\MetaStorage;
use App\Engine\Utils\DbFactory;
use App\Web\Controllers\ApiController;
use App\Web\Controllers\PageController;
use App\Web\Core\Container;
use App\Web\Services\ChatService;
use App\Web\Services\DocumentService;
use App\Web\Services\JobManager;
use App\Web\Services\SearchService;
use App\Web\Services\StatsService;
use Mc\Router;

/**
 * Web application entry. Bootstraps the RAG engine services and
 * wires HTTP routes to them.
 */
class Application
{
    private Container $container;
    private MetaStorage $meta;

    public function __construct(string $root)
    {
        $this->meta = new MetaStorage($root);
        $this->container = new Container($root, $this->meta->getActiveBase());
    }

    public function container(): Container
    {
        return $this->container;
    }

    public function setBase(string $base): void
    {
        $base = DbFactory::normalize($base);
        $this->meta->setActiveBase($base);
        $this->container->setBase($base);
    }

    public function run(): string
    {
        Router::init();

        $api = new ApiController(
            new SearchService($this->container->queryService()),
            new ChatService($this->container->ragService()),
            $this->container->statsService(),
            new JobManager($this->container->root(), $this->meta),
            fn(): string => $this->container->base(),
            $this->meta,
            new DocumentService($this->container->storage()),
        );
        $pages = new PageController(fn(): string => $this->container->base());

        Router::get('/api/health', fn(): string => $api->health());
        Router::get('/api/search', fn(): string => $api->search());
        Router::post('/api/chat', fn(): string => $api->chat());
        Router::get('/api/stats', fn(): string => $api->stats());
        Router::post('/api/index', fn(): string => $api->index());
        Router::get('/api/jobs', fn(): string => $api->jobs());
        Router::get('/api/jobs/{id}', function () use ($api): string {
            $params = Router::getPathParams();
            return $api->job((string) ($params['id'] ?? ''));
        });
        Router::get('/api/bases', fn(): string => $api->bases());
        Router::post('/api/bases', fn(): string => $api->switchBase());
        Router::post('/api/bases/create', fn(): string => $api->createBase());
        Router::get('/api/documents', fn(): string => $api->documents());
        Router::delete('/api/documents/{id}', function () use ($api): string {
            $params = Router::getPathParams();
            return $api->removeDocument((string) ($params['id'] ?? ''));
        });
        Router::get('/api/documents/{id}/download', function () use ($api): string {
            $params = Router::getPathParams();
            return $api->downloadDocument((string) ($params['id'] ?? ''));
        });

        Router::get('/', fn(): string => $pages->search());
        Router::get('/search', fn(): string => $pages->search());
        Router::get('/chat', fn(): string => $pages->chat());
        Router::get('/stats', fn(): string => $pages->stats());
        Router::get('/index', fn(): string => $pages->index());
        Router::get('/documents', fn(): string => $pages->documents());

        return Router::run();
    }
}
