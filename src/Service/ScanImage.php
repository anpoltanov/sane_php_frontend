<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ScanTask;
use App\Service\Exception\RuntimeException;

class ScanImage
{
    public function __construct(
        private readonly bool $mock = false,
        private readonly string $fixturePath = '',
    ) {
    }

    /**
     * @return array{resolutions: int[]}
     */
    public function getScannerOptions(string $device): array
    {
        if ($this->mock) {
            return ['resolutions' => [150, 300, 600]];
        }

        $stdout = $this->run(sprintf('scanimage --help --format=pnm -d %s', escapeshellarg($device)));
        if (!preg_match('/--resolution\D*(\d.*)dpi.*$/im', $stdout, $matches)) {
            throw new RuntimeException('Could not parse scanner resolutions');
        }

        $resolutions = array_map(static fn (string $value): int => (int) $value, explode('|', trim($matches[1])));
        $resolutions = array_values(array_filter($resolutions, static fn (int $value): bool => $value > 0));
        if ($resolutions === []) {
            throw new RuntimeException('Could not parse scanner resolutions');
        }

        return ['resolutions' => $resolutions];
    }

    /**
     * @return string[]
     */
    public function getScanners(): array
    {
        if ($this->mock) {
            return ['mock:scanner'];
        }

        $stdout = $this->run('scanimage -f %d%n');

        return trim($stdout) === '' ? [] : explode("\n", trim($stdout));
    }

    public function scanToFile(ScanTask $scanTask, string $outputPath): void
    {
        if ($this->mock) {
            if ($this->fixturePath === '' || !is_file($this->fixturePath)) {
                throw new RuntimeException('Mock scan fixture is missing');
            }
            if (!copy($this->fixturePath, $outputPath)) {
                throw new RuntimeException('Unable to copy mock scan fixture');
            }

            return;
        }

        $device = $scanTask->getDeviceName();
        if ($device === '') {
            throw new RuntimeException('Scanner device is empty');
        }

        $this->run(sprintf(
            'scanimage --mode=Color --resolution=%d --format=jpeg -d %s',
            $scanTask->getResolution(),
            escapeshellarg($device)
        ), $outputPath);

        if (!is_file($outputPath) || filesize($outputPath) === 0) {
            throw new RuntimeException('Scan produced an empty file');
        }
    }

    private function run(string $shellCmd, ?string $stdoutPath = null): string
    {
        $descriptors = [
            1 => $stdoutPath === null ? ['pipe', 'w'] : ['file', $stdoutPath, 'w'],
            2 => ['pipe', 'w'],
        ];

        $proc = proc_open($shellCmd, $descriptors, $pipes, null, null);
        if (!is_resource($proc)) {
            throw new RuntimeException('Unable to start scanimage');
        }

        $stdout = '';
        $stderr = '';
        try {
            if (isset($pipes[1]) && is_resource($pipes[1])) {
                $stdout = (string) stream_get_contents($pipes[1]);
            }
            if (isset($pipes[2]) && is_resource($pipes[2])) {
                $stderr = (string) stream_get_contents($pipes[2]);
            }
        } finally {
            foreach ($pipes as $pipe) {
                if (is_resource($pipe)) {
                    fclose($pipe);
                }
            }
            $exitCode = proc_close($proc);
        }

        if ($exitCode !== 0) {
            throw new RuntimeException(sprintf("scanimage failed (%d):\n%s", $exitCode, $stderr));
        }

        return $stdout;
    }
}
