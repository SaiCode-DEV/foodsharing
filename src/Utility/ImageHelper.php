<?php

namespace Foodsharing\Utility;

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
            // path for pictures uploaded with the new API
            if (is_numeric($size)) {
                $file .= '?w=' . $size . '&h=' . $size;
            } elseif ($size === 'mini') {
                $file .= '?w=35&h=35';
            }

            return '/api/uploads/' . $file;
        }

        if ($altimg === false) {
            return '/img/' . $size . '_' . $format . '_avatar.png';
        }

        return $altimg;
    }
}
