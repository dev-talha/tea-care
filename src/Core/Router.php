<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    private array $routes = [];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET']['/' . trim($path, '/')] = $handler;
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST']['/' . trim($path, '/')] = $handler;
    }

    public function dispatch(): void
    {
        $method = Request::method();
        $uri = Request::uri();
        if ($uri === '') {
            $uri = '/';
        }

        if ($method === 'POST') {
            $csrf = Request::post('_csrf');
            if ($csrf === null && Request::isAjax()) {
                $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
                if ($csrf === null) {
                    $json = Request::jsonBody();
                    $csrf = $json['_csrf'] ?? null;
                }
            }
            if (!Session::verifyCsrf(is_string($csrf) ? $csrf : null)) {
                if (Request::isAjax()) {
                    Response::json(['ok' => false, 'error' => __('csrf_invalid')], 419);
                }
                Session::flash('error', __('csrf_invalid'));
                Response::redirect($_SERVER['HTTP_REFERER'] ?? '/');
            }
        }

        $handler = $this->routes[$method][$uri] ?? null;
        if ($handler === null) {
            http_response_code(404);
            echo View::render('partials/not_found');
            return;
        }

        [$class, $action] = $handler;
        $controller = new $class();
        $controller->$action();
    }
}
