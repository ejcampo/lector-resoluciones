<?php

namespace App\Repositories;

use App\Database\DatabaseConnection;
use Throwable;

class ResolutionRepository {
    public function saveMany(array $results): array {
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
                mensaje
            ) VALUES (
                :archivo,
                :numero_resolucion,
                :primer_parrafo,
                :firmante,
                :estado,
                :mensaje
            )
            ON CONFLICT (archivo) DO UPDATE SET
                numero_resolucion = EXCLUDED.numero_resolucion,
                primer_parrafo = EXCLUDED.primer_parrafo,
                firmante = EXCLUDED.firmante,
                estado = EXCLUDED.estado,
                mensaje = EXCLUDED.mensaje,
                actualizado_en = CURRENT_TIMESTAMP
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
}
