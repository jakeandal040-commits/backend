<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');
class ProductMiddleware
{
    public function handle(Closure $next)
    {
        // Route middleware runs before the destination controller is constructed.
        $lava = lava_instance() ?: new Controller();
        $lava->call->database();
        $lava->call->library('api');
        header('Cache-Control: no-store');
        $auth = $lava->api->require_jwt();
        // Read the current database role, never a role supplied by the client.
        $user = $lava->db->raw('SELECT role, is_active FROM users WHERE id = ?', [$auth['sub']])->fetch();
        if (!$user || !$user['is_active']) {
            $lava->api->respond_error('Unauthorized', 401);
        }
        $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');
        if (!in_array($method, ['GET', 'HEAD'], true) && $user['role'] !== 'admin') {
            $lava->api->respond_error('Only administrators can add, edit, or delete products.', 403);
        }
        return $next();
    }
}
