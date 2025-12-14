<?php

declare(strict_types=1);

use Phinx\Migration\AbstractMigration;

final class OauthAppsContent extends AbstractMigration
{
    public function change(): void
    {
        $data = [
          'categories' => [
            [
              'key' => 'Android',
              'apps' => [
                [
                  'name' => 'Aegis Authenticator',
                  'links' => [
                    [
                      'url' => 'https://play.google.com/store/apps/details?id=com.beemdevelopment.aegis',
                      'icon' => 'fab fa-google-play',
                      'text' => 'Google Play',
                    ],
                    [
                      'url' => 'https://f-droid.org/en/packages/com.beemdevelopment.aegis/',
                      'icon' => 'fas fa-download',
                      'text' => 'F-Droid',
                    ],
                  ],
                ],
                [
                  'name' => 'Mauth',
                  'links' => [
                    [
                      'url' => 'https://f-droid.org/en/packages/com.xinto.mauth',
                      'icon' => 'fas fa-download',
                      'text' => 'F-Droid',
                    ],
                  ],
                ],
              ],
            ],
            [
              'key' => 'iOS',
              'apps' => [
                [
                  'name' => 'Raivo OTP',
                  'links' => [
                    [
                      'url' => 'https://apps.apple.com/app/raivo-otp/id1459042137',
                      'icon' => 'fab fa-app-store-ios',
                      'text' => 'App Store',
                    ],
                  ],
                ],
                [
                  'name' => 'Tofu Authenticator',
                  'links' => [
                    [
                      'url' => 'https://apps.apple.com/app/tofu-authenticator/id1082229305',
                      'icon' => 'fab fa-app-store-ios',
                      'text' => 'App Store',
                    ],
                  ],
                ],
              ],
            ],
            [
              'key' => 'Windows',
              'apps' => [
                [
                  'name' => '2FAGuard',
                  'links' => [
                    [
                      'url' => 'https://github.com/timokoessler/2FAGuard',
                      'icon' => 'fab fa-github',
                      'text' => 'GitHub',
                    ],
                    [
                      'url' => 'https://apps.microsoft.com/detail/9p6hr4gszjrm',
                      'icon' => 'fab fa-microsoft',
                      'text' => 'Microsoft Store',
                    ],
                  ],
                ],
                [
                  'name' => 'AuthMe',
                  'links' => [
                    [
                      'url' => 'https://github.com/Levminer/authme',
                      'icon' => 'fab fa-github',
                      'text' => 'GitHub',
                    ],
                    [
                      'url' => 'https://apps.microsoft.com/detail/xp9m33rjsvd6jr',
                      'icon' => 'fab fa-microsoft',
                      'text' => 'Microsoft Store',
                    ],
                  ],
                ],
              ],
            ],
            [
              'key' => 'Linux',
              'apps' => [
                [
                  'name' => 'Authenticator',
                  'links' => [
                    [
                      'url' => 'https://flathub.org/apps/details/com.belmoussaoui.Authenticator',
                      'icon' => 'fas fa-download',
                      'text' => 'Flathub',
                    ],
                  ],
                ],
                [
                  'name' => 'OTPClient',
                  'links' => [
                    [
                      'url' => 'https://github.com/paolostivanin/OTPClient',
                      'icon' => 'fab fa-github',
                      'text' => 'GitHub',
                    ],
                  ],
                ],
              ],
            ],
          ],
        ];

        $json = '<p>' . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . '</p>';

        $this->table('fs_content')
          ->insert([
            [
              'id' => '97',
              'name' => 'two_fa_apps',
              'title' => '2FA Authenticator Apps',
              'body' => $json,
              'last_mod' => '2025-11-21 00:00:00',
            ],
          ])
          ->save();
    }
}
