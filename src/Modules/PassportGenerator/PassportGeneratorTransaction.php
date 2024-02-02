<?php

namespace Foodsharing\Modules\PassportGenerator;

use Foodsharing\Lib\Session;
use Foodsharing\Modules\Bell\BellGateway;
use Foodsharing\Modules\Bell\DTO\Bell;
use Foodsharing\Modules\Core\DBConstants\Bell\BellType;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Gender;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use Foodsharing\Modules\Foodsaver\FoodsaverGateway;
use Foodsharing\Modules\Profile\ProfileGateway;
use Foodsharing\Modules\Uploads\UploadsTransactions;
use Foodsharing\Utility\FlashMessageHelper;
use Foodsharing\Utility\TranslationHelper;
use setasign\Fpdi\Tcpdf\Fpdi;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PassportGeneratorTransaction extends AbstractController
{
    private readonly string $projectDir;

    public function __construct(
        private readonly FoodsaverGateway $foodsaverGateway,
        private readonly PassportGeneratorGateway $passportGeneratorGateway,
        private readonly ProfileGateway $profileGateway,
        private readonly Session $session,
        private readonly UploadsTransactions $uploadsTransactions,
        private readonly BellGateway $bellGateway,
        protected FlashMessageHelper $flashMessageHelper,
        protected TranslationHelper $translationHelper,
        protected TranslatorInterface $translator,
        KernelInterface $kernel,
    ) {
        $this->projectDir = $kernel->getProjectDir();
    }

    public function generate(array $foodsavers, ?\DateTime $passDate = null, bool $cutMarkers = true, bool $protectPDF = false, bool $ambassadorGeneration = false, bool $oldGeneration = false): string
    {
        $tmp = [];
        foreach ($foodsavers as $foodsaver) {
            $tmp[$foodsaver] = (int)$foodsaver;
        }
        $foodsavers = $tmp;
        $is_generated = [];

        $pdf = new Fpdi();

        if ($protectPDF) {
            $pdf->SetProtection(['print', 'copy', 'modify', 'assemble'], '', null, 0, null);
        }

        $generationUntilDate = '+3 years';
        if ($ambassadorGeneration) {
            $untilFrom = (new \DateTime())->format('d. m. Y');
            $validUntil = (new \DateTime())->modify($generationUntilDate)->format('d. m. Y');
        } else {
            $untilFrom = $passDate->format('d. m. Y');
            $validUntil = $passDate->modify($generationUntilDate)->format('d. m. Y');
        }

        if (count($tmp) === 1) {
            $pdf->AddPage('L', [53.3, 83]);
            $pdf->SetAutoPageBreak(false, 0);
            $pdf->SetMargins(0, 0, 0, true);
            $backgroundMarginX = 0;
            $backgroundMarginY = 0;
            $cellMarginX = 40;
            $cellMarginY = 3.2;
            $idLabelMarginX = 40;
            $idLabelMarginY = 5;
            $logoMarginX = 3.5;
            $logoMarginY = 3.6;
            $photoMarginX = 4;
            $photoMarginY = 19.7;
            $nameMaxWidthMarginX = 31;
            $nameMaxWidthMarginY = 20;
            $nameLabelMarginX = 31;
            $nameLabelMarginY = 20;
            $nameMarginX = 31;
            $nameMarginY = 22;
            $roleLabelMarginX = 31;
            $roleLabelMarginY = 27;
            $roleMarginX = 31;
            $roleMarginY = 29;
            $validTillLabelMarginX = 31;
            $validTillLabelMarginY = 45;
            $validDownLabelMarginX = 31;
            $validDownLabelMarginY = 36;
            $validDownMarginX = 31;
            $validDownMarginY = 38;
            $validTillMarginX = 31;
            $validTillMarginY = 47;
            $qrCodeMarginX = 60;
            $qrCodeMarginY = 33;
        } else {
            $pdf->AddPage();
            $backgroundMarginX = 10;
            $backgroundMarginY = 10;
            $cellMarginX = 40;
            $cellMarginY = 13.2;
            $idLabelMarginX = 50;
            $idLabelMarginY = 5;
            $logoMarginX = 13.5;
            $logoMarginY = 13.6;
            $photoMarginX = 14;
            $photoMarginY = 31;
            $nameMaxWidthMarginX = 41;
            $nameMaxWidthMarginY = 30;
            $nameLabelMarginX = 41;
            $nameLabelMarginY = 28;
            $roleLabelMarginX = 41;
            $roleLabelMarginY = 37;
            $roleMarginX = 41;
            $roleMarginY = 39;
            $nameMarginX = 41;
            $nameMarginY = 30.2;
            $validTillLabelMarginX = 41;
            $validTillLabelMarginY = 55;
            $validTillMarginX = 41;
            $validTillMarginY = 57;
            $validDownLabelMarginX = 41;
            $validDownLabelMarginY = 46;
            $validDownMarginX = 41;
            $validDownMarginY = 48;
            $qrCodeMarginX = 70.5;
            $qrCodeMarginY = 43;
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->AddFont('Ubuntu-L', '', $this->projectDir . '/lib/font/ubuntul.php', true);
        $pdf->AddFont('AcmeFont Regular', '', $this->projectDir . '/lib/font/acmefont.php', true);

        $x = 0.0;
        $y = 0.0;
        $card = 0;

        $noPhoto = [];

        end($foodsavers);

        $pdf->setSourceFile($this->projectDir . '/img/foodsharing_logo.pdf');
        $fs_logo = $pdf->importPage(1);

        foreach ($foodsavers as $fs_id) {
            if ($foodsaver = $this->foodsaverGateway->getFoodsaverDetails($fs_id)) {
                if (empty($foodsaver['photo'])) {
                    $noPhoto[] = $foodsaver['name'] . ' ' . $foodsaver['nachname'];

                    $bellData = Bell::create(
                        'passgen_failed_title',
                        'passgen_failed',
                        'fas fa-camera',
                        ['href' => '/?page=settings'],
                        ['user' => $this->session->user('name')],
                        BellType::createIdentifier(BellType::PASS_CREATION_FAILED, $foodsaver['id'])
                    );
                    $this->bellGateway->addBell($foodsaver['id'], $bellData);
                    //continue;
                }

                $pdf->SetTextColor(0, 0, 0);

                ++$card;

                $this->passportGeneratorGateway->passGen($this->session->id(), $foodsaver['id']);

                if ($cutMarkers) {
                    $backgroundFile = $this->projectDir . '/img/pass_bg.png';
                } else {
                    $backgroundFile = $this->projectDir . '/img/pass_bg_cut.png';
                }
                $pdf->Image($backgroundFile, $backgroundMarginX + $x, $backgroundMarginY + $y, 83, 55);

                $name = $foodsaver['name'] . ' ' . $foodsaver['nachname'];
                $fontSize = 10;
                $maxWidth = 49;
                $pdf->SetFont('Ubuntu-L', '', $fontSize);
                $maxFontSize = min($maxWidth / $pdf->GetStringWidth($name) * $fontSize, $fontSize);
                if ($maxFontSize >= 8) {
                    $pdf->SetFont('Ubuntu-L', '', $maxFontSize);
                    $pdf->Text($nameMarginX + $x, $nameMarginY + $y - 0.2, $name);
                } else {
                    // Require line break after first name
                    $fontSize = min(
                        $maxWidth / $pdf->GetStringWidth($foodsaver['name']) * $fontSize,
                        $maxWidth / $pdf->GetStringWidth($foodsaver['nachname']) * $fontSize,
                        8
                    );
                    $pdf->SetFont('Ubuntu-L', '', $fontSize);
                    $lineHeight = $pdf->getStringHeight(0, $foodsaver['name']) * 0.7;
                    $pdf->Text($nameMarginX + $x, $nameMarginY + $y - 0.2, $foodsaver['name']);
                    $pdf->Text($nameMarginX + $x, $nameMarginY + $y + $lineHeight - 0.2, $foodsaver['nachname']);
                }

                $pdf->SetFont('Ubuntu-L', '', 10);
                $pdf->Text($roleMarginX + $x, $roleMarginY + $y, $this->getRole($foodsaver['geschlecht'], $foodsaver['rolle']));
                $pdf->Text($validDownMarginX + $x, $validDownMarginY + $y, $untilFrom);
                $pdf->Text($validTillMarginX + $x, $validTillMarginY + $y, $validUntil);
                $pdf->SetFont('Ubuntu-L', '', 6);
                $pdf->Text($nameLabelMarginX + $x, $nameLabelMarginY + $y, 'Name');
                $pdf->Text($roleLabelMarginX + $x, $roleLabelMarginY + $y, 'Rolle');
                $pdf->Text($validDownLabelMarginX + $x, $validDownLabelMarginY + $y, 'Gültig ab');
                $pdf->Text($validTillLabelMarginX + $x, $validTillLabelMarginY + $y, 'Gültig bis');

                $pdf->SetFont('Ubuntu-L', '', 9);
                $pdf->SetTextColor(255, 255, 255);
                $pdf->SetXY($cellMarginX + $x, $cellMarginY + $y);
                $pdf->Cell($idLabelMarginX, $idLabelMarginY, 'ID ' . $fs_id, 0, 0, 'R');

                $pdf->SetFont('AcmeFont Regular', '', 5.3);
                $pdf->Text(12.8 + $x, 18.6 + $y, $this->translator->trans('pass.claim'));

                $pdf->useTemplate($fs_logo, $logoMarginX + $x, $logoMarginY + $y, 29.8);

                $style = [
                    'vpadding' => 'auto',
                    'hpadding' => 'auto',
                    'fgcolor' => [0, 0, 0],
                    'bgcolor' => false, // array(255,255,255)
                    'module_width' => 1, // width of a single module in points
                    'module_height' => 1 // height of a single module in points
                ];

                // FIXME Do we really always want fs.de here?!
                // QRCODE,L : QR-CODE Low error correction
                $pdf->write2DBarcode('https://foodsharing.de/profile/' . $fs_id, 'QRCODE,L', $qrCodeMarginX + $x, $qrCodeMarginY + $y, 20, 20, $style, 'N', true);

                if ($photo = $this->foodsaverGateway->getPhotoFileName($fs_id)) {
                    if (str_starts_with($photo, '/api/uploads')) {
                        // get the UUID and create a resized file
                        $uuid = substr($photo, strlen('/api/uploads/'));
                        $filename = $this->uploadsTransactions->generateFilePath($uuid, 200, 257, 0);
                        if (!file_exists($filename)) {
                            $originalFilename = $this->uploadsTransactions->generateFilePath($uuid);
                            $this->uploadsTransactions->resizeImage($originalFilename, $filename, 200, 257, 0);
                        }
                        $pdf->Image($filename, $photoMarginX + $x, $photoMarginY + $y, 24);
                    } else {
                        if (file_exists('images/crop_' . $photo)) {
                            $pdf->Image('images/crop_' . $photo, $photoMarginX + $x, $photoMarginY + $y, 24);
                        } elseif (file_exists('images/' . $photo)) {
                            $pdf->Image('images/' . $photo, $photoMarginX + $x, $photoMarginY + $y, 22);
                        }
                    }
                }

                if ($x == 0) {
                    $x += 95;
                } else {
                    $y += 65;
                    $x = 0;
                }

                if ($card == 8) {
                    $card = 0;
                    $pdf->AddPage();
                    $x = 0;
                    $y = 0;
                }

                $is_generated[] = $foodsaver['id'];
            }
        }
        if (!empty($noPhoto)) {
            $this->flashMessageHelper->info(
                $this->translator->trans('pass.noPhoto')
                . join(', ', $noPhoto)
                . $this->translator->trans('pass.notGenerated')
            );
        }

        if ($ambassadorGeneration) {
            $this->passportGeneratorGateway->updateLastGen($is_generated);
        }

        if ($oldGeneration) {
            $pdf->Output('foodsaver_pass_.pdf', 'D');
            exit;
        } else {
            return $pdf->Output('', 'S');
        }
    }

    public function getRole(int $gender_id, int $role_id): string
    {
        $roles = match ($gender_id) {
            Gender::MALE => [
                Role::FOODSHARER->value => $this->translator->trans('terminology.foodsharer.m'),
                Role::FOODSAVER->value => $this->translator->trans('terminology.foodsaver.m'),
                Role::STORE_MANAGER->value => $this->translator->trans('terminology.storemanager.m'),
                Role::AMBASSADOR->value => $this->translator->trans('terminology.ambassador.m'),
                Role::ORGA->value => $this->translator->trans('terminology.ambassador.m'),
            ],
            Gender::FEMALE => [
                Role::FOODSHARER->value => $this->translator->trans('terminology.foodsharer.f'),
                Role::FOODSAVER->value => $this->translator->trans('terminology.foodsaver.f'),
                Role::STORE_MANAGER->value => $this->translator->trans('terminology.storemanager.f'),
                Role::AMBASSADOR->value => $this->translator->trans('terminology.ambassador.f'),
                Role::ORGA->value => $this->translator->trans('terminology.ambassador.f'),
            ],
            default => [
                Role::FOODSHARER->value => $this->translator->trans('terminology.foodsharer.d'),
                Role::FOODSAVER->value => $this->translator->trans('terminology.foodsaver.d'),
                Role::STORE_MANAGER->value => $this->translator->trans('terminology.storemanager.d'),
                Role::AMBASSADOR->value => $this->translator->trans('terminology.ambassador.d'),
                Role::ORGA->value => $this->translator->trans('terminology.ambassador.d'),
            ],
        };

        return $roles[$role_id];
    }

    public function getPassDate(int $userId): \DateTime
    {
        $date = $this->passportGeneratorGateway->getLastGen($userId);

        if (empty($date)) {
            $verifyHistory = $this->profileGateway->getVerifyHistory($userId);
            if (!empty($verifyHistory)) {
                $latestEntry = end($verifyHistory);
                $date = $latestEntry->date;
            }
        }

        return $date;
    }
}
