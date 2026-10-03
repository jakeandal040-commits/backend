<?php
// This installed LavaLust version discovers custom commands in app/commands/.
class Migration
{
    public static $command = 'migration';
    public static $description = 'Manage database migrations';
    public static $arguments = ['[action]' => 'run, create-migration, rollback, rollback-all, refresh, status', '[name]' => 'Snake_case migration name'];
    public function handle($action = null, array $flags = [], $name = null)
    {
        $map = ['run' => 'migrate', 'create-migration' => 'create-migration', 'rollback' => 'rollback', 'rollback-all' => 'rollback-all', 'refresh' => 'refresh', 'status' => 'status'];
        $action = $action ?? 'run';
        if (!isset($map[$action])) {
            fwrite(STDERR, 'Unknown action. Available: ' . implode(', ', array_keys($map)) . PHP_EOL);
            exit(1);
        }
        $route = $map[$action];
        if ($action === 'create-migration') {
            if (!$name || !preg_match('/^[a-z][a-z0-9_]*$/', $name)) {
                fwrite(STDERR, 'Example: php lava migration create-migration create_products_table' . PHP_EOL);
                exit(1);
            }
            $route .= '/' . $name;
        }
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(PUBLIC_DIR . 'index.php') . ' ' . escapeshellarg($route), $code);
        exit($code);
    }
}
