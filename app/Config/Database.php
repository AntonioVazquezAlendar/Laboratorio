<?php
class Database
{
    public function connect($serverOnly = false)
    {
        $local = require __DIR__ . '/database.local.php';
        $name = getenv('LAB_DB_NAME') ?: 'laboratorio_clinico';
        if (!preg_match('/^laboratorio_[a-z0-9_]+$/D', $name)) {
            throw new RuntimeException('Nombre de base del laboratorio no permitido.');
        }
        $dsn = 'mysql:host=' . (getenv('LAB_DB_HOST') ?: $local['host']) .
            ($serverOnly ? '' : ';dbname=' . $name) . ';charset=utf8mb4';
        return new PDO($dsn, getenv('LAB_DB_USER') ?: $local['username'],
            getenv('LAB_DB_PASSWORD') !== false ? getenv('LAB_DB_PASSWORD') : $local['password'],
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false));
    }
}
