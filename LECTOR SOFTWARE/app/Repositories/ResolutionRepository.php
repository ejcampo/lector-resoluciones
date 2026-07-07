<?php

namespace App\Repositories;

use App\Database\DatabaseConnection;
use Throwable;

class ResolutionRepository {
    public function saveMany(array $results, ?int $usuario_id = null): array {
        $saved = 0;
        $errors = [];

        try {
            $pdo = DatabaseConnection::get();
        } catch (Throwable $e) {
            return [
                'enabled' => false,
                'saved' => 0,
                'errors' => [$e->getMessage()],
            ];
        }

        $sql = '
            INSERT INTO resoluciones_extraidas (
                archivo,
                numero_resolucion,
                primer_parrafo,
                firmante,
                estado,
                mensaje,
                usuario_id,
                imagen_firma
            ) VALUES (
                :archivo,
                :numero_resolucion,
                :primer_parrafo,
                :firmante,
                :estado,
                :mensaje,
                :usuario_id,
                :imagen_firma
            )
            ON CONFLICT (archivo) DO UPDATE SET
                numero_resolucion = EXCLUDED.numero_resolucion,
                primer_parrafo = EXCLUDED.primer_parrafo,
                firmante = EXCLUDED.firmante,
                estado = EXCLUDED.estado,
                mensaje = EXCLUDED.mensaje,
                usuario_id = EXCLUDED.usuario_id,
                imagen_firma = EXCLUDED.imagen_firma,
                updated_at = CURRENT_TIMESTAMP
        ';

        $stmt = $pdo->prepare($sql);

        foreach ($results as $result) {
            try {
                $stmt->execute([
                    ':archivo' => $result['archivo'] ?? '',
                    ':numero_resolucion' => $result['numero_resolucion'] ?? '',
                    ':primer_parrafo' => $result['primer_parrafo'] ?? '',
                    ':firmante' => $result['firmante'] ?? '',
                    ':estado' => $result['estado'] ?? '',
                    ':mensaje' => $result['mensaje'] ?? null,
                    ':usuario_id' => $usuario_id,
                    ':imagen_firma' => $result['imagen_firma'] ?? '',
                ]);
                $saved++;
            } catch (Throwable $e) {
                $errors[] = ($result['archivo'] ?? 'archivo desconocido') . ': ' . $e->getMessage();
            }
        }

        return [
            'enabled' => true,
            'saved' => $saved,
            'errors' => $errors,
        ];
    }

    public function deleteByArchivoAndUsuario(string $archivo, int $usuario_id): bool {
        try {
            $pdo = DatabaseConnection::get();
            $stmt = $pdo->prepare('DELETE FROM resoluciones_extraidas WHERE archivo = :archivo AND usuario_id = :usuario_id');
            $stmt->execute([
                ':archivo' => $archivo,
                ':usuario_id' => $usuario_id
            ]);
            return $stmt->rowCount() > 0;
        } catch (Throwable $e) {
            return false;
        }
    }
}
