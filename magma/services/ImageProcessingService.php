<?php

declare(strict_types=1);

namespace Magma\services;

use Magma\infrastructure\storage\StorageInterface;
use RuntimeException;
use GdImage;

/**
 * Title: Centralized Image Processing & Optimization Service
 *
 * Purpose:
 * - Provides high-performance image manipulation, proportional resizing, center-square cropping, and WebP compression using PHP's native `ext-gd`.
 * - Decouples image rendering from local disk storage via `StorageInterface` injection.
 *
 * Why / Why this design:
 * - Dependency Inversion Principle (DIP): Uploaded images and thumbnails are saved via `StorageInterface`, enabling instant cloud compatibility (AWS S3, Cloudflare R2) and diskless unit testing.
 * - Zero Heavy Dependencies: Uses native PHP GD functions rather than pulling in massive external composer libraries (Intervention, Imagick).
 * - Modern Next-Gen Format Enforcement: Automatically converts raw JPEG/PNG images to lightweight WebP, reducing client bandwidth consumption by up to 80%.
 *
 * Teaching notes:
 * - Notice the alpha transparency handling (`imagealphablending` and `imagesavealpha`): this prevents transparent PNGs and WebPs from rendering with solid black backgrounds upon resizing.
 */
class ImageProcessingService
{
    private StorageInterface $storage;

    public function __construct(StorageInterface $storage)
    {
        $this->storage = $storage;
    }

    /**
     * Processes, resizes with center square cropping, converts to WebP, and persists to storage.
     *
     * Execution Flow:
     * 1. Extracts binary contents from uploaded file array, file path, or raw stream.
     * 2. Creates a GD image canvas from the binary payload.
     * 3. Calculates center-crop offsets and resamples onto a truecolor canvas.
     * 4. Encodes canvas into compressed WebP binary bytes in memory.
     * 5. Generates a randomized cryptographic filename (`bin2hex(random_bytes(16)) . '.webp'`).
     * 6. Persists the WebP payload via `StorageInterface::put()`.
     * 7. Frees GD memory buffers and returns the relative storage key.
     *
     * Logic behind the logic:
     * - In-memory output buffering (`ob_start()`) avoids creating intermediate temporary files on disk.
     *
     * @param string|array $sourceFile File path or standard $_FILES item
     * @param string $destinationDir Directory or key prefix in storage
     * @param int $targetWidth Target width in pixels
     * @param int $targetHeight Target height in pixels
     * @param int $quality WebP quality factor (1-100)
     * @return string Stored relative path/key
     * @throws RuntimeException
     */
    public function processAndStore(
        string|array $sourceFile,
        string $destinationDir = 'uploads/images',
        int $targetWidth = 800,
        int $targetHeight = 800,
        int $quality = 85
    ): string {
        $sourceData = $this->resolveSourceData($sourceFile);
        $srcImage = $this->createImageFromSource($sourceData);
        $dstImage = null;

        try {
            $dstImage = $this->cropAndResize($srcImage, $targetWidth, $targetHeight);
            $webpBytes = $this->encodeToWebp($dstImage, $quality);
            return $this->storeWebp($webpBytes, $destinationDir);
        } finally {
            imagedestroy($srcImage);
            if ($dstImage !== null) imagedestroy($dstImage);
        }
    }

    private function createImageFromSource(string $sourceData): GdImage
    {
        $srcImage = @imagecreatefromstring($sourceData);
        if ($srcImage === false) {
            throw new RuntimeException("Failed to decode image data into valid GD resource.");
        }
        return $srcImage;
    }

    private function cropAndResize(GdImage $srcImage, int $targetWidth, int $targetHeight): GdImage
    {
        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);

        if ($srcWidth <= 0 || $srcHeight <= 0) {
            throw new RuntimeException("Invalid image dimensions detected.");
        }

        $srcAspect = $srcWidth / $srcHeight;
        $targetAspect = $targetWidth / $targetHeight;

        if ($srcAspect >= $targetAspect) {
            $cropHeight = $srcHeight;
            $cropWidth = (int)round($srcHeight * $targetAspect);
            $srcX = (int)round(($srcWidth - $cropWidth) / 2);
            $srcY = 0;
        } else {
            $cropWidth = $srcWidth;
            $cropHeight = (int)round($srcWidth / $targetAspect);
            $srcX = 0;
            $srcY = (int)round(($srcHeight - $cropHeight) / 2);
        }

        $dstImage = $this->createTrueColorCanvas($targetWidth, $targetHeight);

        imagecopyresampled(
            $dstImage, $srcImage, 0, 0, $srcX, $srcY,
            $targetWidth, $targetHeight, $cropWidth, $cropHeight
        );

        return $dstImage;
    }

    private function createTrueColorCanvas(int $width, int $height): GdImage
    {
        $dstImage = imagecreatetruecolor($width, $height);
        if ($dstImage === false) {
            throw new RuntimeException("Failed to create destination GD truecolor image canvas.");
        }

        imagealphablending($dstImage, false);
        imagesavealpha($dstImage, true);
        $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
        if ($transparent !== false) {
            imagefilledrectangle($dstImage, 0, 0, $width, $height, $transparent);
        }

        return $dstImage;
    }

    private function encodeToWebp(GdImage $dstImage, int $quality): string
    {
        ob_start();
        imagewebp($dstImage, null, $quality);
        $webpBytes = ob_get_clean();

        if ($webpBytes === false || $webpBytes === '') {
            throw new RuntimeException("Failed to encode image to WebP format.");
        }
        return $webpBytes;
    }

    private function storeWebp(string $webpBytes, string $destinationDir): string
    {
        $token = bin2hex(random_bytes(16));
        $cleanDir = trim(str_replace('\\', '/', $destinationDir), '/');
        $key = $cleanDir !== '' ? "{$cleanDir}/{$token}.webp" : "{$token}.webp";

        if (!$this->storage->put($key, $webpBytes)) {
            throw new RuntimeException("Failed to persist processed image to storage key [{$key}].");
        }
        return $key;
    }

    /**
     * Resizes and center-crops an image into a 1:1 square thumbnail.
     *
     * @param string|array $sourceFile
     * @param int $size Width and height in pixels
     * @param int $quality Compression quality
     * @param string $destinationDir
     * @return string Stored relative path
     */
    public function cropToSquare(
        string|array $sourceFile,
        int $size = 800,
        int $quality = 85,
        string $destinationDir = 'uploads/squares'
    ): string {
        return $this->processAndStore($sourceFile, $destinationDir, $size, $size, $quality);
    }

    /**
     * Converts an image to optimized WebP format without resizing dimensions.
     *
     * @param string|array $sourceFile
     * @param int $quality Compression quality
     * @param string $destinationDir
     * @return string Stored relative path
     */
    public function convertToWebp(
        string|array $sourceFile,
        int $quality = 85,
        string $destinationDir = 'uploads/webp'
    ): string {
        $sourceData = $this->resolveSourceData($sourceFile);
        $srcImage = $this->createImageFromSource($sourceData);

        try {
            $width = imagesx($srcImage);
            $height = imagesy($srcImage);

            $dstImage = $this->createTrueColorCanvas($width, $height);
            imagecopy($dstImage, $srcImage, 0, 0, 0, 0, $width, $height);

            $webpBytes = $this->encodeToWebp($dstImage, $quality);
            imagedestroy($dstImage);

            return $this->storeWebp($webpBytes, $destinationDir);
        } finally {
            imagedestroy($srcImage);
        }
    }

    /**
     * Resolves raw binary image payload from file path, $_FILES array, or storage key.
     *
     * @param string|array $sourceFile
     * @return string
     * @throws RuntimeException
     */
    private function resolveSourceData(string|array $sourceFile): string
    {
        if (is_array($sourceFile)) {
            $tmpPath = $sourceFile['tmp_name'] ?? '';
            if ($tmpPath === '' || !file_exists($tmpPath)) {
                throw new RuntimeException("Uploaded temporary file does not exist.");
            }
            $data = file_get_contents($tmpPath);
            if ($data === false) {
                throw new RuntimeException("Failed to read uploaded temporary file data.");
            }
            return $data;
        }

        if (file_exists($sourceFile)) {
            $data = file_get_contents($sourceFile);
            if ($data !== false) {
                return $data;
            }
        }

        if ($this->storage->exists($sourceFile)) {
            $data = $this->storage->get($sourceFile);
            if ($data !== null) {
                return $data;
            }
        }

        throw new RuntimeException("Source image could not be resolved from path or storage key: [{$sourceFile}].");
    }
}
