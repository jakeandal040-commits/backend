<?php
defined('PREVENT_DIRECT_ACCESS') OR exit('No direct script access allowed');

class MigrationController extends Controller
{
    public function __construct()
    {
        parent::__construct();
        if (PHP_SAPI !== 'cli' && (getenv('APP_ENV') === 'production' || getenv('MIGRATION_ENABLED') !== 'true')) {
            http_response_code(403);
            exit('Migration routes are disabled. Use php lava migration from the terminal.');
        }
        $this->call->library('migration');
    }

    public function create_migration($migration_class)
    {
        if (!preg_match('/^[a-z][a-z0-9_]*$/', $migration_class)) {
            http_response_code(422);
            exit('Use a snake_case migration name.');
        }
        $this->migration->create_migration($migration_class);
    }
    public function migrate() { $this->migration->migrate(); }
    public function rollback() { $this->migration->rollback(); }
    public function rollback_all() { $this->migration->rollback_all(); }
    public function refresh() { $this->migration->refresh(); }
    public function status() { $this->migration->status(); }
}
