<?php

declare(strict_types=1);

namespace Magma\services;

use RuntimeException;
use GdImage;

/**
 * Title: Centralized Image Processing & Optimization Service
 *
 * Purpose:
 * - Provides high-performance image manipulation, proportional resizing, center-square cropping, and WebP compression using PHP's native `ext-gd`.
 * - Decouples image rendering from local disk storage, adhering to the Single Responsibility Principle.
 *
 * Why / Why this design:
 * - Single Responsibility Principle (SRP): This service only handles image manipulation. Storage is handled elsewhere.
 * - Zero Heavy Dependencies: Uses native PHP GD functions rather than pulling in massive external composer libraries (Intervention, Imagick).
 * - Modern Next-Gen Format Enforcement: Automatically converts raw JPEG/PNG images to lightweight WebP, reducing client bandwidth consumption by up to 80%.
 *
 * Teaching notes:
 * - Notice the alpha transparency handling (`imagealphablending` and `imagesavealpha`): this prevents transparent PNGs and WebPs from rendering with solid black backgrounds upon resizing.
 */
class ImageProcessingService
{
    /**
     * Processes, resizes with center square cropping, and converts to WebP.
     *
     * Execution Flow:
     * 1. Creates a GD image canvas from the binary payload.
     * 2. Calculates center-crop offsets and resamples onto a truecolor canvas.
     * 3. Encodes canvas into compressed WebP binary bytes in memory.
     * 4. Frees GD memory buffers and returns the raw WebP bytes.
     *
     * Logic behind the logic:
     * - In-memory output buffering (`ob_start()`) avoids creating intermediate temporary files on disk.
     *
     * @param string $sourceData Raw binary image payload
     * @param int $targetWidth Target width in pixels
     * @param int $targetHeight Target height in pixels
     * @param int $quality WebP quality factor (1-100)
     * @return string Raw WebP binary bytes
     * @throws RuntimeException
     */
    public function processImage(
        string $sourceData,
        int $targetWidth = 800,
        int $targetHeight = 800,
        int $quality = 85
    ): string {
        $srcImage = $this->createImageFromSource($sourceData);
        $dstImage = null;

        try {
            $dstImage = $this->cropAndResize($srcImage, $targetWidth, $targetHeight);
            return $this->encodeToWebp($dstImage, $quality);
        } finally {
            imagedestroy($srcImage);
            if ($dstImage !== null) imagedestroy($dstImage);
        }
    }

    /**
     * Decodes binary source data into a GD image resource.
     *
     * Execution Flow:
     * 1. Temporarily overrides the error handler to catch warnings from `imagecreatefromstring`.
     * 2. Attempts to decode the binary image payload.
     * 3. Restores the error handler and checks for valid GD resource creation.
     *
     * Logic behind the logic:
     * - Malformed image data triggers E_WARNING in PHP GD, which cannot be caught by try/catch directly unless converted to Exceptions via set_error_handler.
     *
     * @param string $sourceData
     * @return GdImage
     * @throws RuntimeException
     */
    private function createImageFromSource(string $sourceData): GdImage
    {
        set_error_handler(function ($severity, $message, $file, $line) {
            throw new RuntimeException("GD Error: " . $message, 0, new \ErrorException($message, 0, $severity, $file, $line));
        });

        try {
            $srcImage = imagecreatefromstring($sourceData);
        } finally {
            restore_error_handler();
        }

        if ($srcImage === false) {
            throw new RuntimeException("Failed to decode image data into valid GD resource.");
        }
        return $srcImage;
    }

    /**
     * Crops and resizes an image to fit a target dimension proportionally.
     *
     * Execution Flow:
     * 1. Compares source aspect ratio with target aspect ratio.
     * 2. Calculates coordinates to perform a center crop based on which dimension needs clipping.
     * 3. Creates a truecolor canvas of the target dimensions.
     * 4. Copies and resamples the source image onto the canvas using the calculated coordinates.
     *
     * Logic behind the logic:
     * - Center-cropping ensures that thumbnail proportions strictly match the requested sizes without squashing or stretching the source imagery.
     *
     * @param GdImage $srcImage
     * @param int $targetWidth
     * @param int $targetHeight
     * @return GdImage
     */
    private function cropAndResize(GdImage $srcImage, int $targetWidth, int $targetHeight): GdImage
    {
        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);

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

    /**
     * Creates a new truecolor image canvas with full alpha transparency support.
     *
     * Execution Flow:
     * 1. Initializes a truecolor image via GD.
     * 2. Disables alpha blending and enables alpha saving to retain transparency.
     * 3. Fills the canvas with a transparent background.
     *
     * Logic behind the logic:
     * - Explicitly allocating and filling with transparency ensures PNGs and WebPs do not render with black backgrounds when resized.
     *
     * @param int $width
     * @param int $height
     * @return GdImage
     * @throws RuntimeException
     */
    private function createTrueColorCanvas(int $width, int $height): GdImage
    {
        $dstImage = imagecreatetruecolor(max(1, $width), max(1, $height));
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

    /**
     * Encodes a GD image resource into WebP binary format.
     *
     * Execution Flow:
     * 1. Starts output buffering to intercept GD's direct output.
     * 2. Writes the WebP image stream with the specified quality factor.
     * 3. Captures and cleans the buffer, throwing an exception if it failed.
     *
     * Logic behind the logic:
     * - GD outputs directly to stdout or a file path by default; using output buffering allows capturing the binary data entirely in memory.
     *
     * @param GdImage $dstImage
     * @param int $quality
     * @return string
     * @throws RuntimeException
     */
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

    /**
     * Resizes and center-crops an image into a 1:1 square thumbnail.
     *
     * @param string $sourceData Raw binary image payload
     * @param int $size Width and height in pixels
     * @param int $quality Compression quality
     * @return string Raw WebP binary bytes
     */
    public function cropToSquare(
        string $sourceData,
        int $size = 800,
        int $quality = 85
    ): string {
        return $this->processImage($sourceData, $size, $size, $quality);
    }

    /**
     * Converts an image to optimized WebP format without resizing dimensions.
     *
     * @param string $sourceData Raw binary image payload
     * @param int $quality Compression quality
     * @return string Raw WebP binary bytes
     */
    public function convertToWebp(
        string $sourceData,
        int $quality = 85
    ): string {
        $srcImage = $this->createImageFromSource($sourceData);

        try {
            $width = imagesx($srcImage);
            $height = imagesy($srcImage);

            $dstImage = $this->createTrueColorCanvas($width, $height);
            imagecopy($dstImage, $srcImage, 0, 0, 0, 0, $width, $height);

            $webpBytes = $this->encodeToWebp($dstImage, $quality);
            imagedestroy($dstImage);

            return $webpBytes;
        } finally {
            imagedestroy($srcImage);
        }
    }
}
