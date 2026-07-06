<?php

namespace App\Controllers;

use App\Database\DatabaseConnection;
use Throwable;

class ResolucionesController {
    /**
     * Obtiene la lista de resoluciones de un usuario específico.
     */
    public function getUserResolutions(): void {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Método no permitido.'
            ]);
            return;
        }

        $usuario_id = isset($_GET['usuario_id']) ? (int)$_GET['usuario_id'] : 0;

        if ($usuario_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'ID de usuario inválido o ausente.'
            ]);
            return;
        }

        try {
            $pdo = DatabaseConnection::get();
            $stmt = $pdo->prepare('
                SELECT archivo, numero_resolucion, primer_parrafo, firmante, estado, mensaje, imagen_firma, actualizado_en 
                FROM resoluciones_extraidas 
                WHERE usuario_id = :usuario_id
                ORDER BY actualizado_en DESC
            ');
            $stmt->execute([':usuario_id' => $usuario_id]);
            $resoluciones = $stmt->fetchAll();

            echo json_encode([
                'success' => true,
                'results' => $resoluciones
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ]);
        }
    }
}
