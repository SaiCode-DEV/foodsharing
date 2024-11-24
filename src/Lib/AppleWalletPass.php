<?php

/**
 * Copyright (c) 2017, Thomas Schoffelen BV.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense, and/or
 * sell copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 */

namespace Foodsharing\Lib;

use PKPass\PKPass;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;

class AppleWalletPass
{
    /**
     * Path to p12 certificate file.
     */
    public string $certFilePath;

    /**
     * Password for p12 certificate file.
     */
    private string $certFilePass;

    public function __construct(
        private readonly TranslatorInterface $translator,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
        $this->certFilePath = APPLE_WALLET_CERTIFICATE_PATH;
        $this->certFilePass = APPLE_WALLET_CERTIFICATE_PASS;
    }

    public function createNewPass(int $userId, string $name, string $profileURL, string $photoFileName, \DateTime $passDate): string
    {
        $pass = new PKPass($this->certFilePath, $this->certFilePass);

        $validStart = $passDate->format('c');
        $validEnd = $passDate->modify('+3 year')->format('c');
        $photoFileName = str_replace('./', $this->projectDir, $photoFileName);
        if (file_exists($photoFileName)) {
            $pass->addFile($photoFileName, 'thumbnail.png');
        }
        // Pass content
        $data = [
            'description' => 'Foodsharing Ausweis',
            'formatVersion' => 1,
            'organizationName' => 'Foodsharing',
            'passTypeIdentifier' => APPLE_WALLET_PASS_TYPE_ID,
            'serialNumber' => APPLE_WALLET_PASS_TYPE_ID . $userId,
            'teamIdentifier' => APPLE_WALLET_TEAM_ID,
            'eventTicket' => [
                'headerFields' => [
                    [
                        'key' => 'header',
                        'label' => 'ID',
                        'value' => $userId,
                    ],
                ],
                'primaryFields' => [
                    [
                        'key' => 'name',
                        'label' => $this->translator->trans('settings.passport.wallet.name'),
                        'value' => $name,
                    ],
                ],
                'auxiliaryFields' => [
                    [
                        'key' => 'valid_start',
                        'label' => $this->translator->trans('settings.passport.wallet.valid_start'),
                        'value' => $validStart,
                        'dateStyle' => 'PKDateStyleShort',
                        'timeStyle' => 'PKDateStyleNone',
                    ],
                    [
                        'key' => 'valid_end',
                        'label' => $this->translator->trans('settings.passport.wallet.valid_end'),
                        'value' => $validEnd,
                        'dateStyle' => 'PKDateStyleShort',
                        'timeStyle' => 'PKDateStyleNone',
                    ],
                ],
            ],
            'barcode' => [
                'format' => 'PKBarcodeFormatQR',
                'message' => $profileURL,
                'messageEncoding' => 'iso-8859-1',
            ],
            'backgroundColor' => 'rgb(20,35,7)',
            'foregroundColor' => 'rgb(255,255,255)',
            'labelColor' => 'rgb(100,174,36)',
            'expirationDate' => $validEnd,
        ];
        $pass->setData($data);

        // Add files to the pass package
        $pass->addFile($this->projectDir . '/img/apple/icon.png');
        $pass->addFile($this->projectDir . '/img/apple/icon@2x.png');
        $pass->addFile($this->projectDir . '/img/apple/logo.png');
        $pass->addFile($this->projectDir . '/img/apple/background.png');

        // Create and output the pass
        return $pass->create(true);
    }
}
