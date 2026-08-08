<?php

declare(strict_types=1);

namespace App\Web\Controllers;

use App\Web\Core\View;

/**
 * Server-rendered HTML pages (Skeleton CSS layout).
 */
class PageController
{
    /** @var callable(): string */
    private $baseResolver;

    /**
     * @param callable(): string $baseResolver
     */
    public function __construct(callable $baseResolver)
    {
        $this->baseResolver = $baseResolver;
    }

    public function search(): string
    {
        return View::load('default')->render($this->layoutData('Search', $this->searchBody(), 'search.js'));
    }

    public function chat(): string
    {
        return View::load('default')->render($this->layoutData('Chat', $this->chatBody(), 'chat.js'));
    }

    public function stats(): string
    {
        return View::load('default')->render($this->layoutData('Stats', $this->statsBody(), 'stats.js'));
    }

    public function index(): string
    {
        return View::load('default')->render($this->layoutData('Index', $this->indexBody(), 'index.js'));
    }

    public function documents(): string
    {
        return View::load('default')->render($this->layoutData('Documents', $this->documentsBody(), 'documents.js'));
    }

    private function documentsBody(): string
    {
        return View::load('widgets.documents')->render([]);
    }

    private function indexBody(): string
    {
        return View::load('widgets.index')->render([]);
    }

    private function chatBody(): string
    {
        return View::load('widgets.chat')->render([]);
    }

    private function searchBody(): string
    {
        return View::load('widgets.search')->render([]);
    }

    private function statsBody(): string
    {
        return View::load('widgets.stats')->render([]);
    }

    /**
     * @return array<string, string>
     */
    private function layoutData(string $title, string $content, string $script): array
    {
        return [
            'LANG' => 'en',
            'PAGE_TITLE' => $title . ' - RAG-PHP-SQLite',
            'NAVBAR_HTML' => $this->navbar(),
            'ERROR_HTML' => '',
            'PAGE_CONTENT' => $content,
            'PAGE_FOOTER' => '',
            'PAGE_SCRIPTS' => '<script src="/assets/js/' . $script . '"></script>',
        ];
    }

    private function navbar(): string
    {
        return View::load('widgets.navbar')->render([
            'BASE_SELECTOR' => $this->baseSelector(),
        ]);
    }

    private function baseSelector(): string
    {
        $active = htmlspecialchars(($this->baseResolver)(), ENT_QUOTES);

        return '<label class="base-selector" title="Active RAG database">DB'
            . '<select id="base-select" data-active="' . $active . '"></select></label>';
    }
}
