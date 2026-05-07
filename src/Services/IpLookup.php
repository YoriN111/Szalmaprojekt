<?php
namespace App\Services;

class IpLookup
{
    private static array $privateRanges = ['127.', '10.', '192.168.', '172.16.', '::1'];

    public static function lookup(string $ip): array
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            return ['country' => '', 'city' => '', 'isp' => ''];
        }

        foreach (self::$privateRanges as $prefix) {
            if (str_starts_with($ip, $prefix)) {
                return ['country' => 'Local', 'city' => 'Local', 'isp' => 'Local'];
            }
        }

        $ctx  = stream_context_create(['http' => ['timeout' => 3]]);
        $json = @file_get_contents(
            "http://ip-api.com/json/{$ip}?fields=country,city,isp,status",
            false,
            $ctx
        );

        if (!$json) {
            return ['country' => '', 'city' => '', 'isp' => ''];
        }

        $data = json_decode($json, true);
        if (($data['status'] ?? '') !== 'success') {
            return ['country' => '', 'city' => '', 'isp' => ''];
        }

        return [
            'country' => $data['country'] ?? '',
            'city'    => $data['city']    ?? '',
            'isp'     => $data['isp']     ?? '',
        ];
    }
}
