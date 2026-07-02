<?php

namespace App\Helpers;

/**
 * Helper estático para acceder a la configuración de la aplicación.
 *
 * Carga el archivo config/app.php una sola vez y expone métodos
 * para consultar valores como DEBUG_MODE.
 */
class AppConfig {
    private static ?array $config = null;

    /**
     * Carga la configuración desde config/app.php (solo una vez).
     */
    private static function load(): void {
        if (self::$config === null) {
            $configFile = dirname(dirname(__DIR__)) . '/config/app.php';
            if (file_exists($configFile)) {
                self::$config = require $configFile;
            } else {
                self::$config = [];
            }
        }
    }

    /**
     * Verifica si el modo DEBUG está activado.
     *
     * @return bool
     */
    public static function isDebug(): bool {
        self::load();
        return self::$config['DEBUG_MODE'] ?? false;
    }

    /**
     * Obtiene un valor de configuración por clave.
     *
     * @param string $key Clave de configuración
     * @param mixed $default Valor por defecto si no existe
     * @return mixed
     */
    public static function get(string $key, mixed $default = null): mixed {
        self::load();
        return self::$config[$key] ?? $default;
    }
}
