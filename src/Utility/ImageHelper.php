<?php

namespace Foodsharing\Utility;

use Exception;
use Imagick;

final class ImageHelper
{
    public function img(?string $file, $size = 'mini', $format = 'q', $altimg = false)
    {
        // prevent path traversal
        if (!empty($file)) {
            $file = preg_replace('/%/', '', (string)$file) ?? ''; // Destroys url encoded path elements to load images from other position
            $file = preg_replace('/\.+/', '.', $file) ?? ''; // Destroys url path navigation like /../../
        }

        if (!empty($file)) {
            if (str_starts_with($file, '/api/uploads/')) {
                // path for pictures uploaded with the new API
                if (is_numeric($size)) {
                    $file .= '?w=' . $size . '&h=' . $size;
                } elseif ($size === 'mini') {
                    $file .= '?w=35&h=35';
                }

                return $file;
            } elseif (file_exists('images/' . $file)) {
                // backward compatible path for old pictures
                if (!file_exists('images/' . $size . '_' . $format . '_' . $file)) {
                    $this->resizeImg('images/' . $file, $size, $format);
                }

                return '/images/' . $size . '_' . $format . '_' . $file;
            }
        }

        if ($altimg === false) {
            return '/img/' . $size . '_' . $format . '_avatar.png';
        }

        return $altimg;
    }

    private function resizeImg(string $img, string $width, string $format): bool
    {
        // prevent path traversal
        $img = preg_replace('/%/', '', $img) ?? '';
        $img = preg_replace('/\.+/', '.', $img) ?? '';
        if (!file_exists($img)) {
            return false;
        }

        $opt = 'auto';
        if ($format == 'q') {
            $opt = 'crop';
        }

        try {
            $newimg = str_replace('/', '/' . $width . '_' . $format . '_', $img);

            $image = new Imagick($img);

            // Get original dimensions
            $sourceWidth = $image->getImageWidth();
            $sourceHeight = $image->getImageHeight();

            $newWidth = (int)$width;
            $newHeight = $newWidth;

            if ($opt === 'crop') {
                // Crop to square (1:1 ratio) then resize
                $ratio = $sourceWidth / $sourceHeight;

                if ($ratio > 1) {
                    // Width is larger - crop width
                    $cropWidth = $sourceHeight;
                    $cropHeight = $sourceHeight;
                    $cropX = (int)(($sourceWidth - $sourceHeight) / 2);
                    $cropY = 0;
                } else {
                    // Height is larger or equal - crop height
                    $cropWidth = $sourceWidth;
                    $cropHeight = $sourceWidth;
                    $cropX = 0;
                    $cropY = (int)(($sourceHeight - $sourceWidth) / 2);
                }

                $image->cropImage($cropWidth, $cropHeight, $cropX, $cropY);
                $image->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 0.9, true);
            } else {
                // Just resize while maintaining aspect ratio
                $aspectRatio = $sourceHeight / $sourceWidth;
                $newHeight = (int)($newWidth * $aspectRatio);
                $image->resizeImage($newWidth, $newHeight, Imagick::FILTER_LANCZOS, 0.9, true);
            }

            // Write the resized image
            $image->writeImage($newimg);

            return true;
        } catch (Exception) {
            return false;
        }
    }
}
