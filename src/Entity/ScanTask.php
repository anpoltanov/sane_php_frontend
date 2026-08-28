<?php

declare(strict_types=1);

namespace App\Entity;

use App\Service\ScanConverter;
use Symfony\Component\Validator\Constraints as Assert;

class ScanTask
{
    #[Assert\Length(
        min: 1,
        max: 50,
        maxMessage: 'File name must not exceed {{ limit }} characters'
    )]
    protected string $fileName;

    #[Assert\NotBlank]
    #[Assert\Length(max: 200)]
    protected string $deviceName = '';

    #[Assert\NotBlank]
    #[Assert\Range(min: 50, max: 2400)]
    protected ?int $resolution = null;

    public function __construct()
    {
        $this->fileName = (new \DateTimeImmutable())->format('Y_m_d_H_i_s');
    }

    public function getFileName(): string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): ScanTask
    {
        $this->fileName = $fileName;

        return $this;
    }

    public function getDeviceName(): string
    {
        return $this->deviceName;
    }

    public function setDeviceName(string $deviceName): ScanTask
    {
        $this->deviceName = $deviceName;

        return $this;
    }

    public function getResolution(): ?int
    {
        return $this->resolution;
    }

    public function setResolution(int|string|null $resolution): ScanTask
    {
        $this->resolution = $resolution === null || $resolution === '' ? null : (int) $resolution;

        return $this;
    }

    public function getFullFileName(string $format = ScanConverter::FORMAT_JPEG): string
    {
        return sprintf('%s.%s', $this->fileName, $format);
    }
}
