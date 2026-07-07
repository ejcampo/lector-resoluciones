<?php
/**
 * Script de migración de base de datos
 * Ejecuta todas las migraciones SQL en orden
 * 
 * Uso: php run-migrations.php
 */

// Cargar configuración de base de datos
$dbConfig = require __DIR__ . '/config/database.php';

echo "\n╔════════════════════════════════════════════════════════════════╗\n";
echo "║              MIGRACIÓN DE BASE DE DATOS - LECTOR              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Conectar a PostgreSQL
try {
    $dsn = sprintf(
        'pgsql:host=%s;port=%s;dbname=%s',
        $dbConfig['host'],
        $dbConfig['port'],
        $dbConfig['database']
    );

    $pdo = new PDO(
        $dsn,
        $dbConfig['username'],
        $dbConfig['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "✓ Conectado a PostgreSQL: {$dbConfig['host']}:{$dbConfig['port']}/{$dbConfig['database']}\n";
    echo "  Usuario: {$dbConfig['username']}\n\n";

} catch (PDOException $e) {
    echo "✗ Error de conexión a la base de datos:\n";
    echo "  {$e->getMessage()}\n\n";
    exit(1);
}

// Obtener todas las migraciones
$migrationsPath = __DIR__ . '/database/migrations';
$migrationFiles = array_filter(
    scandir($migrationsPath),
    fn($f) => substr($f, -4) === '.sql'
);
sort($migrationFiles);

if (empty($migrationFiles)) {
    echo "⚠ No se encontraron archivos de migración en: $migrationsPath\n";
    exit(0);
}

echo "Migraciones encontradas: " . count($migrationFiles) . "\n";
echo str_repeat('─', 64) . "\n\n";

$executed = 0;
$skipped = 0;
$failed = 0;

foreach ($migrationFiles as $file) {
    echo "📄 {$file}\n";

    $filePath = $migrationsPath . '/' . $file;
    $sqlContent = file_get_contents($filePath);

    if (!$sqlContent) {
        echo "   ✗ No se pudo leer el archivo\n";
        $failed++;
        continue;
    }

    try {
        // Para archivos con bloques DO, ejecutar como una sola declaración
        if (strpos($sqlContent, 'DO $$') !== false) {
            $pdo->exec($sqlContent);
        } else {
            // Para otros archivos, dividir por puntos y coma
            $statements = preg_split('/;(?=\s*$)/m', $sqlContent);
            foreach ($statements as $statement) {
                $statement = trim($statement);
                if (!empty($statement)) {
                    $pdo->exec($statement);
                }
            }
        }

        echo "   ✓ Ejecutada correctamente\n";
        $executed++;

    } catch (Exception $e) {
        // Si el error es sobre elementos que ya existen, es seguro ignorarlo
        if (strpos($e->getMessage(), 'already exists') !== false ||
            strpos($e->getMessage(), 'duplicate') !== false ||
            strpos($e->getMessage(), 'already defined') !== false ||
            strpos($e->getMessage(), 'Duplicate column') !== false) {
            echo "   ⊘ Ya existe (ignorado)\n";
            $skipped++;
        } else {
            echo "   ✗ Error: " . $e->getMessage() . "\n";
            $failed++;
        }
    }
}

echo "\n" . str_repeat('─', 64) . "\n";
echo "\nResultados:\n";
echo "  ✓ Ejecutadas:  {$executed}\n";
echo "  ⊘ Omitidas:    {$skipped}\n";
echo "  ✗ Errores:     {$failed}\n";

if ($failed > 0) {
    echo "\n⚠ Se encontraron errores durante la migración.\n";
    exit(1);
} else {
    echo "\n✓ Migración completada exitosamente.\n";
    exit(0);
}
