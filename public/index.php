<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/Core/helpers.php';

use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\EmployeeController;
use App\Controllers\HomeController;
use App\Controllers\MediaController;
use App\Controllers\PushController;
use App\Controllers\StaffController;
use App\Core\Auth;
use App\Core\Config;
use App\Core\Lang;
use App\Core\Router;
use App\Core\Session;

Config::load(dirname(__DIR__));
Session::start();

$currentUser = Auth::user();
Lang::init('en');

$router = new Router();

// Public & Auth
$router->get('/', [HomeController::class, 'index']);
$router->get('/offline', [HomeController::class, 'offline']);
$router->get('/login', [AuthController::class, 'showLogin']);
$router->post('/login', [AuthController::class, 'login']);
$router->get('/register', [AuthController::class, 'showRegister']);
$router->post('/register', [AuthController::class, 'register']);
$router->post('/logout', [AuthController::class, 'logout']);

// Media
$router->get('/media/cup', [MediaController::class, 'cupPhoto']);
$router->get('/media/logo', [MediaController::class, 'officeLogo']);

// Employee
$router->get('/today', [EmployeeController::class, 'dashboard']);
$router->get('/today/poll', [EmployeeController::class, 'poll']);
$router->post('/today/override', [EmployeeController::class, 'updateToday']);
$router->get('/settings', [EmployeeController::class, 'settings']);
$router->post('/settings', [EmployeeController::class, 'settings']);
$router->get('/defaults', [EmployeeController::class, 'defaults']);
$router->post('/defaults', [EmployeeController::class, 'defaults']);
$router->get('/order-now', [EmployeeController::class, 'orderNow']);
$router->post('/order-now', [EmployeeController::class, 'orderNow']);
$router->post('/orders/cancel', [EmployeeController::class, 'cancelOrder']);
$router->post('/orders/deliver', [EmployeeController::class, 'deliverOrder']);
$router->get('/guest-order', [EmployeeController::class, 'guestOrder']);
$router->post('/guest-order', [EmployeeController::class, 'guestOrder']);
$router->get('/history', [EmployeeController::class, 'history']);
$router->get('/profile', [EmployeeController::class, 'profile']);
$router->post('/profile', [EmployeeController::class, 'profile']);
$router->post('/api/monkey-dismiss', [EmployeeController::class, 'dismissMonkeyToday']);

// Tea Staff
$router->get('/staff', [StaffController::class, 'dashboard']);
$router->post('/staff/status', [StaffController::class, 'updateStatus']);
$router->post('/staff/deliver-room', [StaffController::class, 'deliverRoom']);
$router->post('/staff/announcement', [StaffController::class, 'postAnnouncement']);
$router->post('/staff/announcement/clear', [StaffController::class, 'clearAnnouncement']);
$router->get('/staff/settings', [StaffController::class, 'settings']);
$router->get('/staff/notifications', [StaffController::class, 'settings']);
$router->post('/staff/notifications/read', [StaffController::class, 'markNotificationsRead']);
$router->get('/staff/poll', [StaffController::class, 'poll']);
$router->get('/staff/employee-search', [StaffController::class, 'searchEmployees']);
$router->get('/staff/employee-details', [StaffController::class, 'employeeDetails']);

// Push API
$router->get('/api/push/vapid-key', [PushController::class, 'vapidKey']);
$router->post('/api/push/subscribe', [PushController::class, 'subscribe']);
$router->post('/api/push/unsubscribe', [PushController::class, 'unsubscribe']);
$router->post('/api/push/test', [PushController::class, 'test']);

// Admin
$router->get('/admin', [AdminController::class, 'dashboard']);
$router->get('/admin/employees', [AdminController::class, 'employees']);
$router->post('/admin/employees', [AdminController::class, 'employees']);
$router->get('/admin/departments', [AdminController::class, 'departments']);
$router->post('/admin/departments', [AdminController::class, 'departments']);
$router->get('/admin/rooms', [AdminController::class, 'rooms']);
$router->post('/admin/rooms', [AdminController::class, 'rooms']);
$router->get('/admin/orders', [AdminController::class, 'orders']);
$router->post('/admin/orders', [AdminController::class, 'orders']);
$router->get('/admin/reports', [AdminController::class, 'reports']);
$router->get('/admin/schedule', [AdminController::class, 'schedule']);
$router->post('/admin/schedule', [AdminController::class, 'schedule']);
$router->get('/admin/notifications', [AdminController::class, 'notifications']);
$router->post('/admin/notifications', [AdminController::class, 'notifications']);
$router->get('/admin/settings', [AdminController::class, 'settings']);
$router->post('/admin/settings', [AdminController::class, 'settings']);
$router->post('/admin/settings/test-whatsapp', [AdminController::class, 'testWhatsapp']);

$router->dispatch();
