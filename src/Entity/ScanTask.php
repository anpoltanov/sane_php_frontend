<?php

declare(strict_types=1);

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @TODO implement deviceName prop
 */
class ScanTask
{
    public const FILE_EXTENSION_PNG = 'png';
    public const FILE_EXTENSION_JPG = 'jpeg';
    public const FILE_EXTENSION_TIFF = 'tiff';

    #[Assert\Length(
        min: 1,
        max: 50,
        maxMessage: 'File name must not exceed {{ limit }} characters'
    )]
    protected string $fileName;

    #[Assert\Choice(callback: 'getAvailableExtensions')]
    #[Assert\NotBlank]
    protected string $extension;

    #[Assert\Choice(callback: 'getAvailableResolutions')]
    #[Assert\NotBlank]
    protected ?int $resolution = null;

    /**
     * @var int[]
     */
    protected static array $availableResolutions = [];

    public function __construct()
    {
        $this->fileName = (new \DateTime())->format('Y_m_d_H_i_s');
        $this->extension = self::FILE_EXTENSION_PNG;
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

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): ScanTask
    {
        $this->extension = $extension;
        return $this;
    }

    public function getResolution(): ?int
    {
        return $this->resolution;
    }

    public function setResolution(?int $resolution): ScanTask
    {
        $this->resolution = $resolution;
        return $this;
    }

    public function getFullFileName(): string
    {
        return sprintf('%s.%s', $this->fileName, $this->extension);
    }

    /**
     * @return string[]
     */
    public static function getAvailableExtensions(): array
    {
        return [
            self::FILE_EXTENSION_PNG,
            self::FILE_EXTENSION_JPG,
            self::FILE_EXTENSION_TIFF,
        ];
    }

    /**
     * @return int[]
     */
    public static function getAvailableResolutions(): array
    {
        return self::$availableResolutions;
    }

    public static function setAvailableResolutions(array $availableResolutions): void
    {
        $availableResolutions = array_map(static function ($value) {
            return (int) $value;
        }, $availableResolutions);
        self::$availableResolutions = $availableResolutions;
    }
}
