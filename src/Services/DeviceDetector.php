<?php
namespace App\Services;

use Detection\MobileDetect;

class DeviceDetector
{
    public static function detect(): array
    {
        $ua     = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $detect = new MobileDetect();

        if ($detect->isMobile()) {
            $type = 'mobile';
        } elseif ($detect->isTablet()) {
            $type = 'tablet';
        } else {
            $type = 'desktop';
        }

        $browser = 'Unknown';
        if (str_contains($ua, 'Edg'))          { $browser = 'Edge'; }
        elseif (str_contains($ua, 'OPR'))      { $browser = 'Opera'; }
        elseif (str_contains($ua, 'Firefox'))  { $browser = 'Firefox'; }
        elseif (str_contains($ua, 'Chrome'))   { $browser = 'Chrome'; }
        elseif (str_contains($ua, 'Safari'))   { $browser = 'Safari'; }

        $os = 'Unknown';
        if (str_contains($ua, 'Windows'))                              { $os = 'Windows'; }
        elseif (str_contains($ua, 'Android'))                          { $os = 'Android'; }
        elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) { $os = 'iOS'; }
        elseif (str_contains($ua, 'Mac'))                              { $os = 'macOS'; }
        elseif (str_contains($ua, 'Linux'))                            { $os = 'Linux'; }

        return [
            'device_type' => $type,
            'browser'     => $browser,
            'os'          => $os,
        ];
    }
}
