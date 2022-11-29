<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\ScanTask;

class ScanImage
{
    /**
     * @param string $device
     * @return array
     */
    public function getScannerOptions(string $device): array
    {
        $shellCmd = sprintf('scanimage --help -d %s', escapeshellarg($device));
        $message = $pipes = [];
        $proc = null;

        try {
            $proc = proc_open($shellCmd, [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],$pipes);

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            if ($stderr) {
                throw new Exception\RuntimeException(sprintf("Internal error %s:\n %s", $stdout, $stderr));
            }
            preg_match('/--resolution\D*(\d.*)dpi.*$/im', $stdout, $matches);

            $message['resolutions'] = explode("|", trim($matches[1]));

            return $message;
        } catch (Exception\RuntimeException $e) {
            throw $e;
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }
    }

    /**
     * @return string[]
     */
    public function getScanners(): array
    {
        $shellCmd = 'scanimage -f %d%n';
        $pipes = [];
        $proc = null;

        try {
            $proc = proc_open($shellCmd, [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],$pipes);

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            if ($stderr) {
                throw new Exception\RuntimeException(sprintf("Internal error %s:\n %s", $stdout, $stderr));
            }

            $message = explode("\n", trim($stdout));
        } catch (Exception\RuntimeException $e) {
            throw $e;
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }

        return $message;
    }

    /**
     * @param ScanTask $scanTask
     * @return string
     */
    public function scanImage(ScanTask $scanTask): string
    {
        $shellCmd = sprintf(
            'scanimage --mode=Color --resolution=%d --format=%s --compression=None',
            $scanTask->getResolution(),
            $scanTask->getExtension()
        );
        $pipes = [];
        $proc = null;

        try {
            // @TODO should be executed async (maybe MQ?)
            $proc = proc_open($shellCmd, [
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],$pipes);

            $stdout = stream_get_contents($pipes[1]);
            $stderr = stream_get_contents($pipes[2]);

            if ($stderr) {
                throw new Exception\RuntimeException(sprintf("Scan error %s:\n %s", $stdout, $stderr));
            }

            // @TODO move dirpath to config
            $filePath = sprintf('/mnt/sd/public/storage/scans/%s',
                $scanTask->getFullFileName()
            );
            $success = file_put_contents($filePath, $stdout);

            if ($success === false) {
                throw new Exception\RuntimeException(sprintf("File write error %s:\n %s", $stdout, $stderr));
            }

            // @TODO get this path from config
            return sprintf('Scanned to file: PublicSD/storage/scans/%s.',
                $scanTask->getFullFileName()
            );
        } catch (Exception\RuntimeException $e) {
            throw $e;
        } finally {
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($proc);
        }
    }
}