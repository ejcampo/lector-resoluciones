<?php

namespace App\DTO;

/**
 * Data Transfer Object que representa un archivo cargado.
 */
class FileDTO {
    public string $name;
    public string $tempPath;
    public int $size;
    public string $status;

    public function __construct(string $name, string $tempPath, int $size, string $status = 'Pendiente') {
        $this->name = $name;
        $this->tempPath = $tempPath;
        $this->size = $size;
        $this->status = $status;
    }

    /**
     * Convierte el DTO a un array asociativo.
     */
    public function toArray(): array {
        return [
            'name' => $this->name,
            'tempPath' => $this->tempPath,
            'size' => $this->size,
            'status' => $this->status
        ];
    }
}
