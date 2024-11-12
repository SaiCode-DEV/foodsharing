<?php

namespace Foodsharing\Modules\BusinessCard;

use Foodsharing\Lib\FoodsharingController;
use Foodsharing\Modules\Core\DBConstants\Foodsaver\Role;
use setasign\Fpdi\Tcpdf\Fpdi;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

class BusinessCardController extends FoodsharingController
{
    private const MAX_CHAR_PER_LINE = 45;

    public function __construct(
        private readonly BusinessCardGateway $gateway,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    #[Route('/bcard', name: 'bcard')]
    public function index(Request $request, #[MapQueryParameter] ?string $sub): Response
    {
        return match ($sub) {
            'makeCard' => $this->makeCard($request),
            default => $this->redirectToRoute('current_user_settings', ['sub' => 'bcard']),
        };
    }

    private function makeCard(Request $request): Response
    {
        $data = $this->gateway->getMyData($this->session->id(), $this->session->mayRole(Role::STORE_MANAGER));
        $opt = $request->query->get('opt');
        if (!$data || !$opt) {
            throw new BadRequestHttpException();
        } else {
            $opt = explode(':', $opt); // role:region
        }

        if (count($opt) != 2 || (int)$opt[1] < 0) {
            throw new BadRequestHttpException();
        }

        $regionId = (int)$opt[1];
        $role = $opt[0];
        $mailbox = false;

        if (isset($data[$role]) && $data[$role] != false) {
            foreach ($data[$role] as $d) {
                if ($d['id'] == $regionId) {
                    $mailbox = $d;
                }
            }
        } else {
            throw new BadRequestHttpException();
        }

        if (!$mailbox) {
            throw new BadRequestHttpException();
        }

        if (isset($mailbox['email'])) {
            $data['email'] = $mailbox['email'];
        }
        $data['subtitle'] = $this->displayedRole($role, $data['geschlecht'], $mailbox['name']);

        if (mb_strlen($data['plz'] . ' ' . $data['stadt']) >= self::MAX_CHAR_PER_LINE) {
            $data['stadt'] = mb_substr((string)$data['stadt'], 0, self::MAX_CHAR_PER_LINE - strlen((string)$data['plz']) - 4) . '...';
        }

        $pdf = $this->generatePdf($data);

        $filename = 'bcard-' . $role . '.pdf';

        // mimick TCPDF's output headers as close as possible
        // (though some of it is long deprecated and/or just there for IE compatibility)
        // BinaryFileResponse would do a lot of this automatically, but it only supports files (which we would need to clean up etc)
        $resp = new Response($pdf);
        $resp->headers->set('Content-Type', 'application/pdf');
        $dispositionHeader = $resp->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        $resp->headers->set('Content-Disposition', $dispositionHeader);
        $resp->setCache([
            'private' => true,
            'must_revalidate' => true,
            'max_age' => 1,
            'last_modified' => new \DateTimeImmutable(), // now
        ]);

        return $resp;
    }

    private function displayedRole(string $role, int $gender, string $regionName): string
    {
        $modifier = 'dmfd'[$gender]; // 0=d 1=m 2=f 3=d
        $roleName = match ($role) {
            'sm' => $this->translator->trans('terminology.storemanager.' . $modifier),
            'bot' => $this->translator->trans('terminology.ambassador.' . $modifier),
            default => $this->translator->trans('terminology.foodsaver.' . $modifier),
        };

        return $this->translator->trans('bcard.for', ['{role}' => $roleName, '{region}' => $regionName]);
    }

    private function generatePdf(array $data): string
    {
        $pdf = new Fpdi();
        $pdf->AddPage();
        $pdf->SetTextColor(0, 0, 0);
        $pdf->AddFont('Ubuntu-L', '', $this->projectDir . '/lib/font/ubuntul.php', true);
        $pdf->AddFont('AcmeFont Regular', '', $this->projectDir . '/lib/font/acmefont.php', true);

        $x = 0.0;
        $y = 0.0;

        for ($i = 0; $i < 8; ++$i) {
            $pdf->Image($this->projectDir . '/img/fsvisite.png', 10 + $x, 10 + $y, 91, 61);

            $pdf->SetTextColor(85, 60, 36);

            if (strlen($data['name'] . ' ' . $data['nachname']) >= 33) {
                $pdf->SetFont('Ubuntu-L', '', 5);
                $pdf->Text(48.5 + $x, 29.5 + $y, $data['name'] . ' ' . $data['nachname']);
            } elseif (strlen($data['name'] . ' ' . $data['nachname']) >= 22) {
                $pdf->SetFont('Ubuntu-L', '', 7);
                $pdf->Text(48.5 + $x, 29.5 + $y, $data['name'] . ' ' . $data['nachname']);
            } else {
                $pdf->SetFont('Ubuntu-L', '', 10);
                $pdf->Text(48.5 + $x, 29.5 + $y, $data['name'] . ' ' . $data['nachname']);
            }

            $pdf->SetFont('Ubuntu-L', '', 7);

            $pdf->SetXY(48.5 + $x, 35.2 + $y);
            $pdf->MultiCell(50, 12, $data['subtitle'], 0, 'L');

            $pdf->SetTextColor(0, 0, 0);

            $tel = $data['handy'];
            if (empty($tel)) {
                $tel = $data['telefon'];
            }

            $pdf->Text(53.3 + $x, 45.8 + $y, $tel);
            $this->formatEmail($pdf, $x, $y, $data['email']);
            $pdf->Text(53.3 + $x, 61.2 + $y, BASE_URL);
            if ($x == 0) {
                $x += 91;
            } else {
                $y += 61;
                $x = 0;
            }
        }

        return $pdf->Output('', 'S');
    }

    private function formatEmail(Fpdi $pdf, float $x, float $y, string $email): void
    {
        $emailWidth = $pdf->GetStringWidth($email);

        if ($emailWidth > 51) {
            $parts = explode('@', $email);

            if (count($parts) == 2) {
                $firstPart = $parts[0];
                $secondPart = '@' . $parts[1];

                $pdf->Text(53.3 + $x, 52.2 + $y, $firstPart);
                $pdf->Text(53.3 + $x, 55.2 + $y, $secondPart);
            }
        } else {
            $pdf->Text(53.3 + $x, 52.2 + $y, $email);
        }
    }
}
