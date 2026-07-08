<?php

namespace App\Helpers;

class PythonRuntime {
    public static function executable(): string {
        $baseDir = dirname(dirname(__DIR__));
        $userProfile = getenv('USERPROFILE') ?: 'C:\\Users\\julia';

        $candidates = [
            getenv('PYTHON_EXE') ?: '',
            $userProfile . '\\.cache\\codex-runtimes\\codex-primary-runtime\\dependencies\\python\\python.exe',
            $baseDir . '\\.venv\\Scripts\\python.exe',
            'python',
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === '') {
                continue;
            }

            if ($candidate === 'python' || file_exists($candidate)) {
                return $candidate;
            }
        }

        return 'python';
    }
}
