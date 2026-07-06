<?php

namespace App\Controllers;

use App\Database\DatabaseConnection;
use Throwable;

/**
 * Controlador para gestionar la autenticación de usuarios.
 */
class AuthController {
    /**
     * Valida las credenciales de inicio de sesión por correo.
     * Retorna JSON con los datos del usuario si el ingreso es correcto.
     */
    public function handleLogin(): void {
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Método no permitido.'
            ]);
            return;
        }

        try {
            $rawInput = file_get_contents('php://input');
            $data = json_decode($rawInput, true);

            $correo = trim($data['correo'] ?? '');
            $contrasena = $data['contrasena'] ?? '';

            if (empty($correo) || empty($contrasena)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'El correo y la contraseña son obligatorios.'
                ]);
                return;
            }

            // Conectar a la base de datos y buscar el usuario por correo
            $pdo = DatabaseConnection::get();
            $stmt = $pdo->prepare('
                SELECT id, correo, contrasena, nombre_completo, rol 
                FROM usuarios 
                WHERE LOWER(correo) = LOWER(:correo)
            ');
            $stmt->execute([':correo' => $correo]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($contrasena, $user['contrasena'])) {
                http_response_code(401);
                echo json_encode([
                    'success' => false,
                    'error' => 'El correo electrónico o la contraseña son incorrectos.'
                ]);
                return;
            }

            // Autenticación exitosa: retornar información básica del usuario
            echo json_encode([
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'correo' => $user['correo'],
                    'nombre_completo' => $user['nombre_completo'],
                    'rol' => $user['rol']
                ]
            ]);

        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Error interno en el servidor: ' . $e->getMessage()
            ]);
        }
    }
}
