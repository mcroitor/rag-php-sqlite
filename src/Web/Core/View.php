<?php

declare(strict_types=1);

namespace App\Web\Core;

use App\Web\Config;
use Mc\Template;

class View
{
    private Template $template;

    private function __construct(Template $template)
    {
        $this->template = $template;
    }

    public static function load(string $templateName): View
    {
        $filename = str_replace('.', DIRECTORY_SEPARATOR, $templateName);
        $path = Config::viewsDir() . $filename . '.html';
        $template = Template::load($path, Template::BR);
        return new View($template);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function render(array $data = []): string
    {
        return $this->template->fill($data)->value();
    }
}
