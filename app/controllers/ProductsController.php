<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class ProductsController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->call->library('api');
        header('Cache-Control: no-store');
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'OPTIONS') {
            $this->call->database();
            $this->call->library('cache');
            $this->call->model('ProductModel', 'products');
        }
    }

    // Read raw JSON: escaping belongs in the UI, not in stored product names or passwords.
    private function input()
    {
        if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
            $this->api->respond_error('Send an application/json request body.', 415);
        }
        $data = json_decode(file_get_contents('php://input'), true);
        if (!is_array($data) || array_is_list($data) && $data !== []) {
            $this->api->respond_error('Invalid JSON object.', 400);
        }
        return $data;
    }
    private function user()
    {
        $auth = $this->api->require_jwt();
        $user = $this->db->raw('SELECT id, username, email, role, is_active FROM users WHERE id = ?', [$auth['sub']])->fetch();
        if (!$user || !$user['is_active']) $this->api->respond_error('Unauthorized', 401);
        unset($user['is_active']);
        return $user;
    }
    private function product($id)
    {
        if (!ctype_digit((string) $id) || (int) $id < 1) $this->api->respond_error('Product not found.', 404);
        $row = $this->products->find_product($id);
        if (!$row) $this->api->respond_error('Product not found.', 404);
        return $row;
    }
    public function preflight(...$args) { $this->api->respond(null, 204); }
    public function health() { $this->db->raw('SELECT 1'); $this->api->respond(['status' => 'ok']); }
    public function register()
    {
        $this->api->rate_limit('register_' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 10, 60);
        $data = $this->input();
        $username = is_string($data['username'] ?? null) ? trim($data['username']) : '';
        $email = is_string($data['email'] ?? null) ? strtolower(trim($data['email'])) : '';
        $password = $data['password'] ?? '';
        if (!preg_match('/^[a-zA-Z0-9_]{3,40}$/', $username)) $this->api->respond_error('Username must be 3–40 letters, numbers, or underscores.', 422);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 255) $this->api->respond_error('Enter a valid email address.', 422);
        if (!is_string($password) || strlen($password) < 8 || strlen($password) > 72) $this->api->respond_error('Password must be 8–72 bytes.', 422);
        try {
            $this->db->raw('INSERT INTO users (username, email, password) VALUES (?, ?, ?)', [$username, $email, password_hash($password, PASSWORD_BCRYPT)], true);
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') $this->api->respond_error('Username or email already exists.', 409);
            throw $e;
        }
        $this->api->respond(['message' => 'Account created. You can now sign in.'], 201);
    }
    public function login()
    {
        $this->api->rate_limit('login_' . ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 10, 60);
        $data = $this->input();
        $identity = is_string($data['identity'] ?? null) ? trim($data['identity']) : '';
        $password = $data['password'] ?? '';
        $user = $this->db->raw('SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1', [$identity, $identity])->fetch();
        if (!is_string($password) || !$user || !$user['is_active'] || !password_verify($password, $user['password'])) {
            $this->api->respond_error('Invalid username/email or password.', 401);
        }
        $tokens = $this->api->issue_tokens(['id' => $user['id'], 'role' => $user['role']]);
        $this->api->respond($tokens + ['user' => array_intersect_key($user, array_flip(['id', 'username', 'email', 'role']))]);
    }
    public function me() { $this->api->respond(['user' => $this->user()]); }
    public function refresh()
    {
        $data = $this->input();
        $token = $data['refresh_token'] ?? '';
        if (!is_string($token)) $this->api->respond_error('Invalid refresh token.', 422);
        $payload = $this->api->validate_jwt($token, 'refresh');
        if (!$payload) $this->api->respond_error('Invalid refresh token.', 401);
        $user = $this->db->raw('SELECT is_active FROM users WHERE id = ?', [$payload['sub']])->fetch();
        if (!$user || !$user['is_active']) $this->api->respond_error('Unauthorized', 401);
        $this->api->refresh_access_token($token);
    }
    public function logout()
    {
        $data = $this->input();
        if (!is_string($data['refresh_token'] ?? null)) $this->api->respond_error('Refresh token required.', 422);
        $this->api->revoke_refresh_token($data['refresh_token']);
        $this->api->respond(['message' => 'Signed out.']);
    }
    public function index()
    {
        $this->api->respond(['products' => $this->products->all_products()]);
    }
    public function show($id) { $this->api->respond(['product' => $this->product($id)]); }
    private function validate($data)
    {
        $errors = [];
        $name = is_string($data['product_name'] ?? null) ? trim($data['product_name']) : '';
        $description = $data['description'] ?? '';
        $price = $data['price'] ?? null;
        $quantity = $data['quantity'] ?? null;
        if ($name === '' || mb_strlen($name) > 100) $errors['product_name'] = 'Enter a product name of 1–100 characters.';
        if (!is_string($description) || strlen($description) > 65535) $errors['description'] = 'Description must be text within 65,535 bytes.';
        if ((!is_string($price) && !is_numeric($price)) || !preg_match('/^\d{1,8}(\.\d{1,2})?$/', (string) $price)) $errors['price'] = 'Enter a price from 0 to 99,999,999.99 with at most 2 decimal places.';
        if ((!is_string($quantity) && !is_int($quantity)) || !preg_match('/^\d{1,10}$/', (string) $quantity) || (float) $quantity > 2147483647) $errors['quantity'] = 'Quantity must be a whole number from 0 to 2,147,483,647.';
        if ($errors) $this->api->respond(['error' => 'Please check the highlighted fields.', 'errors' => $errors], 422);
        return [
            'product_name' => $name,
            'description' => $description,
            'price' => number_format((float) $price, 2, '.', ''),
            'quantity' => (int) $quantity,
        ];
    }
    public function store()
    {
        $values = $this->validate($this->input());
        $product = $this->products->create_product($values);
        $this->api->respond(['product' => $product, 'message' => 'Product added.'], 201);
    }
    public function update($id)
    {
        $existing = $this->product($id);
        $input = $this->input();
        $values = $this->validate(($_SERVER['REQUEST_METHOD'] === 'PATCH') ? array_merge($existing, $input) : $input);
        $product = $this->products->update_product($id, $values);
        $this->api->respond(['product' => $product, 'message' => 'Product updated.']);
    }
    public function destroy($id)
    {
        $this->product($id);
        $this->products->delete_product($id);
        $this->api->respond(['message' => 'Product deleted.']);
    }
}
