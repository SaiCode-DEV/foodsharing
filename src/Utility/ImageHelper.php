<?php

namespace Foodsharing\Utility;

use Exception;
use Flourish\fImage;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use UnexpectedValueException;

final class ImageHelper
{
    private array $extensions = ['image/gif' => 'gif', 'image/jpeg' => 'jpg', 'image/png' => 'png'];

    /**
     * Guesses a filename extension for a file.
     *
     * @param string $file the file
     *
     * @return string the guessed extension
     *
     * @throws UnexpectedValueException if the file does not exist or has an
     *                                  unknown image type
     */
    public function guessImageFileExtension(string $file): string
    {
        if (empty($file) || !file_exists($file)) {
            throw new UnexpectedValueException('File not found');
        }

        $fileInfo = finfo_open();
        $mime = finfo_file($fileInfo, $file, FILEINFO_MIME_TYPE);
        finfo_close($fileInfo);

        if ($mime !== null && isset($this->extensions[$mime])) {
            return $this->extensions[$mime];
        }

        throw new UnexpectedValueException('Unknown image type');
    }

    /**
     * Creates a copy of the file in the destination directory with a unique
     * name and creates rescaled versions of it.
     *
     * @param string $file the original file
     * @param string $dstDir destination directory
     * @param array $sizes key-value-pairs of size (int) and prefix (string)
     *
     * @return string the base name for the created files or null if the
     *                     original file does not exist or rescaling failed
     *
     * @throws UnexpectedValueException if the file does not exist or has an
     *                                  unknown image type
     * @throws Exception if an error occurs while resizing the image
     */
    public function createResizedPictures(string $file, string $dstDir, array $sizes): string
    {
        $extension = $this->guessImageFileExtension($file);
        $name = uniqid('', true) . '.' . strtolower($extension);

        try {
            foreach ($sizes as $s => $p) {
                $dst = $dstDir . $p . $name;
                copy($file, $dst);
                $img = new fImage($dst);
                $img->resize($s, $s);
                $img->saveChanges();
            }
        } catch (Exception $e) {
            // in case of an error remove all created files
            $this->removeResizedPictures($dstDir, $name, $sizes);
            throw $e;
        }

        return $name;
    }

    /**
     * Removes all rescaled versions of the picture with the given name
     * and prefixes in the directory.
     *
     * @param string $dir the directory
     * @param string $name the base name
     * @param array $sizes key-value-pairs of size (int) and prefix (string)
     */
    public function removeResizedPictures(string $dir, string $name, array $sizes): void
    {
        foreach (array_values($sizes) as $p) {
            if (file_exists($dir . $p . $name)) {
                unlink($dir . $p . $name);
            }
        }
    }

    public function img($file = false, $size = 'mini', $format = 'q', $altimg = false)
    {
        if ($file === false) {
            $file = $_SESSION['client']['photo'];
        }

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

    public function avatar($foodsaver, $size = 'mini', $altimg = false): string
    {
        /*
         * temporary for quiz
         */
        $bg = '';
        if (isset($foodsaver['quiz_rolle'])) {
            switch ($foodsaver['quiz_rolle']) {
                case Role::FOODSAVER:
                    $bg = 'box-sizing:border-box;border:3px solid var(--fs-color-role-foodsaver);';
                    break;
                case Role::STORE_MANAGER:
                    $bg = 'box-sizing:border-box;border:3px solid var(--fs-color-role-storemanager);';
                    break;
                case Role::AMBASSADOR:
                    $bg = 'box-sizing:border-box;border:3px solid var(--fs-color-role-ambassador);';
                    break;
                default:
                    break;
            }
        }

        return '<span style="' . $bg . 'background-image:url(' . $this->img($foodsaver['photo'], $size, 'q', $altimg) . ');" class="avatar size-' . $size . ' sleepmode-' . $foodsaver['sleep_status'] . '"><i>' . $foodsaver['name'] . '</i></span>';
    }
}
