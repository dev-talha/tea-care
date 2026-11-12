<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;

class HomeController
{
    public function index(): void
    {
        $user = Auth::user();
        if (!$user) {
            Response::redirect('/login');
        }

        match ($user['role']) {
            'admin' => Response::redirect('/admin'),
            'staff' => Response::redirect('/staff'),
            default => Response::redirect('/today'),
        };
    }

    public function offline(): void
    {
        Response::html(View::render('partials/offline', [], 'auth'));
    }
}
