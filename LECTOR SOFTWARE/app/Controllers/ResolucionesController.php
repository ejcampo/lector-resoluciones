<?php

namespace App\Controllers;

use App\Database\DatabaseConnection;
use Throwable;

class ResolucionesController {
    /**
     * Obtiene la lista de resoluciones de un usuario específico (no confirmadas).
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
                SELECT archivo, numero_resolucion, primer_parrafo, firmante, estado, mensaje, imagen_firma, updated_at 
                FROM resoluciones_extraidas 
                WHERE usuario_id = :usuario_id AND (confirmado = false OR confirmado IS NULL)
                ORDER BY updated_at DESC
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

    /**
     * Obtiene las resoluciones confirmadas de un usuario específico.
     */
    public function getConfirmedResolutions(): void {
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
                SELECT id, archivo, numero_resolucion, primer_parrafo, firmante, estado, mensaje, imagen_firma, updated_at 
                FROM resoluciones_extraidas 
                WHERE usuario_id = :usuario_id AND confirmado = true
                ORDER BY updated_at DESC
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

    /**
     * Marca documentos como confirmados.
     */
    public function confirmarDocumentos(): void {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Método no permitido.'
            ]);
            return;
        }

        $payload = json_decode(file_get_contents('php://input'), true) ?? [];
        $archivos = $payload['archivos'] ?? [];
        $usuario_id = isset($payload['usuario_id']) ? (int)$payload['usuario_id'] : 0;

        if (empty($archivos) || $usuario_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Parámetros inválidos o ausentes.'
            ]);
            return;
        }

        try {
            $pdo = DatabaseConnection::get();
            $confirmados = 0;

            foreach ($archivos as $archivo) {
                $stmt = $pdo->prepare('
                    UPDATE resoluciones_extraidas 
                    SET confirmado = true
                    WHERE archivo = :archivo AND usuario_id = :usuario_id
                ');
                $stmt->execute([
                    ':archivo' => $archivo,
                    ':usuario_id' => $usuario_id
                ]);

                if ($stmt->rowCount() > 0) {
                    $confirmados++;
                }
            }

            echo json_encode([
                'success' => true,
                'confirmados' => $confirmados,
                'message' => "Se confirmaron {$confirmados} documento(s)."
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error interno: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Elimina una resolución específica para un usuario.
     */
    public function deleteResolution(): void {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Método no permitido.'
            ]);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        $archivo = $input['archivo'] ?? '';
        $usuario_id = isset($input['usuario_id']) ? (int)$input['usuario_id'] : 0;

        if (empty($archivo) || $usuario_id <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Faltan parámetros requeridos: archivo o usuario_id.'
            ]);
            return;
        }

        try {
            $repo = new \App\Repositories\ResolutionRepository();
            $deleted = $repo->deleteByArchivoAndUsuario($archivo, $usuario_id);

            if ($deleted) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Resolución eliminada correctamente de la base de datos.'
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'No se encontró la resolución o no tienes permisos para eliminarla.'
                ]);
            }
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error interno al intentar eliminar: ' . $e->getMessage()
            ]);
        }
    }
}