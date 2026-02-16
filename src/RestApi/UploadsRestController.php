<?php

namespace Foodsharing\RestApi;

use Carbon\Carbon;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Core\DatabaseNoValueFoundException;
use Foodsharing\Modules\Uploads\Exceptions\Base64DecodingException;
use Foodsharing\Modules\Uploads\Exceptions\FileSizeTooBigException;
use Foodsharing\Modules\Uploads\Exceptions\InvalidFileException;
use Foodsharing\Modules\Uploads\UploadAttributes;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Foodsharing\Permissions\UploadsPermissions;
use Foodsharing\RestApi\Models\Upload\FileUpload;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Requirement\Requirement;

#[OA\Tag(name: 'upload')]
class UploadsRestController extends AbstractFoodsharingRestController
{
    private const int EXPIRATION_TIME_SECONDS = 86400 * 7; // one week

    public function __construct(
        private readonly UploadsGateway $uploadsGateway,
        private readonly UploadsTransactions $uploadsTransactions,
        private readonly UploadsPermissions $uploadsPermissions,
        protected Session $session,
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'Returns the image with the requested UUID. Width and height must both be given or can be set both to 0 to indicate no resizing.')]
    #[OA\QueryParameter(name: 'w', description: 'Max image width', required: false, schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\QueryParameter(name: 'h', description: 'Max image height', required: false, schema: new OA\Schema(type: 'integer', default: 0))]
    #[OA\QueryParameter(name: 'q', description: 'Image quality (between 1 and 100)', required: false, schema: new OA\Schema(type: 'integer', default: null, maximum: 100, minimum: 1))]
    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Tried resizing for a non-image file')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'Insufficient permissions')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'UUID does not exist')]
    #[Route('uploads/{uuid}', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    public function getImage(string $uuid, #[MapQueryParameter] int $w = 0, #[MapQueryParameter] int $h = 0, #[MapQueryParameter] ?int $q = null): Response
    {
        $doResize = $h || $w;

        $this->validateParameters($h, $w, $q, $doResize);

        $file = $this->uploadsGateway->getUploadedFile($uuid);
        if (is_null($file)) {
            throw new NotFoundHttpException('file not found');
        }

        if (!$this->uploadsPermissions->mayAccessUpload($file)) {
            throw new AccessDeniedHttpException('not allowed to download this file');
        }

        $filename = $this->uploadsTransactions->generateFilePath($uuid);

        // resizing of images
        if ($doResize) {
            if (!str_starts_with($file->mimeType, 'image/')) {
                throw new BadRequestHttpException('resizing only possible with images');
            }

            $q ??= UploadAttributes::DEFAULT_QUALITY;

            $originalFilename = $filename;
            $filename = $this->uploadsTransactions->generateFilePath($uuid, $w, $h, $q);

            if (!file_exists($filename)) {
                $this->uploadsTransactions->resizeImage($originalFilename, $filename, $w, $h, $q);
            }
        }

        $response = new BinaryFileResponse($filename);
        $response->setPublic();
        $response->setMaxAge(self::EXPIRATION_TIME_SECONDS);
        $response->setExpires(Carbon::now()->addSeconds(self::EXPIRATION_TIME_SECONDS));
        $response->setLastModified(Carbon::now());

        $mime = explode('/', $file->mimeType);
        match ($mime[0]) {
            'video', 'audio', 'image' => $response->headers->set('Content-Type', $file->mimeType),
            'text' => $response->headers->set('Content-Type', 'text/plain'),
            default => $response->headers->set('Content-Type', 'application/octet-stream'),
        };

        return $response;
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Tried resizing for a non-image file')]
    #[OA\Response(response: Response::HTTP_NOT_FOUND, description: 'UUID does not exist')]
    #[Route('uploads/{uuid}/metadata', requirements: ['uuid' => Requirement::UUID], methods: ['GET'])]
    public function getImageMetadata(string $uuid): Response
    {
        try {
            $mimetype = $this->uploadsGateway->getMimeType($uuid);
        } catch (DatabaseNoValueFoundException) {
            throw new NotFoundHttpException('file not found');
        }
        if (!str_starts_with($mimetype, 'image/')) {
            throw new BadRequestHttpException('Dimensions only fetchable for images');
        }

        $filename = $this->uploadsTransactions->generateFilePath($uuid);

        if (!file_exists($filename)) {
            throw new NotFoundHttpException('file not found');
        }
        $result = $this->uploadsTransactions->getImageMetadata($filename);

        return $this->respondOK($result);
    }

    #[OA\Response(response: Response::HTTP_OK, description: 'Success')]
    #[OA\Response(response: Response::HTTP_BAD_REQUEST, description: 'Invalid data provided')]
    #[OA\Response(response: Response::HTTP_UNAUTHORIZED, description: 'Not logged in')]
    #[OA\Response(response: Response::HTTP_FORBIDDEN, description: 'MIME type not allowed')]
    #[Route('uploads', methods: ['POST'])]
    public function uploadFile(#[MapRequestPayload] FileUpload $file, Request $request, RateLimiterFactory $loginLimiter): Response
    {
        $this->checkRateLimit($request, $loginLimiter);

        $this->assertLoggedIn();

        try {
            $temporaryFile = $this->uploadsTransactions->storeTemporaryValidatedFile($file->body);
        } catch (Base64DecodingException|FileSizeTooBigException|InvalidFileException $error) {
            throw new BadRequestHttpException($error->getMessage());
        }

        if (empty($temporaryFile->mimeType) || !$this->isMimeTypeAllowed($temporaryFile->mimeType)) {
            throw new AccessDeniedHttpException('MIME type could not be determined or is not allowed');
        }

        $uuid = $this->uploadsTransactions->uploadFile($temporaryFile);

        return $this->respondOK([
            'url' => '/api/uploads/' . $uuid,
            'uuid' => $uuid,
            'filename' => $file->filename,
            'mimeType' => $temporaryFile->mimeType,
            'filesize' => $temporaryFile->fileSize,
        ]);
    }

    /**
     * The method validates the input parameters.
     */
    private function validateParameters(int $height, int $width, ?int $quality, bool $doResize): void
    {
        if ($height && $height < UploadAttributes::MIN_WIDTH_AND_HEIGHT) {
            throw new BadRequestHttpException('minium height is ' . UploadAttributes::MIN_WIDTH_AND_HEIGHT . ' pixel');
        }
        if ($height && $height > UploadAttributes::MAX_HEIGHT) {
            throw new BadRequestHttpException('maximum height is ' . UploadAttributes::MAX_HEIGHT . ' pixel');
        }
        if ($width && $width < UploadAttributes::MIN_WIDTH_AND_HEIGHT) {
            throw new BadRequestHttpException('minium width is ' . UploadAttributes::MIN_WIDTH_AND_HEIGHT . ' pixel');
        }
        if ($width && $width > UploadAttributes::MAX_WIDTH) {
            throw new BadRequestHttpException('maximum width is ' . UploadAttributes::MAX_WIDTH . ' pixel');
        }

        if (($height && !$width) || ($width && !$height)) {
            throw new BadRequestHttpException('resizing requires both, height and width');
        }

        if (!is_null($quality) && !$doResize) {
            throw new BadRequestHttpException('quality parameter only allowed while resizing');
        }
        if (!is_null($quality) && ($quality < UploadAttributes::MIN_QUALITY || $quality > UploadAttributes::MAX_QUALITY)) {
            throw new BadRequestHttpException('quality needs to be between ' . UploadAttributes::MIN_QUALITY . ' and ' . UploadAttributes::MAX_QUALITY);
        }
    }

    /**
     * Whitelist of allowed mime types. The same list exists in the frontend in consts.js. If you change something,
     * please also adjust that list.
     */
    private function isMimeTypeAllowed(string $mimeType): bool
    {
        $acceptedFileTypes = [
            // Image files
            'image/*',

            // Documents
            'application/pdf', 'text/plain', 'application/rtf',

            // Audio and Video files
            'audio/*', 'video/*',

            // Compressed archives
            'application/zip', 'application/gzip', 'application/x-7z-compressed', 'application/x-tar',
            'application/x-bzip2', 'application/x-xz',

            // Data formats
            'text/csv', 'application/json', 'application/xml', 'text/xml', 'application/x-yaml',

            // Microsoft Office files
            'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',

            // OpenDocument files
            'application/vnd.oasis.opendocument.text', 'application/vnd.oasis.opendocument.spreadsheet',
            'application/vnd.oasis.opendocument.presentation'
        ];
        foreach ($acceptedFileTypes as $pattern) {
            $p = str_replace('/', '\\/', $pattern);
            if (preg_match("/$p/", $mimeType)) {
                return true;
            }
        }

        return false;
    }
}
