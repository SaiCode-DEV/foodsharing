<?php

namespace Foodsharing\Utility;

use Exception;
use Flourish\fImage;

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
        if (file_exists($img)) {
            $opt = 'auto';
            if ($format == 'q') {
                $opt = 'crop';
            }

            try {
                $newimg = str_replace('/', '/' . $width . '_' . $format . '_', $img);
                copy($img, $newimg);
                $img = new fImage($newimg);

                if ($opt == 'crop') {
                    $img->cropToRatio(1, 1);
                    $img->resize($width, $width);
                } else {
                    $img->resize($width, 0);
                }

                $img->saveChanges();

                return true;
            } catch (Exception) {
            }
        }

        return false;
    }
}
