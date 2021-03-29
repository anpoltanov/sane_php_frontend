<?php

declare(strict_types=1);

namespace App\Entity;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * @TODO implement deviceName prop
 * Class ScanTask
 * @package App\Entity
 */
class ScanTask
{
    const FILE_EXTENSION_PNG = 'png';
    const FILE_EXTENSION_JPG = 'jpeg';
    const FILE_EXTENSION_TIFF = 'tiff';

    /**
     * @var string
     * @Assert\Length(
     *      min = 1,
     *      max = 50,
     *      maxMessage = "File name must not exceed {{ limit }} characters"
     * )
     */
    protected $fileName;

    /**
     * @var string
     * @Assert\Choice(callback="getAvailableExtensions")
     * @Assert\NotBlank()
     */
    protected $extension;

    /**
     * @var int|null
     * @Assert\Choice(callback="getAvailableResolutions")
     * @Assert\NotBlank()
     */
    protected $resolution;

    /**
     * @var array
     */
    protected static $availableResolutions = [];

    /**
     * ScanTask constructor.
     */
    public function __construct()
    {
        $this->fileName = (new \DateTime())->format('Y_m_d_H_i_s');
        $this->extension = self::FILE_EXTENSION_PNG;
    }

    /**
     * @return string
     */
    public function getFileName(): string
    {
        return $this->fileName;
    }

    /**
     * @param string $fileName
     * @return ScanTask
     */
    public function setFileName(string $fileName): ScanTask
    {
        $this->fileName = $fileName;
        return $this;
    }

    /**
     * @return string
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * @param string $extension
     * @return ScanTask
     */
    public function setExtension(string $extension): ScanTask
    {
        $this->extension = $extension;
        return $this;
    }

    /**
     * @return int|null
     */
    public function getResolution(): ?int
    {
        return $this->resolution;
    }

    /**
     * @param int|null $resolution
     * @return ScanTask
     */
    public function setResolution(?int $resolution): ScanTask
    {
        $this->resolution = $resolution;
        return $this;
    }

    /**
     * @return string
     */
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

    /**
     * @param array $availableResolutions
     */
    public static function setAvailableResolutions(array $availableResolutions): void
    {
        $availableResolutions = array_map(function ($value) {
            return (int) $value;
        }, $availableResolutions);
        self::$availableResolutions = $availableResolutions;
    }
}