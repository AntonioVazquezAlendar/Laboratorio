<?php
class Database
{
    public function connect($serverOnly = false)
    {
        $config = __DIR__ . '/database.local.php';
        $local = is_file($config) ? require $config : array();
        $name = getenv('LAB_DB_NAME') ?: (isset($local['database']) ? $local['database'] : 'laboratorio_clinico');
        if (!preg_match('/^laboratorio_[a-z0-9_]+$/D', $name)) {
            throw new RuntimeException('Nombre de base del laboratorio no permitido.');
        }
        $dsn = 'mysql:host=' . (getenv('LAB_DB_HOST') ?: (isset($local['host']) ? $local['host'] : '127.0.0.1')) .
            ($serverOnly ? '' : ';dbname=' . $name) . ';charset=utf8mb4';
        return new PDO($dsn, getenv('LAB_DB_USER') ?: (isset($local['username']) ? $local['username'] : 'root'),
            getenv('LAB_DB_PASSWORD_EMPTY') === '1' ? '' : (getenv('LAB_DB_PASSWORD') !== false ? getenv('LAB_DB_PASSWORD') : (isset($local['password']) ? $local['password'] : '')),
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false));
    }
}
