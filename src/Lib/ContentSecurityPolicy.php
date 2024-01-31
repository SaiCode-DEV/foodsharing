<?php

namespace Foodsharing\Lib;

class ContentSecurityPolicy
{
    public function generate(string $httpHost, string $reportUri = null, bool $reportOnly = false): string
    {
        $none = "'none'";
        $self = "'self'";
        $unsafeInline = "'unsafe-inline'";
        $unsafeEval = "'unsafe-eval'";
        $gitpod_config = 'config/gitpod';
        $gitpod_url = '';
        if (file_exists($gitpod_config)) {
            $gitpod_file = file_get_contents($gitpod_config, true);
            $gitpod_explode = explode(PHP_EOL, $gitpod_file);
            $gitpod_url = $gitpod_explode[0];
        }

        $policy = [
            'default-src' => [
                $none
            ],
            'script-src' => [
                $self,
                $unsafeInline,
                $unsafeEval, // lots of `$.globalEval` still ... 😢
            ],
            'connect-src' => [
                $self,
                $this->websocketUrlFor($httpHost),
                $gitpod_url,
                'https://localhost',
                'https://nginx',
                'https://sentry.io',
                'https://photon.komoot.io',
                'https://maps.geoapify.com',
                'https://maps01.geoapify.com',
                'https://maps02.geoapify.com',
                'https://maps03.geoapify.com',
                'https://tile.openstreetmap.org',
                'https://search.mapzen.com', // only used in u_loadCoords, gets hopefully replaces soon
                'https://gitpod.io',
                'https://spenden.twingle.de',
                'blob:',
                'ws:'
            ],
            'img-src' => [
                $self,
                'data:',
                'https:',
                'blob:'
            ],
            'media-src' => [
                $self
            ],
            'style-src' => [
                $self,
                $unsafeInline,
            ],
            'font-src' => [
                $self,
                'data:'
            ],
            'frame-src' => [
                $self
            ],
            'frame-ancestors' => [
                $none
            ],
            'worker-src' => [
                $self,
                'blob:'
            ],
            'child-src' => [
                $self,
                'blob:'
            ],
            'manifest-src' => [
                $self
            ]
        ];

        if ($reportUri) {
            $policy['report-uri'] = [
                $reportUri
            ];
        }

        $value = '';
        foreach ($policy as $key => $values) {
            $value .= $key . ' ' . implode(' ', $values) . '; ';
        }

        if ($reportOnly) {
            return 'Content-Security-Policy-Report-Only: ' . $value;
        }

        return 'Content-Security-Policy: ' . $value;
    }

    public function websocketUrlFor(string $baseUrl): string
    {
        return preg_replace('/^http(s)?:/', 'ws\1:', $baseUrl);
    }
}
