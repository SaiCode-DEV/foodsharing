<?php

namespace Foodsharing\Modules\Uploads;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Uploads\DTO\UploadedFile;
use Foodsharing\Modules\Uploads\Exceptions\Base64DecodingException;
use Foodsharing\Modules\Uploads\Exceptions\FileSizeTooBigException;
use Foodsharing\Modules\Uploads\Exceptions\InvalidFileException;
use Imagick;

class UploadsTransactions
{
    public function __construct(
        private readonly Session $session,
        private readonly UploadsGateway $uploadsGateway,
    ) {
    }

    /**
     * Returns the actual path of the file with the specified parameters.
     */
    public function generateFilePath(string $uuid, int $width = 0, int $height = 0, int $quality = 0): string
    {
        $filename = $uuid;

        if ($height && $width) {
            $filename .= '-' . $width . 'x' . $height;
        }
        if ($quality) {
            $filename .= '-q' . $quality;
        }

        return implode('/', [
            ROOT_DIR,
            'data/uploads',
            $uuid[0],
            $uuid[1] . $uuid[2],
            $filename
        ]);
    }

    public function isValidImage(string $file): bool
    {
        $img = new Imagick($file);

        return $img->valid();
    }

    public function stripImageExifData(string $input, string $output): void
    {
        $img = new Imagick($input);

        // rotate images according the EXIF rotation
        switch ($img->getImageOrientation()) {
            case Imagick::ORIENTATION_TOPLEFT:
                break;
            case Imagick::ORIENTATION_TOPRIGHT:
                $img->flopImage();
                break;
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $img->rotateImage('#000', 180);
                break;
            case Imagick::ORIENTATION_BOTTOMLEFT:
                $img->flopImage();
                $img->rotateImage('#000', 180);
                break;
            case Imagick::ORIENTATION_LEFTTOP:
                $img->flopImage();
                $img->rotateImage('#000', -90);
                break;
            case Imagick::ORIENTATION_RIGHTTOP:
                $img->rotateImage('#000', 90);
                break;
            case Imagick::ORIENTATION_RIGHTBOTTOM:
                $img->flopImage();
                $img->rotateImage('#000', 90);
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $img->rotateImage('#000', -90);
                break;
            default: // Invalid orientation
                break;
        }
        $img->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);

        // store ICC Profiles
        $profiles = $img->getImageProfiles('icc', true);

        // remove all EXIF DATA
        $img->stripImage();

        // restore ICC Profiles
        if (!empty($profiles)) {
            $img->profileImage('icc', $profiles['icc']);
        }

        // write image
        $img->writeImage($output);
    }

    /**
     * Resizes and crops an image to fit provided width and height.
     */
    public function resizeImage(string $input, string $output, int $width, int $height, int $quality): void
    {
        $img = new Imagick($input);

        $ratio = $width / $height;

        // Original image dimensions.
        $old_width = $img->getImageWidth();
        $old_height = $img->getImageHeight();
        $old_ratio = $old_width / $old_height;

        // Determine new image dimensions to scale to.
        // Also determine cropping coordinates.
        if ($ratio > $old_ratio) {
            $new_width = $width;
            $new_height = $width / $old_width * $old_height;
            $crop_x = 0;
            $crop_y = (int)(($new_height - $height) / 2);
        } else {
            $new_width = $height / $old_height * $old_width;
            $new_height = $height;
            $crop_x = (int)(($new_width - $width) / 2);
            $crop_y = 0;
        }
        $img->resizeImage($new_width, $new_height, Imagick::FILTER_LANCZOS, 0.9, true);
        $img->cropImage($width, $height, $crop_x, $crop_y);
        if ($quality) {
            $img->setImageCompressionQuality($quality);
        }
        $img->writeImage($output);
    }

    public function getImageMetadata(string $input): array
    {
        $img = new Imagick($input);

        return [
            'width' => $img->getImageWidth(),
            'height' => $img->getImageHeight(),
        ];
    }

    /**
     * This method generates a temporary file, before it can be saved in the database and on the non-temporary harddrive location.
     * It validates the image and remove exif data too.
     *
     * @return UploadedFile data of the temporary file
     *
     * @throws FileSizeTooBigException
     * @throws Base64DecodingException
     * @throws InvalidFileException
     */
    public function storeTemporaryValidatedFile(string $bodyBase64Encoded): UploadedFile
    {
        $maxBase64Size = 4 * (UploadAttributes::MAX_UPLOAD_FILE_SIZE / 3);
        if (strlen($bodyBase64Encoded) > $maxBase64Size) {
            throw new FileSizeTooBigException('file is bigger than ' . round(UploadAttributes::MAX_UPLOAD_FILE_SIZE / 1024 / 1024, 1) . ' MB');
        }

        $bodyDecoded = base64_decode($bodyBase64Encoded, true);
        if (!$bodyDecoded) {
            throw new Base64DecodingException('invalid body');
        }

        // generate & save temporary file
        $temporaryFile = tempnam(sys_get_temp_dir(), 'fs_upload');
        file_put_contents($temporaryFile, $bodyDecoded);

        $bodyHashOfTemporaryFile = hash_file('sha256', $temporaryFile);
        $sizeOfTemporaryFile = filesize($temporaryFile);
        $mimeTypeOfTemporaryFile = mime_content_type($temporaryFile);

        // image? check if its valid
        if (str_contains($mimeTypeOfTemporaryFile, 'image') && !$this->isValidImage($temporaryFile)) {
            unlink($temporaryFile);
            throw new InvalidFileException('invalid image provided');
        }

        return new UploadedFile(
            filePath: $temporaryFile,
            fileSize: $sizeOfTemporaryFile,
            hashedBody: $bodyHashOfTemporaryFile,
            mimeType: $mimeTypeOfTemporaryFile,
            uploaderId: $this->session->id(),
            usedIn: null,
            usageId: null
        );
    }

    public function moveTemporaryFileToPermanentLocation(
        string $temporaryFilePath,
        string $reservedFilePathForPersistentFile,
        string $temporaryFileMimeType
    ): void {
        $dir = dirname($reservedFilePathForPersistentFile);

        // create parent directories if they don't exist yet
        if (!file_exists($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException(sprintf('Directory "%s" was not created', $dir));
        }

        if (str_contains($temporaryFileMimeType, 'image/')) {
            $this->stripImageExifData($temporaryFilePath, $reservedFilePathForPersistentFile);
        } else {
            // otherwise just move it
            rename($temporaryFilePath, $reservedFilePathForPersistentFile);
        }
    }

    /**
     * Returns meta data of an uploaded file or null if the given UUID does not exist.
     */
    public function getUploadedFile(string $uuid): ?UploadedFile
    {
        $image = $this->uploadsGateway->getUploadedFile($uuid);
        if (empty($image)) {
            return null;
        }

        $image->filePath = $this->generateFilePath($uuid);

        return $image;
    }

    /**
     * Deletes all resized versions of the uploaded file with the specified UUID and removes the entry from the
     * database. After this, the UUID can be reused.
     */
    public function deleteUploadedFile(string $uuid): void
    {
        // Use a placeholder because there might be several resized versions of the original file
        foreach (glob($this->generateFilePath($uuid) . '*') as $file) {
            unlink($file);
        }
        $this->uploadsGateway->deleteUpload($uuid);
    }

    public function uploadFile(UploadedFile $file): array
    {
        $fileInfoFromDatabase = $this->uploadsGateway->addFile($file->uploaderId, $file->hashedBody, $file->fileSize, $file->mimeType);

        if (!$fileInfoFromDatabase['isReuploaded']) {
            $pathForPersistentFile = $this->generateFilePath($fileInfoFromDatabase['uuid']);
            $this->moveTemporaryFileToPermanentLocation($file->filePath, $pathForPersistentFile, $file->mimeType);
        }

        return $fileInfoFromDatabase;
    }
}
