<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Whois;

use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use function curl_close;
use function curl_errno;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt_array;
use function curl_version;
use function fclose;
use function feof;
use function fopen;
use function fputs;
use function fread;
use function fseek;
use function fsockopen;
use function fstat;
use function function_exists;
use function fwrite;
use function in_array;
use function is_resource;
use function parse_url;
use function sprintf;
use function stream_get_meta_data;
use function strtolower;
use function trim;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_FOLLOWLOCATION;
use const CURLOPT_INFILE;
use const CURLOPT_INFILESIZE;
use const CURLOPT_NOPROGRESS;
use const CURLOPT_PORT;
use const CURLOPT_PROTOCOLS;
use const CURLOPT_READFUNCTION;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;
use const CURLOPT_VERBOSE;
use const CURLPROTO_HTTP;
use const CURLPROTO_HTTPS;
use const CURLPROTO_TELNET;

class SocketRequest
{
    protected static ?bool $usSocket = null;

    protected array $acceptedProtocol = [];

    public function __construct()
    {
        self::$usSocket ??= function_exists('fsockopen');
        $this->acceptedProtocol = curl_version()['protocols'];
    }

    /**
     * @param string $server
     * @return array{scheme: string, port: int, host: string}
     */
    public function determineServer(string $server): array
    {
        $parsed = parse_url($server);
        $host = $parsed['host']??$parsed['path'];
        $scheme = $parsed['scheme']??null;
        if (!isset($parsed['port'])) {
            $port = $scheme === 'https' ? 443 : (
            $scheme === 'http' ? 80 : 43
            );
        } else {
            $port = (int)$parsed['port'];
        }
        return [
            'scheme' => $scheme??'telnet',
            'port' => $port,
            'host' => $host
        ];
    }


    /**
     * @param string $server
     * @param string $domain
     * @param int $timeout
     * @return array{error: array{code: int, message: ?string}, info: array, result: ?string}
     */
    public function socketRequest(
        string $server,
        string $domain,
        int $timeout = 15
    ) : array {
        $server = $this->determineServer($server);
        $fp = fsockopen(
            $server['host'],
            $server['port'],
            $errorCode,
            $errorMessage,
            $timeout
        );
        $result = false;
        $info = false;
        if ($errorCode === 0) {
            fputs($fp, "$domain\r\n");
            $result = '';
            while (!feof($fp)) {
                $result .= fread($fp, 1024);
            }
            $info = stream_get_meta_data($fp);
        }

        if (is_resource($fp)) {
            fclose($fp);
        }
        return [
            'error' => [
                'code' => $errorCode,
                'message' => $errorMessage?:null,
            ],
            'info' => $info?:[],
            'result' => $result === false ? null : $result,
        ];
    }

    /**
     * @param string $server
     * @param string $domain
     * @param int $timeout
     * @return array{error: array{code: int, message: ?string}, info: array, result: ?string}
     */
    public function curlRequest(string $server, string $domain, int $timeout = 15) : array
    {
        $server = $this->determineServer($server);
        if (!in_array($server['scheme'], $this->acceptedProtocol)) {
            throw new RuntimeException(
                sprintf('Protocol "%s" is not supported.', $server['scheme'])
            );
        }

        $fp = fopen("php://temp", 'r+');
        $domain = strtolower(trim($domain));
        fwrite($fp, "$domain\r\n");
        fseek($fp, 0);
        $size = fstat($fp)['size'];
        $ch = curl_init();
        $host = "{$server['scheme']}://{$server['host']}";
        if ($server['port'] === 43) {
            $host .= ":43";
        }
        curl_setopt_array($ch, [
            CURLOPT_URL => $host,
            CURLOPT_PORT => $server['port'],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_PROTOCOLS => match ($server['scheme']) {
                'https' => CURLPROTO_HTTPS,
                'http' => CURLPROTO_HTTP,
                default => CURLPROTO_TELNET
            },
            CURLOPT_NOPROGRESS => true,
            CURLOPT_INFILE => $fp,
            CURLOPT_INFILESIZE => $size,
            CURLOPT_VERBOSE => false,
            CURLOPT_READFUNCTION => function ($ch, $fh, $length = false) {
                return fread($fh, $length?:0);
            }
        ]);
        $result = curl_exec($ch);

        fclose($fp);

        $error = curl_error($ch);
        $errorCode = curl_errno($ch);
        $info = curl_getinfo($ch);
        curl_close($ch);
        return [
            'error' => [
                'code' => $errorCode,
                'message' => $error?:null,
            ],
            'info' => $info?:[],
            'result' => $result === false ? null : $result,
        ];
    }

    /**
     * @param string $server
     * @param string $domain
     * @param int $timeout
     * @return array{error: array{code: int, message: ?string}, info: array, result: ?string}
     */
    public function doRequest(string $server, string $domain, int $timeout = 15): array
    {
        return self::$usSocket
            ? $this->socketRequest($server, $domain, $timeout)
            : $this->curlRequest($server, $domain, $timeout);
    }
}
