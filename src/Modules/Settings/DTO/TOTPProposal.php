<?php

namespace Foodsharing\Modules\Settings\DTO;

use OpenApi\Attributes as OA;

/**
 * @property string[] $backupCodes
 */
class TOTPProposal
{
    #[OA\Property('reused', description: 'How often the same setup proposal has been reused from the session', example: 0)]
    public int $reused;

    #[OA\Property('secret', description: 'Base32-encoded TOTP secret', example: 'JBSWY3DPEHPK3PXP')]
    public string $secret;

    #[OA\Property('qrCode', description: 'QR code as data URI (PNG)', example: 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAZAAAAGQCAYAAACAvzbMAAA...')]
    public string $qrCode;

    #[OA\Property('backupCodes', description: 'One-time-use backup codes for account recovery', type: 'array',
        items: new OA\Items(type: 'string', example: 'A9F3-4C2B-91D8')
    )]
    public array $backupCodes;

    public function __construct(
        int $reused,
        string $secret,
        string $qrCode,
        array $backupCodes
    ) {
        $this->reused = $reused;
        $this->secret = $secret;
        $this->qrCode = $qrCode;
        $this->backupCodes = $backupCodes;
    }

    public static function tryFromArray(mixed $data): ?self
    {
        try {
            return new self($data['reused'], $data['secret'], $data['qrCode'], $data['backupCodes']);
        } catch (\Exception) {
            return null;
        }
    }
}
