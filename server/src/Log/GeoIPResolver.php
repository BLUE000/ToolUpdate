<?php
declare(strict_types=1);

namespace ReleaseHub\Log;

class GeoIPResolver
{
    private array $localRanges = [];
    private array $countryRanges = [];

    public function __construct(array $geoData)
    {
        if (isset($geoData['local']) && is_array($geoData['local'])) {
            foreach ($geoData['local'] as $item) {
                $start = ip2long($item['start'] ?? '');
                $end = ip2long($item['end'] ?? '');
                if ($start !== false && $end !== false) {
                    $this->localRanges[] = [
                        'start' => sprintf('%u', $start),
                        'end' => sprintf('%u', $end),
                        'code' => $item['code'] ?? 'LOCAL',
                        'name' => $item['name'] ?? 'Localhost'
                    ];
                }
            }
        }

        if (isset($geoData['ranges']) && is_array($geoData['ranges'])) {
            foreach ($geoData['ranges'] as $item) {
                $start = ip2long($item['start'] ?? '');
                $end = ip2long($item['end'] ?? '');
                if ($start !== false && $end !== false) {
                    $this->countryRanges[] = [
                        'start' => sprintf('%u', $start),
                        'end' => sprintf('%u', $end),
                        'code' => $item['code'] ?? 'OTHER',
                        'name' => $item['name'] ?? 'International/Other'
                    ];
                }
            }
        }
    }

    public function resolve(string $ip): array
    {
        $ip = trim($ip);
        if ($ip === '' || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return [
                'country_code' => 'UNKNOWN',
                'country_name' => 'Unknown'
            ];
        }

        $longIp = ip2long($ip);
        if ($longIp === false) {
            return [
                'country_code' => 'UNKNOWN',
                'country_name' => 'Unknown'
            ];
        }

        $ipNum = sprintf('%u', $longIp);

        // 1. ローカル・プライベートIP判定
        $ipFloat = (float)$ipNum;
        foreach ($this->localRanges as $range) {
            if ($ipFloat >= (float)$range['start'] && $ipFloat <= (float)$range['end']) {
                return [
                    'country_code' => $range['code'],
                    'country_name' => $range['name']
                ];
            }
        }

        // 2. 国別IP判定
        foreach ($this->countryRanges as $range) {
            if ($ipFloat >= (float)$range['start'] && $ipFloat <= (float)$range['end']) {
                return [
                    'country_code' => $range['code'],
                    'country_name' => $range['name']
                ];
            }
        }

        return [
            'country_code' => 'OTHER',
            'country_name' => 'International/Other'
        ];
    }
}
