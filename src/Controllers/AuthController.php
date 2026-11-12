<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Database;
use App\Core\Lang;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\Validator;
use App\Core\View;
use App\Services\AuditService;
use App\Services\ImageService;
use App\Services\OrderService;

class AuthController
{
    public function showLogin(): void
    {
        if (Auth::check()) {
            Response::redirect('/');
        }
        $allowReg = setting('allow_self_registration', '1') === '1';
        Response::html(View::render('auth/login', ['allowReg' => $allowReg], 'auth'));
    }

    public function login(): void
    {
        $employeeId = strtoupper(Validator::str(Request::post('employee_id'), 50));
        $password = (string)Request::post('password', '');
        $ip = Request::ip();

        if ($employeeId === '' || $password === '') {
            Session::flash('error', __('invalid_credentials'));
            Response::redirect('/login');
        }

        if (Auth::isRateLimited($ip, $employeeId)) {
            Session::flash('error', __('rate_limited'));
            Response::redirect('/login');
        }

        $user = Database::fetchOne('SELECT * FROM users WHERE employee_id = ?', [$employeeId]);
        if (!$user || !password_verify($password, $user['password_hash'])) {
            Auth::recordFailedAttempt($ip, $employeeId);
            Session::flash('error', __('invalid_credentials'));
            Response::redirect('/login');
        }

        if ($user['status'] === 'pending') {
            Session::flash('warning', __('account_pending'));
            Response::redirect('/login');
        }

        if ($user['status'] !== 'active') {
            Session::flash('error', __('account_disabled'));
            Response::redirect('/login');
        }

        Auth::clearAttempts($ip, $employeeId);
        Auth::login((int)$user['id']);
        if (!empty($user['preferred_lang'])) {
            Lang::set($user['preferred_lang'], true);
        }
        AuditService::log((int)$user['id'], $user['full_name'], 'login', 'user', (int)$user['id']);

        Response::redirect('/');
    }

    public function showRegister(): void
    {
        if (Auth::check()) {
            Response::redirect('/');
        }
        if (setting('allow_self_registration', '1') !== '1') {
            Session::flash('error', 'Self-registration is currently disabled. Contact Admin.');
            Response::redirect('/login');
        }

        $departments = Database::fetchAll('SELECT id, name FROM departments WHERE is_active = 1 ORDER BY name ASC');
        $rooms = Database::fetchAll("SELECT id, name, floor FROM rooms WHERE is_active = 1 AND room_type = 'office' ORDER BY name ASC");

        Response::html(View::render('auth/register', [
            'departments' => $departments,
            'rooms'       => $rooms,
        ], 'auth'));
    }

    public function register(): void
    {
        if (setting('allow_self_registration', '1') !== '1') {
            Session::flash('error', 'Self-registration is disabled.');
            Response::redirect('/login');
        }

        $fullName   = Validator::str(Request::post('full_name'), 150);
        $employeeId = strtoupper(Validator::str(Request::post('employee_id'), 50));
        $deptId     = Validator::intRange(Request::post('department_id'), 1, 999999, 0);
        $roomId     = Validator::intRange(Request::post('room_id'), 1, 999999, 0);
        $password   = (string)Request::post('password', '');

        if ($fullName === '' || $employeeId === '' || strlen($password) < 6) {
            Session::flash('error', 'Full Name, unique Employee ID, and a password (min 6 chars) are required.');
            Response::redirect('/register');
        }

        $exists = Database::fetchOne('SELECT id FROM users WHERE employee_id = ?', [$employeeId]);
        if ($exists) {
            Session::flash('error', 'This Employee ID is already registered.');
            Response::redirect('/register');
        }

        $dept = $deptId ? Database::fetchOne('SELECT id FROM departments WHERE id = ? AND is_active = 1', [$deptId]) : null;
        $room = $roomId ? Database::fetchOne("SELECT id FROM rooms WHERE id = ? AND is_active = 1 AND room_type = 'office'", [$roomId]) : null;

        if (!$dept || !$room) {
            Session::flash('error', 'Please select a valid active Department and Office Room.');
            Response::redirect('/register');
        }

        $requiresApproval = setting('require_admin_approval', '0') === '1';
        $status = $requiresApproval ? 'pending' : 'active';
        $hash = password_hash($password, PASSWORD_DEFAULT);

        // Never allow user to pick admin/staff role during self-registration; grant guest order access by default
        $userId = (int)Database::insert(
            "INSERT INTO users (employee_id, full_name, password_hash, role, department_id, room_id, status, can_order_for_guests, preferred_lang, created_at, updated_at)
             VALUES (?, ?, ?, 'employee', ?, ?, ?, 1, ?, NOW(), NOW())",
            [$employeeId, $fullName, $hash, $dept['id'], $room['id'], $status, Lang::current()]
        );

        OrderService::seedUserDefaultPreferences($userId);

        if (!empty($_FILES['cup_photo']) && ($_FILES['cup_photo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            try {
                $cupPath = ImageService::saveCupPhoto($_FILES['cup_photo'], $userId);
                if ($cupPath) {
                    Database::execute('UPDATE users SET cup_image = ? WHERE id = ?', [$cupPath, $userId]);
                }
            } catch (\Throwable $e) {
                Session::flash('warning', 'Account created, but cup photo upload failed: ' . $e->getMessage());
            }
        }

        AuditService::log($userId, $fullName, 'register', 'user', $userId, ['employee_id' => $employeeId, 'status' => $status]);

        if ($requiresApproval) {
            Session::flash('success', 'Registration submitted! An administrator will approve your account shortly.');
            Response::redirect('/login');
        }

        Auth::login($userId);
        Session::flash('success', 'Welcome to TeaCare! Configure your morning and afternoon tea defaults anytime.');
        Response::redirect('/today');
    }

    public function logout(): void
    {
        $user = Auth::user();
        if ($user) {
            AuditService::log((int)$user['id'], $user['full_name'], 'logout', 'user', (int)$user['id']);
        }
        Auth::logout();
        Response::redirect('/login');
    }
}
