<?php

declare(strict_types=1);

namespace App\Service;

use App\Service\Exception\RuntimeException;

class ScanConverter
{
    public const FORMAT_JPEG = 'jpeg';
    public const FORMAT_PNG = 'png';
    public const FORMAT_TIFF = 'tiff';
    public const FORMAT_PDF = 'pdf';

    public const MIME_TYPES = [
        self::FORMAT_JPEG => 'image/jpeg',
        self::FORMAT_PNG => 'image/png',
        self::FORMAT_TIFF => 'image/tiff',
        self::FORMAT_PDF => 'application/pdf',
    ];

    /**
     * @return string[]
     */
    public static function formats(): array
    {
        return [
            self::FORMAT_JPEG,
            self::FORMAT_PNG,
            self::FORMAT_TIFF,
            self::FORMAT_PDF,
        ];
    }

    public function mimeType(string $format): string
    {
        if (!isset(self::MIME_TYPES[$format])) {
            throw new RuntimeException(sprintf('Unsupported format "%s"', $format));
        }

        return self::MIME_TYPES[$format];
    }

    public function generatePreview(string $sourcePath, string $destinationPath, int $maxWidth = 1200): void
    {
        if (extension_loaded('imagick')) {
            $image = new \Imagick($sourcePath);
            try {
                if ($image->getImageWidth() > $maxWidth) {
                    $image->resizeImage($maxWidth, 0, \Imagick::FILTER_LANCZOS, 1);
                }
                $image->setImageFormat('jpeg');
                $image->setImageCompressionQuality(75);
                $image->writeImage($destinationPath);
            } finally {
                $image->clear();
                $image->destroy();
            }

            return;
        }

        $this->generatePreviewWithGd($sourcePath, $destinationPath, $maxWidth);
    }

    public function convert(string $sourcePath, string $destinationPath, string $format): void
    {
        $format = strtolower($format);
        if (!in_array($format, self::formats(), true)) {
            throw new RuntimeException(sprintf('Unsupported format "%s"', $format));
        }

        if ($format === self::FORMAT_JPEG && $this->isJpeg($sourcePath)) {
            if (!copy($sourcePath, $destinationPath)) {
                throw new RuntimeException('Unable to copy scan file');
            }

            return;
        }

        if (extension_loaded('imagick')) {
            $this->convertWithImagick($sourcePath, $destinationPath, $format);

            return;
        }

        if (in_array($format, [self::FORMAT_JPEG, self::FORMAT_PNG], true)) {
            $this->convertWithGd($sourcePath, $destinationPath, $format);

            return;
        }

        throw new RuntimeException('PDF and TIFF export require the Imagick PHP extension');
    }

    private function convertWithImagick(string $sourcePath, string $destinationPath, string $format): void
    {
        $image = new \Imagick($sourcePath);
        try {
            $image->setImageFormat($format);
            if ($format === self::FORMAT_JPEG) {
                $image->setImageCompressionQuality(85);
            }
            if ($format === self::FORMAT_TIFF) {
                $image->setImageCompression(\Imagick::COMPRESSION_LZW);
            }
            $image->writeImage($destinationPath);
        } finally {
            $image->clear();
            $image->destroy();
        }
    }

    private function convertWithGd(string $sourcePath, string $destinationPath, string $format): void
    {
        $image = $this->gdLoad($sourcePath);
        try {
            $ok = $format === self::FORMAT_PNG
                ? imagepng($image, $destinationPath)
                : imagejpeg($image, $destinationPath, 85);
            if ($ok === false) {
                throw new RuntimeException('Unable to convert scan image');
            }
        } finally {
            imagedestroy($image);
        }
    }

    private function generatePreviewWithGd(string $sourcePath, string $destinationPath, int $maxWidth): void
    {
        $source = $this->gdLoad($sourcePath);
        $width = imagesx($source);
        $height = imagesy($source);
        $newWidth = $width > $maxWidth ? $maxWidth : $width;
        $newHeight = (int) max(1, (int) round($height * ($newWidth / max(1, $width))));
        $preview = imagecreatetruecolor($newWidth, $newHeight);
        try {
            imagecopyresampled($preview, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
            if (imagejpeg($preview, $destinationPath, 75) === false) {
                throw new RuntimeException('Unable to write preview image');
            }
        } finally {
            imagedestroy($source);
            imagedestroy($preview);
        }
    }

    /**
     * @return \GdImage
     */
    private function gdLoad(string $sourcePath): \GdImage
    {
        $info = @getimagesize($sourcePath);
        if ($info === false) {
            throw new RuntimeException('Unable to read scan image');
        }

        $image = match ($info[2]) {
            IMAGETYPE_JPEG => imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG => imagecreatefrompng($sourcePath),
            default => false,
        };

        if ($image === false) {
            throw new RuntimeException('Unable to load scan image');
        }

        return $image;
    }

    private function isJpeg(string $path): bool
    {
        $info = @getimagesize($path);
        if ($info === false) {
            $lower = strtolower($path);

            return str_ends_with($lower, '.jpg') || str_ends_with($lower, '.jpeg');
        }

        return $info[2] === IMAGETYPE_JPEG;
    }
}
