<?php

namespace Foodsharing\RestApi;

use Exception;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Uploads\Exceptions\Base64DecodingException;
use Foodsharing\Modules\Uploads\Exceptions\FileSizeTooBigException;
use Foodsharing\Modules\Uploads\Exceptions\InvalidFileException;
use Foodsharing\Modules\Uploads\UploadAttributes;
use Foodsharing\Modules\Uploads\UploadsGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Foodsharing\Permissions\UploadsPermissions;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcher;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

#[OA\Tag(name: 'upload')]
class UploadsRestController extends AbstractFoodsharingRestController
{
    private const EXPIRATION_TIME_SECONDS = 86400 * 7; // one week

    public function __construct(
        private readonly UploadsGateway $uploadsGateway,
        private readonly UploadsTransactions $uploadsTransactions,
        private readonly UploadsPermissions $uploadsPermissions,
        protected Session $session,
    ) {
        parent::__construct($this->session);
    }

    #[OA\Get(summary: 'Returns the image with the requested UUID. Width and height must both be given or can be set both to 0 to indicate no resizing.')]
    #[Rest\Get('uploads/{uuid}', requirements: ['uuid' => '[0-9a-f\-]+'])]
    #[Rest\QueryParam(name: 'w', requirements: '\d+', default: 0, description: 'Max image width')]
    #[Rest\QueryParam(name: 'h', requirements: '\d+', default: 0, description: 'Max image height')]
    #[Rest\QueryParam(name: 'q', requirements: '\d+', default: 0, description: 'Image quality (between 1 and 100)')]
    public function getImage(string $uuid, ParamFetcher $paramFetcher): void
    {
        $width = $paramFetcher->get('w');
        $height = $paramFetcher->get('h');
        $quality = $paramFetcher->get('q');
        $doResize = $height || $width;

        $this->validateParameters($height, $width, $quality, $doResize);

        $file = $this->uploadsGateway->getUploadedFile($uuid);
        if (is_null($file)) {
            throw new NotFoundHttpException('file not found');
        }

        if (!$this->uploadsPermissions->mayAccessUpload($file)) {
            throw new AccessDeniedHttpException('not allowed to download this file');
        }

        // update lastAccess timestamp
        $this->uploadsGateway->touchFile($uuid);

        $filename = $this->uploadsTransactions->generateFilePath($uuid);

        // resizing of images
        if ($doResize) {
            if (!str_starts_with($file->mimeType, 'image/')) {
                throw new BadRequestHttpException('resizing only possible with images');
            }

            if (!$quality) {
                $quality = UploadAttributes::DEFAULT_QUALITY;
            }

            $originalFilename = $filename;
            $filename = $this->uploadsTransactions->generateFilePath($uuid, $width, $height, $quality);

            if (!file_exists($filename)) {
                $this->uploadsTransactions->resizeImage($originalFilename, $filename, $width, $height, $quality);
            }
        }

        // write response
        header('Pragma: public');
        header('Cache-Control: max-age=' . self::EXPIRATION_TIME_SECONDS);
        header('Expires: ' . gmdate('D, d M Y H:i:s \G\M\T', time() + self::EXPIRATION_TIME_SECONDS));
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');

        $mime = explode('/', $file->mimeType);
        match ($mime[0]) {
            'video', 'audio', 'image' => header('Content-Type: ' . $file->mimeType),
            'text' => header('Content-Type: text/plain'),
            default => header('Content-Type: application/octet-stream'),
        };
        readfile($filename);
        exit;
    }

    #[Rest\Get('uploads/{uuid}/metadata', requirements: ['uuid' => '[0-9a-f\-]+'])]
    public function getImageMetadata(string $uuid): Response
    {
        try {
            $mimetype = $this->uploadsGateway->getMimeType($uuid);
        } catch (Exception) {
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

        return $this->handleView($this->view($result, Response::HTTP_OK));
    }

    #[Rest\Post('uploads')]
    #[Rest\RequestParam(name: 'filename')]
    #[Rest\RequestParam(name: 'body')]
    public function uploadImage(ParamFetcher $paramFetcher, Request $request, RateLimiterFactory $loginLimiter): Response
    {
        $this->checkRateLimit($request, $loginLimiter);

        if (!$this->session->id()) {
            throw new UnauthorizedHttpException('');
        }

        $fileName = $paramFetcher->get('filename');
        $bodyEncoded = $paramFetcher->get('body');

        // check uploaded body
        if (!$fileName) {
            throw new BadRequestHttpException('no filename provided');
        }
        if (!$bodyEncoded) {
            throw new BadRequestHttpException('no body provided');
        }

        try {
            $temporaryFile = $this->uploadsTransactions->storeTemporaryValidatedFile($bodyEncoded);
        } catch (Base64DecodingException|FileSizeTooBigException|InvalidFileException $error) {
            throw new BadRequestHttpException($error->getMessage());
        }

        $fileInfoFromDatabase = $this->uploadsTransactions->uploadFile($temporaryFile);

        $view = $this->view([
            'url' => '/api/uploads/' . $fileInfoFromDatabase['uuid'],
            'uuid' => $fileInfoFromDatabase['uuid'],
            'filename' => $fileName,
            'mimeType' => $temporaryFile->mimeType,
            'filesize' => $temporaryFile->fileSize,
        ], 200);

        return $this->handleView($view);
    }

    /**
     * The method validates the input parameters.
     */
    private function validateParameters(int $height, int $width, int $quality, bool $doResize): void
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

        if ($quality && !$doResize) {
            throw new BadRequestHttpException('quality parameter only allowed while resizing');
        }
        if ($quality && ($quality < UploadAttributes::MIN_QUALITY || $quality > UploadAttributes::MAX_QUALITY)) {
            throw new BadRequestHttpException('quality needs to be between ' . UploadAttributes::MIN_QUALITY . ' and ' . UploadAttributes::MAX_QUALITY);
        }
    }
}
