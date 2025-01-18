<?php

namespace Foodsharing\Modules\PassportGenerator;

use Carbon\Carbon;
use DateTime;
use Exception;
use Foodsharing\Lib\AppleWalletPass;
use Foodsharing\Lib\GoogleWalletPass;
use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Region\RegionGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Foodsharing\RestApi\Models\Passport\CreateRegionPassportModel;
use Foodsharing\Utility\EmailHelper;
use Foodsharing\Utility\FlashMessageHelper;
use Foodsharing\Utility\TimeHelper;
use Foodsharing\Utility\TranslationHelper;
use setasign\Fpdi\Tcpdf\Fpdi;
use stdClass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PassportGeneratorTransaction
{
    private const int PASSPORT_VALIDITY_YEARS = 3;

    public function __construct(
        private readonly RegionGateway $regionGateway,
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly PassportGeneratorGateway $passportGeneratorGateway,
        private readonly Session $session,
        private readonly UploadsTransactions $uploadsTransactions,
        protected FlashMessageHelper $flashMessageHelper,
        protected TranslationHelper $translationHelper,
        protected TranslatorInterface $translator,
        private readonly UrlGeneratorInterface $router,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
        private readonly GoogleWalletPass $googleWalletPass,
        private readonly AppleWalletPass $appleWalletPass,
        private readonly TimeHelper $timeHelper,
        private readonly BellGateway $bellGateway,
        private readonly EmailHelper $emailHelper,
    ) {
    }

    private function setupPdfMargins(\TCPDF $pdf, array $userIds, bool $usePaperSizeDinA4): array
    {
        $singleUser = !$usePaperSizeDinA4 && count($userIds) === 1;

        $pdf->AddPage(
            $singleUser ? 'L' : 'P',
            $singleUser ? [53.3, 83] : 'A4'
        );

        $pdf->SetAutoPageBreak(false, 0);
        $pdf->SetMargins(0, 0, 0, true);

        return [
            'backgroundMarginX' => $singleUser ? 0 : 10,
            'backgroundMarginY' => $singleUser ? 0 : 10,
            'cellMarginX' => 40,
            'cellMarginY' => $singleUser ? 3.2 : 13.2,
            'idLabelMarginX' => $singleUser ? 40 : 50,
            'idLabelMarginY' => 5,
            'logoMarginX' => $singleUser ? 3.5 : 13.5,
            'logoMarginY' => $singleUser ? 3.6 : 13.6,
            'photoMarginX' => $singleUser ? 4 : 14,
            'photoMarginY' => $singleUser ? 19.7 : 31,
            'nameMaxWidthMarginX' => $singleUser ? 31 : 41,
            'nameMaxWidthMarginY' => $singleUser ? 20 : 30,
            'nameLabelMarginX' => $singleUser ? 31 : 41,
            'nameLabelMarginY' => $singleUser ? 20 : 28,
            'nameMarginX' => $singleUser ? 31 : 41,
            'nameMarginY' => $singleUser ? 22 : 30.2,
            'roleLabelMarginX' => $singleUser ? 31 : 41,
            'roleLabelMarginY' => $singleUser ? 27 : 37,
            'roleMarginX' => $singleUser ? 31 : 41,
            'roleMarginY' => $singleUser ? 29 : 39,
            'validTillLabelMarginX' => $singleUser ? 31 : 41,
            'validTillLabelMarginY' => $singleUser ? 45 : 55,
            'validTillMarginX' => $singleUser ? 31 : 41,
            'validTillMarginY' => $singleUser ? 47 : 57,
            'validDownLabelMarginX' => $singleUser ? 31 : 41,
            'validDownLabelMarginY' => $singleUser ? 36 : 46,
            'validDownMarginX' => $singleUser ? 31 : 41,
            'validDownMarginY' => $singleUser ? 38 : 48,
            'qrCodeMarginX' => $singleUser ? 60 : 70.5,
            'qrCodeMarginY' => $singleUser ? 33 : 43,
        ];
    }

    private function getProfileUrl(int $userId): string
    {
        return $this->router->generate('user_profile', ['userId' => $userId], UrlGeneratorInterface::ABSOLUTE_URL);
    }

    private function addUserPhotoToPdf(\TCPDF $pdf, int $userId, float $x, float $y, array $margins): void
    {
        $photo = $this->foodsaverGateway->getPhotoFileName($userId);
        if (!$photo) {
            return;
        }

        $imagePath = null;
        $imageWidth = null;

        if (str_starts_with($photo, '/api/uploads')) {
            $uuid = substr($photo, strlen('/api/uploads/'));
            $filename = $this->uploadsTransactions->generateFilePath($uuid, 200, 257, 0);

            if (!file_exists($filename)) {
                $originalFilename = $this->uploadsTransactions->generateFilePath($uuid);
                $this->uploadsTransactions->resizeImage($originalFilename, $filename, 200, 257, 0);
            }

            $imagePath = $filename;
            $imageWidth = 24;
        } else {
            $croppedImagePath = 'images/crop_' . $photo;
            $originalImagePath = 'images/' . $photo;

            if (file_exists($croppedImagePath)) {
                $imagePath = $croppedImagePath;
                $imageWidth = 24;
            } elseif (file_exists($originalImagePath)) {
                $imagePath = $originalImagePath;
                $imageWidth = 22;
            }
        }

        if ($imagePath) {
            $pdf->Image(
                $imagePath,
                $margins['photoMarginX'] + $x,
                $margins['photoMarginY'] + $y,
                $imageWidth
            );
        }
    }

    private function generatePdf(array $userIds, bool $ambassadorGeneration, bool $usePaperSizeDinA4, DateTime $validFrom, DateTime $validUntil): stdClass
    {
        $protectPDF = !$ambassadorGeneration;
        $cutMarkers = $ambassadorGeneration;

        $fontFamily = 'Ubuntu-L';
        $fontStyle = '';
        $initialFontSize = 10;
        $minimumFontSize = 8;
        $maxWidth = 49;
        $card = 0;

        $pdf = new Fpdi();

        if ($protectPDF) {
            $pdf->SetProtection(['print', 'copy', 'modify', 'assemble'], '', null, 0, null);
        }

        $margins = $this->setupPdfMargins($pdf, $userIds, $usePaperSizeDinA4);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->AddFont('Ubuntu-L', '', $this->projectDir . '/lib/font/ubuntul.php', true);

        end($userIds);

        $pdf->setSourceFile($this->projectDir . '/img/foodsharing_logo.pdf');
        $fs_logo = $pdf->importPage(1);
        $pdfGeneratedUser = [];

        foreach ($userIds as $userId) {
            if ($user = $this->foodsaverGateway->getFoodsaverDetails($userId)) {
                $pdf->SetTextColor(0, 0, 0);

                ++$card;
                $cardOnPage = ($card - 1) % 8;
                $column = $cardOnPage % 2;
                $row = floor($cardOnPage / 2);

                $x = $column * 95;
                $y = $row * 65;

                $backgroundFile = $this->projectDir . '/img/pass_bg' . ($cutMarkers ? '' : '_cut') . '.png';

                $pdf->Image($backgroundFile, $margins['backgroundMarginX'] + $x, $margins['backgroundMarginY'] + $y, 83, 55);

                $name = $user['name'] . ' ' . $user['nachname'];
                $nameX = $margins['nameMarginX'] + $x;
                $nameY = $margins['nameMarginY'] + $y - 0.2;

                $fullNameWidth = $pdf->GetStringWidth($name);
                $maxFontSize = min($maxWidth / $fullNameWidth * $initialFontSize, $initialFontSize);

                if ($maxFontSize >= $minimumFontSize) {
                    $pdf->SetFont($fontFamily, $fontStyle, $maxFontSize);
                    $pdf->Text($nameX, $nameY, $name);
                } else {
                    $firstNameWidth = $pdf->GetStringWidth($user['name']);
                    $lastNameWidth = $pdf->GetStringWidth($user['nachname']);

                    $firstNameFontSize = min($maxWidth / $firstNameWidth * $initialFontSize, $initialFontSize);
                    $lastNameFontSize = min($maxWidth / $lastNameWidth * $initialFontSize, $initialFontSize);

                    $fontSize = min($firstNameFontSize, $lastNameFontSize);
                    $fontSize = max($fontSize, $minimumFontSize);

                    $pdf->SetFont($fontFamily, $fontStyle, $fontSize);

                    $lineHeight = $pdf->getStringHeight(0, $user['name']) * 0.7;

                    $pdf->Text($nameX, $nameY, $user['name']);
                    $pdf->Text($nameX, $nameY + $lineHeight, $user['nachname']);
                }

                $pdf->SetFont($fontFamily, $fontStyle, 10);
                $pdf->Text($margins['validDownMarginX'] + $x, $margins['validDownMarginY'] + $y, $validFrom->format('d.m.Y'));
                $pdf->Text($margins['validTillMarginX'] + $x, $margins['validTillMarginY'] + $y, $validUntil->format('d.m.Y'));

                $pdf->SetFont($fontFamily, $fontStyle, 6);
                // ToDo: Add translation keys
                $pdf->Text($margins['nameLabelMarginX'] + $x, $margins['nameLabelMarginY'] + $y, 'Name');
                $pdf->Text($margins['validDownLabelMarginX'] + $x, $margins['validDownLabelMarginY'] + $y, 'Gültig ab');
                $pdf->Text($margins['validTillLabelMarginX'] + $x, $margins['validTillLabelMarginY'] + $y, 'Gültig bis');

                $pdf->SetFont($fontFamily, $fontStyle, 9);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetXY($margins['cellMarginX'] + $x, $margins['cellMarginY'] + $y);
                $pdf->Cell($margins['idLabelMarginX'], $margins['idLabelMarginY'], 'ID ' . $userId, 0, 0, 'R');

                $pdf->useTemplate($fs_logo, $margins['logoMarginX'] + $x, $margins['logoMarginY'] + $y, 29.8);

                $style = [
                    'vpadding' => 'auto',
                    'hpadding' => 'auto',
                    'fgcolor' => [0, 0, 0],
                    'bgcolor' => false, // array(255,255,255)
                    'module_width' => 1, // width of a single module in points
                    'module_height' => 1 // height of a single module in points
                ];

                $pdf->write2DBarcode($this->getProfileUrl($userId), 'QRCODE,H', $margins['qrCodeMarginX'] + $x, $margins['qrCodeMarginY'] + $y, 20, 20, $style, 'N', true);

                $this->addUserPhotoToPdf($pdf, $userId, $x, $y, $margins);

                if ($cardOnPage == 7) {
                    $pdf->AddPage();
                }

                $pdfGeneratedUser[] = $user['id'];
            }
        }

        $result = new stdClass();
        $result->pdf = $pdf;
        $result->pdfGeneratedUserIds = $pdfGeneratedUser;

        return $result;
    }

    /**
     * @throws Exception
     */
    public function generatePassportAsUser(int $userId): string
    {
        $lastPassDate = $this->passportGeneratorGateway->getFoodsaverLastPassDate($userId);

        if (empty($lastPassDate) || !$this->isPassportValid($lastPassDate)) {
            throw new Exception('passport is not valid');
        }
        $validFrom = Carbon::parse($lastPassDate);
        $validUntil = $this->getPassportValidityEnd($validFrom);

        $result = $this->generatePdf([$userId], false, true, $validFrom, $validUntil);

        return $result->pdf->Output('', 'S');
    }

    private function isPassportValid(DateTime $lastPassDate): bool
    {
        $date = $this->getPassportValidityEnd($lastPassDate);

        return $this->timeHelper->daysInFuture($date) >= 1;
    }

    /**
     * Returns the end of the validity of the passport which was created at the given date.
     */
    public function getPassportValidityEnd(DateTime $creationDate): DateTime
    {
        return Carbon::instance($creationDate)->addYears(self::PASSPORT_VALIDITY_YEARS)->toDateTime();
    }

    public function generatePassportAsAmbassador(CreateRegionPassportModel $regionPassportModel): mixed
    {
        $result = new stdClass();
        $generatedUserId = $this->session->id();

        if ($regionPassportModel->createPdf) {
            $validFrom = Carbon::today();
            $validUntil = $this->getPassportValidityEnd($validFrom);
            $result = $this->generatePdf($regionPassportModel->userIds, true, $regionPassportModel->usePaperSizeDinA4, $validFrom, $validUntil);
        }

        $userIds = $result->pdfGeneratedUserIds ?? $regionPassportModel->userIds;
        if ($regionPassportModel->renew) {
            $this->passportGeneratorGateway->logPassGeneration($generatedUserId, $userIds);
            $this->passportGeneratorGateway->updateFoodsaverLastPassDate($userIds);
            if (!empty(GOOGLE_WALLET_ISSUER_ID)) {
                $this->updateGoogleWallet($userIds);
            }
            if ($regionPassportModel->informUser) {
                $this->addBellAndSendPassportMail($userIds);
            }
        }

        return $regionPassportModel->createPdf ? $result->pdf->Output('', 'S') : json_encode(['userIds' => $regionPassportModel->userIds]);
    }

    private function addBellAndSendPassportMail(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $passportGenLink = '/user/current/settings?sub=passport';
            $bellData = Bell::create(
                'passport_created_or_renewed_title',
                'passport_created_or_renewed',
                'fas fa-id-card',
                ['href' => $passportGenLink],
                ['user' => $this->session->user('name')],
                BellType::createIdentifier(BellType::PASS_CREATED_OR_RENEWED, $userId)
            );
            $this->bellGateway->addBell($userId, $bellData);

            $passportMailLink = 'https://foodsharing.de' . $passportGenLink;
            $fs = $this->foodsaverGateway->getFoodsaver($userId);
            $this->emailHelper->tplMail('user/passport', $fs['email'], [
                'name' => $fs['name'],
                'link' => $passportMailLink,
                'anrede' => $this->translator->trans('salutation.' . $fs['geschlecht']),
            ], false, true);
        }
    }

    private function updateGoogleWallet(array $userIds): void
    {
        foreach ($userIds as $userId) {
            $this->googleWalletPass->renewObject($userId);
        }
    }

    public function areUsersInRegion(array $userIds, int $regionId): object
    {
        $result = true;
        $missingUserIds = [];

        foreach ($userIds as $userId) {
            if (!$this->regionGateway->hasMember($userId, $regionId)) {
                $result = false;
                $missingUserIds[] = $userId;
            }
        }

        return (object)[
            'result' => $result,
            'message' => $result ? '' : 'The following user IDs are not included in the region: ' . implode(', ', $missingUserIds)
        ];
    }

    public function createWallet(int $userId, string $walletType): string
    {
        $name = $this->session->user('name') . ' ' . $this->session->user('nachname');
        $passDate = $this->passportGeneratorGateway->getFoodsaverLastPassDate($userId);
        if (is_null($passDate)) {
            throw new \InvalidArgumentException('No pass was created for the user');
        }
        $profileURL = $this->router->generate('user_profile', ['userId' => $userId], UrlGeneratorInterface::ABSOLUTE_URL);
        $photo = $this->session->user('photo');
        if (!$photo) {
            throw new \InvalidArgumentException('No photo found for user');
        }
        switch ($walletType) {
            case 'apple':
                $photo_uuid = substr((string)$photo, strlen('/api/uploads/'));
                $photoFileName = $this->uploadsTransactions->generateFilePath($photo_uuid);
                $result = $this->appleWalletPass->createNewPass($userId, $name, $profileURL, $photoFileName, $passDate);
                break;
            case 'google':
                $userPhoto = BASE_URL . $photo;
                if (getenv('FS_ENV') === 'dev') {
                    $userPhoto = 'https://foodsharing.de/img/50_q_avatar.png';
                }
                $result = $this->googleWalletPass->createNewPassJwt($userId, $name, $profileURL, $userPhoto, $passDate);
                break;
            default:
                throw new \InvalidArgumentException("Ungültiger Wallet-Typ: $walletType");
        }

        return $result;
    }
}
