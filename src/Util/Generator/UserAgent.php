<?php
/** @noinspection PhpUnused */
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Generator;

use function sprintf;
use function trim;

class UserAgent
{
    /**
     * Generator helper based schema of user agent
     * @link https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/User-Agent
     *
     * @param string $systemInformation
     * @param string $platform
     * @param string $platformDetails
     * @param string $extensions
     *
     * @return string
     */
    public function generate(
        string $systemInformation,
        string $platform = '',
        string $platformDetails = '',
        string $extensions = ''
    ) : string {
        return trim(
            sprintf(
                'Mozilla/5.0 (%1$s) %2$s %3$s %4$s',
                $systemInformation,
                $platform,
                $platformDetails,
                $extensions
            )
        );
    }

    public function bot(string $botName, string $version, string $botPageUrl) : string
    {
        // Mozilla/5.0 (compatible; MyProvider/1.0; +https://example.com/bot.html)
        return $this->generate(
            sprintf(
                'compatible; %1$s/%2$s; +%3$s',
                $botName,
                $version,
                $botPageUrl
            )
        );
    }

    public function googleBot(string $version = '2.1') : string
    {
        // Mozilla/5.0 (compatible; Googlebot/2.1; +https://www.google.com/bot.html)
        return $this->bot(
            'Googlebot',
            $version,
            'https://www.google.com/bot.html'
        );
    }

    public function yandexBot(string $version = '3.0') : string
    {
        // Mozilla/5.0 (compatible; YandexForDomain/3.0; +http://yandex.com/bots)
        /** @noinspection HttpUrlsUsage */
        return $this->bot(
            'YandexForDomain',
            $version,
            'http://yandex.com/bots'
        );
    }

    public function chrome(
        string $system,
        string $browserVersion,
        string $engineVersion
    ) : string {
        // Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko)
        // Chrome/106.0.0.0 Safari/537.36
        return $this->generate(
            $system,
            sprintf('AppleWebKit/%s', $engineVersion),
            '(KHTML, like Gecko)',
            sprintf('Chrome/%1$s Safari/%2$s', $browserVersion, $engineVersion)
        );
    }

    public function chromeMobile(
        string $system,
        string $browserVersion,
        string $engineVersion
    ) : string {
        // Mozilla/5.0 (Linux; Android 10) AppleWebKit/537.36 (KHTML, like Gecko)
        // Chrome/105.0.5195.136 Mobile Safari/537.36
        return $this->generate(
            $system,
            sprintf('AppleWebKit/%s', $engineVersion),
            '(KHTML, like Gecko)',
            sprintf('Chrome/%1$s Mobile Safari/%2$s', $browserVersion, $engineVersion)
        );
    }

    public function safari(
        string $system,
        string $browserVersion,
        string $engineVersion
    ) : string {
        // Mozilla/5.0 (Macintosh; Intel Mac OS X 12_6) AppleWebKit/605.1.15 (KHTML, like Gecko)
        // Version/16.0 Safari/605.1.15
        return $this->generate(
            $system,
            sprintf('AppleWebKit/%s', $engineVersion),
            '(KHTML, like Gecko)',
            sprintf('Version/%1$s Safari/%2$s', $browserVersion, $engineVersion)
        );
    }

    public function safariMobile(
        string $system,
        string $browserVersion,
        string $engineVersion,
        string $firmwareBuildNumber = '15E148'
    ) : string {
        // Mozilla/5.0 (iPhone; CPU iPhone OS 16_0_2 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko)
        // Version/16.0 Mobile/15E148 Safari/604.1
        return $this->generate(
            $system,
            sprintf('AppleWebKit/%s', $engineVersion),
            '(KHTML, like Gecko)',
            sprintf(
                'Version/%1$s Mobile/%2$s Safari/%3$s',
                $browserVersion,
                $firmwareBuildNumber,
                $engineVersion
            )
        );
    }

    public function opera(
        string $system,
        string $browserVersion,
        string $engineVersion,
        string $operaVersion
    ) : string {
        // Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko)
        // Chrome/106.0.0.0 Safari/537.36 OPR/91.0.4516.20
        return $this->generate(
            $system,
            sprintf('AppleWebKit/%s', $engineVersion),
            '(KHTML, like Gecko)',
            sprintf(
                'Chrome/%1$s Safari/%2$s OPR/%3$s',
                $browserVersion,
                $engineVersion,
                $operaVersion
            )
        );
    }

    public function operaMobile(
        string $system,
        string $browserVersion,
        string $engineVersion,
        string $operaVersion
    ) : string {
        // Mozilla/5.0 (Linux; Android 10; VOG-L29) AppleWebKit/537.36 (KHTML, like Gecko)
        // Chrome/105.0.5195.136 Mobile Safari/537.36 OPR/63.3.3216.58675
        return $this->generate(
            $system,
            sprintf('AppleWebKit/%s', $engineVersion),
            '(KHTML, like Gecko)',
            sprintf(
                'Chrome/%1$s Mobile Safari/%2$s OPR/%3$s',
                $browserVersion,
                $engineVersion,
                $operaVersion
            )
        );
    }

    public function firefox(
        string $system,
        string $browserVersion,
        string $engineVersion = '20100101'
    ) : string {
        // Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:110.0) Gecko/20100101 Firefox/110.0
        return $this->generate(
            $system,
            sprintf('Gecko/%s', $engineVersion),
            sprintf('Firefox/%s', $browserVersion)
        );
    }

    /**
     * User agent for firefox mobile
     *
     * @param string $system
     * @param string $browserVersion
     * @param string $engineVersion
     * @return string
     */
    public function firefoxMobile(
        string $system,
        string $browserVersion,
        string $engineVersion = '110.0'
    ) : string {
        // Mozilla/5.0 (Android 13; Mobile; rv:109.0) Gecko/110.0 Firefox/110.0
        return $this->generate(
            $system,
            sprintf('Gecko/%s', $engineVersion),
            sprintf('Firefox/%s', $browserVersion)
        );
    }

    /**
     * Use agent for firefox os
     *
     * @param string $system
     * @param string $browserVersion
     * @param string $engineVersion
     * @param string $firmwareBuildNumber
     * @return string
     */
    public function firefoxIOS(
        string $system,
        string $browserVersion,
        string $engineVersion,
        string $firmwareBuildNumber = '15E148'
    ) : string {
        // Mozilla/5.0 (iPhone; CPU iPhone OS 12_6 like Mac OS X)
        // AppleWebKit/605.1.15 (KHTML, like Gecko) FxiOS/105.0 Mobile/15E148 Safari/605.1.15
        return $this->generate(
            $system,
            sprintf('AppleWebKit/%s', $engineVersion),
            '(KHTML, like Gecko)',
            sprintf(
                'FxiOS/%1$s Mobile/%2$s Safari/%3$s',
                $browserVersion,
                $firmwareBuildNumber,
                $engineVersion
            )
        );
    }
}
