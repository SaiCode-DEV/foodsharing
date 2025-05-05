<?php

/*
 * Copyright 2022 Google Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the 'License');
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an 'AS IS' BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Foodsharing\Lib;

use DateTime;
use Firebase\JWT\JWT;
use Google\Auth\Credentials\ServiceAccountCredentials;
use Google\Client as GoogleClient;
use Google\Service\Exception;
use Google\Service\Walletobjects;
use Google\Service\Walletobjects\Barcode;
use Google\Service\Walletobjects\GenericClass;
use Google\Service\Walletobjects\GenericObject;
use Google\Service\Walletobjects\Image;
use Google\Service\Walletobjects\ImageUri;
use Google\Service\Walletobjects\LinksModuleData;
use Google\Service\Walletobjects\LocalizedString;
use Google\Service\Walletobjects\TextModuleData;
use Google\Service\Walletobjects\TranslatedString;
use Google\Service\Walletobjects\Uri;
use RuntimeException;
use Symfony\Contracts\Translation\TranslatorInterface;

/** Demo class for creating and managing passes in Google Wallet. */
class GoogleWalletPass
{
    /**
     * The Google API Client
     * https://github.com/google/google-api-php-client.
     */
    public GoogleClient $client;

    /**
     * Path to service account key file from Google Cloud Console.
     */
    public string $keyFilePath;

    /**
     * Service account credentials for Google Wallet APIs.
     */
    public ServiceAccountCredentials $credentials;

    /**
     * Google Wallet service client.
     */
    public Walletobjects $service;

    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
        if (!empty(GOOGLE_WALLET_ISSUER_ID)) {
            $this->keyFilePath = GOOGLE_WALLET_KEY_PATH;
            $this->auth();
        }
    }

    /**
     * Create authenticated HTTP client using a service account file.
     */
    public function auth(): void
    {
        try {
            if (!file_exists($this->keyFilePath)) {
                throw new RuntimeException("Key file not found: {$this->keyFilePath}");
            }

            if (!is_readable($this->keyFilePath)) {
                throw new RuntimeException("Key file is not readable. Please check file permissions: {$this->keyFilePath}");
            }

            $fileContent = file_get_contents($this->keyFilePath);
            if (!$fileContent) {
                throw new RuntimeException("Unable to read key file or file is empty: {$this->keyFilePath}");
            }

            // Check if file contains valid JSON
            json_decode($fileContent);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Key file does not contain valid JSON format: ' . json_last_error_msg());
            }

            $this->credentials = new ServiceAccountCredentials(
                Walletobjects::WALLET_OBJECT_ISSUER,
                $this->keyFilePath
            );

            // Initialize Google Wallet API service
            $this->client = new GoogleClient();
            $this->client->setApplicationName(WALLET_LABEL);
            $this->client->setScopes(Walletobjects::WALLET_OBJECT_ISSUER);
            $this->client->setAuthConfig($this->keyFilePath);

            $this->service = new Walletobjects($this->client);
        } catch (\Exception $e) {
            throw new RuntimeException('Error during Google Wallet authentication: ' . $e->getMessage());
        }
    }

    /**
     * Create a class.
     *
     * @return string The pass class ID: "{$issuerId}.{$classSuffix}"
     */
    public function createClass()
    {
        $issuerId = GOOGLE_WALLET_ISSUER_ID;
        $classSuffix = GOOGLE_WALLET_CLASS_ID;
        // Check if the class exists
        try {
            $this->service->genericclass->get("{$issuerId}.{$classSuffix}");

            return "{$issuerId}.{$classSuffix}";
        } catch (Exception $ex) {
            if (empty($ex->getErrors()) || $ex->getErrors()[0]['reason'] != 'classNotFound') {
                throw new RuntimeException($ex->getMessage());
            }
        }

        // See link below for more information on required properties
        // https://developers.google.com/wallet/generic/rest/v1/genericclass
        $newClass = new GenericClass([
          'id' => "{$issuerId}.{$classSuffix}",
          'classTemplateInfo' => [
            'cardTemplateOverride' => [
              'cardRowTemplateInfos' => [
                [
                  'twoItems' => [
                    'startItem' => [
                      'firstValue' => [
                        'fields' => [
                          [
                            'fieldPath' => "object.textModulesData['valid_start']"
                          ]
                        ]
                      ]
                    ],
                    'endItem' => [
                      'firstValue' => [
                        'fields' => [
                          [
                            'fieldPath' => "object.textModulesData['valid_end']"
                          ]
                        ]
                      ]
                    ]
                  ]
                ]
              ]
            ]
          ]
        ]);

        $response = $this->service->genericclass->insert($newClass);

        return $response->id;
    }

    /**
     * Update a class.
     *
     * **Warning:** This replaces all existing class attributes!
     *
     * @return string The pass class ID: "{$issuerId}.{$classSuffix}"
     */
    public function updateClass()
    {
        $issuerId = GOOGLE_WALLET_ISSUER_ID;
        $classSuffix = GOOGLE_WALLET_CLASS_ID;
        // Check if the class exists
        try {
            $updatedClass = $this->service->genericclass->get("{$issuerId}.{$classSuffix}");
        } catch (Exception $ex) {
            if (!empty($ex->getErrors()) && $ex->getErrors()[0]['reason'] == 'classNotFound') {
                // Class does not exist
                echo "Class {$issuerId}.{$classSuffix} not found!";

                return "{$issuerId}.{$classSuffix}";
            } else {
                throw new RuntimeException($ex->getMessage());
            }
        }

        $newClass = new GenericClass([
          'id' => "{$issuerId}.{$classSuffix}",
          'classTemplateInfo' => [
            'cardTemplateOverride' => [
              'cardRowTemplateInfos' => [
                [
                  'twoItems' => [
                    'startItem' => [
                      'firstValue' => [
                        'fields' => [
                          [
                            'fieldPath' => "object.textModulesData['valid_start']"
                          ]
                        ]
                      ]
                    ],
                    'endItem' => [
                      'firstValue' => [
                        'fields' => [
                          [
                            'fieldPath' => "object.textModulesData['valid_end']"
                          ]
                        ]
                      ]
                    ]
                  ]
                ]
              ]
            ]
          ]
        ]);

        $updatedClass = $newClass;

        $response = $this->service->genericclass->update("{$issuerId}.{$classSuffix}", $updatedClass);

        return $response->id;
    }

    /**
     * Create an object.
     *
     * @param int $userId developer-defined unique ID for this pass object
     *
     * @return string The pass object ID: "{$issuerId}.{$userId}"
     */
    public function createObject(int $userId, string $name, string $profileURL, string $photo, ?DateTime $passDate)
    {
        $issuerId = GOOGLE_WALLET_ISSUER_ID;
        $classSuffix = GOOGLE_WALLET_CLASS_ID;
        $validStart = $passDate->format('d. m. Y');
        $validEnd = $passDate->modify('+3 year')->format('d. m. Y');
        // Check if the object exists
        try {
            $this->service->genericobject->get("{$issuerId}.{$userId}");

            echo "Object {$issuerId}.{$userId} already exists!";

            return "{$issuerId}.{$userId}";
        } catch (Exception $ex) {
            if (empty($ex->getErrors()) || $ex->getErrors()[0]['reason'] != 'resourceNotFound') {
                throw new RuntimeException($ex->getMessage());
            }
        }

        // See link below for more information on required properties
        // https://developers.google.com/wallet/generic/rest/v1/genericobject
        $newObject = new GenericObject([
          'id' => "{$issuerId}.{$userId}",
          'classId' => "{$issuerId}.{$classSuffix}",
          'state' => 'ACTIVE',
          'validTimeInterval' => [
            'start' => [
              'date' => $passDate->format(DateTime::ATOM)
            ],
            'end' => [
              'date' => $passDate->modify('+3 year')->format(DateTime::ATOM)
            ],
          ],
          'heroImage' => new Image([
            'sourceUri' => new ImageUri([
              'uri' => $photo
            ]),
            'contentDescription' => new LocalizedString([
              'defaultValue' => new TranslatedString([
                'language' => 'de-DE',
                'value' => WALLET_LABEL . ' logo'
              ])
            ])
          ]),
          'textModulesData' => [
            new TextModuleData([
              'header' => $this->translator->trans('settings.passport.wallet.valid_start'),
              'body' => $validStart,
              'id' => 'valid_start'
            ]),
            new TextModuleData([
              'header' => $this->translator->trans('settings.passport.wallet.valid_end'),
              'body' => $validEnd,
              'id' => 'valid_end'
            ])
          ],
          'linksModuleData' => new LinksModuleData([
            'uris' => [
              new Uri([
                'uri' => $profileURL,
                'description' => WALLET_LABEL,
                'id' => 'LINK_MODULE_URI_ID'
              ])
            ]
          ]),
          'barcode' => new Barcode([
            'type' => 'QR_CODE',
            'alternateText' => 'ID ' . $userId,
            'value' => $profileURL
          ]),
          'cardTitle' => new LocalizedString([
            'defaultValue' => new TranslatedString([
              'language' => 'de-DE',
              'value' => WALLET_LABEL
            ])
          ]),
          'subheader' => new LocalizedString([
            'defaultValue' => new TranslatedString([
              'language' => 'de-DE',
              'value' => $this->translator->trans('settings.passport.wallet.name')
            ])
          ]),
          'header' => new LocalizedString([
            'defaultValue' => new TranslatedString([
              'language' => 'de-DE',
              'value' => $name
            ])
          ]),
          'hexBackgroundColor' => '#142307',
          'logo' => new Image([
            'sourceUri' => new ImageUri([
              'uri' => 'https://foodsharing.de/uploads/wallet/FS_Schriftzug_gb_klein.png'
            ])
          ]),
          'wideLogo' => new Image([
            'sourceUri' => new ImageUri([
              'uri' => 'https://foodsharing.de/uploads/wallet/FS_Schriftzug_gw.png'
            ])
          ]),
          'notifications' => [
            'expiryNotification' => [
              'enableNotification' => true,
            ],
          ],
        ]);

        $response = $this->service->genericobject->insert($newObject);

        return $response->id;
    }

    /**
     * Update an object.
     *
     * **Warning:** This replaces all existing object attributes!
     *
     * @param int $userId developer-defined unique ID for this pass object
     *
     * @return string The pass object ID: "{$issuerId}.{$userId}"
     */
    public function updateObject(int $userId, GenericObject $data, bool $skipCheck = false)
    {
        $issuerId = GOOGLE_WALLET_ISSUER_ID;

        if (!$skipCheck) {
            // Check if the object exists
            try {
                $updatedObject = $this->service->genericobject->get("{$issuerId}.{$userId}");
            } catch (Exception $ex) {
                if (!empty($ex->getErrors()) && $ex->getErrors()[0]['reason'] == 'resourceNotFound') {
                    echo "Object {$issuerId}.{$userId} not found!";

                    return "{$issuerId}.{$userId}";
                } else {
                    throw new RuntimeException($ex->getMessage());
                }
            }
        }

        try {
            $response = $this->service->genericobject->update("{$issuerId}.{$userId}", $data);

            return $response->id;
        } catch (Exception $ex) {
            throw new RuntimeException($ex->getMessage());
        }
    }

    /**
     * Expire an object.
     *
     * Sets the object's state to Expired. If the valid time interval is
     * already set, the pass will expire automatically up to 24 hours after.
     *
     * @param int $userId developer-defined unique ID for this pass object
     *
     * @return string The pass object ID: "{$issuerId}.{$userId}"
     */
    public function expireObject(int $userId)
    {
        $issuerId = GOOGLE_WALLET_ISSUER_ID;
        // Check if the object exists
        try {
            $this->service->genericobject->get("{$issuerId}.{$userId}");
        } catch (Exception $ex) {
            if (!empty($ex->getErrors()) && $ex->getErrors()[0]['reason'] == 'resourceNotFound') {
                echo "Object {$issuerId}.{$userId} not found!";

                return "{$issuerId}.{$userId}";
            } else {
                throw new RuntimeException($ex->getMessage());
            }
        }

        // Patch the object, setting the pass as expired
        $patchBody = new GenericObject([
          'state' => 'EXPIRED'
        ]);

        $response = $this->service->genericobject->patch("{$issuerId}.{$userId}", $patchBody);

        return $response->id;
    }

    /**
     * Renew an object.
     * @param int $userId developer-defined unique ID for this pass object
     * @return string The pass object ID: "{$issuerId}.{$userId}"
     */
    public function renewObject(int $userId, DateTime $passDate = new DateTime())
    {
        $issuerId = GOOGLE_WALLET_ISSUER_ID;
        $validStart = $passDate->format('d. m. Y');
        $validEnd = $passDate->modify('+3 year')->format('d. m. Y');
        // Check if the object exists
        try {
            $this->service->genericobject->get("{$issuerId}.{$userId}");
        } catch (Exception $ex) {
            if (!empty($ex->getErrors()) && $ex->getErrors()[0]['reason'] == 'resourceNotFound') {
                return "{$issuerId}.{$userId}";
            } else {
                throw new RuntimeException($ex->getMessage());
            }
        }
        // Patch the object, setting the pass as active
        $patchBody = new GenericObject([
          'state' => 'ACTIVE',
          'validTimeInterval' => [
            'start' => [
              'date' => $passDate->format(DateTime::ATOM)
            ],
            'end' => [
              'date' => $passDate->modify('+3 year')->format(DateTime::ATOM)
            ],
          ],
          'textModulesData' => [
            new TextModuleData([
              'header' => $this->translator->trans('settings.passport.wallet.valid_start'),
              'body' => $validStart,
              'id' => 'valid_start'
            ]),
            new TextModuleData([
              'header' => $this->translator->trans('settings.passport.wallet.valid_end'),
              'body' => $validEnd,
              'id' => 'valid_end'
            ])
          ],
        ]);

        $response = $this->service->genericobject->patch("{$issuerId}.{$userId}", $patchBody);

        return $response->id;
    }

    /**
     * Generate a signed JWT that creates a new pass class and object.
     *
     * When the user opens the "Add to Google Wallet" URL and saves the pass to
     * their wallet, the pass class and object defined in the JWT are
     * created. This allows you to create multiple pass classes and objects in
     * one API call when the user saves the pass to their wallet.
     *
     * @param int $userId developer-defined unique ID for the pass object
     *
     * @return string an "Add to Google Wallet" link
     */
    public function createNewPassJwt(int $userId, string $name, string $profileURL, string $photo, DateTime $passDate): string
    {
        $issuerId = GOOGLE_WALLET_ISSUER_ID;
        $classSuffix = GOOGLE_WALLET_CLASS_ID;
        $validStart = $passDate->format('d. m. Y');
        $validEnd = $passDate->modify('+3 year')->format('d. m. Y');

        // See link below for more information on required properties
        // https://developers.google.com/wallet/generic/rest/v1/genericclass

        $newObject = new GenericObject([
          'id' => "{$issuerId}.{$userId}",
          'classId' => "{$issuerId}.{$classSuffix}",
          'state' => 'ACTIVE',
          'validTimeInterval' => [
            'start' => [
              'date' => $passDate->format(DateTime::ATOM)
            ],
            'end' => [
              'date' => $passDate->modify('+3 year')->format(DateTime::ATOM)
            ],
          ],
          'heroImage' => new Image([
            'sourceUri' => new ImageUri([
              'uri' => $photo
            ]),
            'contentDescription' => new LocalizedString([
              'defaultValue' => new TranslatedString([
                'language' => 'de-DE',
                'value' => WALLET_LABEL . ' logo'
              ])
            ])
          ]),
          'textModulesData' => [
            new TextModuleData([
              'header' => $this->translator->trans('settings.passport.wallet.valid_start'),
              'body' => $validStart,
              'id' => 'valid_start'
            ]),
            new TextModuleData([
              'header' => $this->translator->trans('settings.passport.wallet.valid_end'),
              'body' => $validEnd,
              'id' => 'valid_end'
            ])
          ],
          'linksModuleData' => new LinksModuleData([
            'uris' => [
              new Uri([
                'uri' => $profileURL,
                'description' => WALLET_LABEL,
                'id' => 'LINK_MODULE_URI_ID'
              ])
            ]
          ]),
          'barcode' => new Barcode([
            'type' => 'QR_CODE',
            'alternateText' => 'ID ' . $userId,
            'value' => $profileURL,
          ]),
          'cardTitle' => new LocalizedString([
            'defaultValue' => new TranslatedString([
              'language' => 'de-DE',
              'value' => WALLET_LABEL
            ])
          ]),
          'subheader' => new LocalizedString([
            'defaultValue' => new TranslatedString([
              'language' => 'de-DE',
              'value' => $this->translator->trans('settings.passport.wallet.name')
            ])
          ]),
          'header' => new LocalizedString([
            'defaultValue' => new TranslatedString([
              'language' => 'de-DE',
              'value' => $name
            ])
          ]),
          'hexBackgroundColor' => '#142307',
          'logo' => new Image([
            'sourceUri' => new ImageUri([
              'uri' => 'https://foodsharing.de/uploads/wallet/FS_Schriftzug_gb_klein.png'
            ])
          ]),
          'wideLogo' => new Image([
            'sourceUri' => new ImageUri([
              'uri' => 'https://foodsharing.de/uploads/wallet/FS_Schriftzug_gw.png'
            ])
          ]),
          'notifications' => [
            'expiryNotification' => [
              'enableNotification' => true,
            ],
          ],
        ]);

        try {
            $this->service->genericobject->get("{$issuerId}.{$userId}");

            $this->updateObject($userId, $newObject, true);

            return $this->createJwtExistingObjects($userId);
        } catch (Exception $ex) {
            if (empty($ex->getErrors()) || $ex->getErrors()[0]['reason'] != 'resourceNotFound') {
                throw new RuntimeException($ex->getMessage());
            }
        }

        // The service account credentials are used to sign the JWT
        $serviceAccount = json_decode(file_get_contents($this->keyFilePath), true);

        // Create the JWT as an array of key/value pairs
        $claims = [
          'iss' => $serviceAccount['client_email'],
          'aud' => 'google',
          'origins' => [WALLET_LABEL],
          'typ' => 'savetowallet',
          'payload' => [
            'genericObjects' => [
              $newObject
            ]
          ]
        ];

        $token = JWT::encode(
            $claims,
            $serviceAccount['private_key'],
            'RS256'
        );

        return "https://pay.google.com/gp/v/save/{$token}";
    }

    /**
     * Generate a signed JWT that references an existing pass object.
     *
     * When the user opens the "Add to Google Wallet" URL and saves the pass to
     * their wallet, the pass objects defined in the JWT are added to the
     * user's Google Wallet app. This allows the user to save multiple pass
     * objects in one API call.
     *
     * The objects to add must follow the below format:
     *
     *  {
     *    'id': 'ISSUER_ID.OBJECT_SUFFIX',
     *    'classId': 'ISSUER_ID.CLASS_SUFFIX'
     *  }
     *
     * @return string an "Add to Google Wallet" link
     */
    public function createJwtExistingObjects(int $userId): string
    {
        $issuerId = GOOGLE_WALLET_ISSUER_ID;
        $classSuffix = GOOGLE_WALLET_CLASS_ID;

        // The service account credentials are used to sign the JWT
        $serviceAccount = json_decode(file_get_contents($this->keyFilePath), true);

        // Create the JWT as an array of key/value pairs
        $claims = [
          'iss' => $serviceAccount['client_email'],
          'aud' => 'google',
          'origins' => [WALLET_LABEL],
          'typ' => 'savetowallet',
          'payload' => [
            'genericObjects' => [
              [
                'id' => "{$issuerId}.{$userId}",
                'classId' => "{$issuerId}.{$classSuffix}"
              ]
            ],
          ]
        ];

        $token = JWT::encode(
            $claims,
            $serviceAccount['private_key'],
            'RS256'
        );

        return "https://pay.google.com/gp/v/save/{$token}";
    }
}
