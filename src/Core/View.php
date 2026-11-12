<?php

declare(strict_types=1);

namespace App\Core;

class View
{
    public static function render(string $template, array $data = [], ?string $layout = null): string
    {
        $viewPath = Config::basePath('src/Views/' . str_replace('.', '/', $template) . '.php');
        if (!is_file($viewPath)) {
            throw new \RuntimeException("View not found: {$template}");
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewPath;
        $content = ob_get_clean();

        if ($layout === null) {
            return (string)$content;
        }

        $layoutPath = Config::basePath('src/Views/layouts/' . $layout . '.php');
        if (!is_file($layoutPath)) {
            throw new \RuntimeException("Layout not found: {$layout}");
        }

        ob_start();
        require $layoutPath;
        return (string)ob_get_clean();
    }
}
