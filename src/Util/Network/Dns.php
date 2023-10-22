<?php
declare(strict_types=1);

namespace ArrayAccess\TrayDigita\Util\Network;

use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\EmptyArgumentException;
use ArrayAccess\TrayDigita\Exceptions\InvalidArgument\InvalidArgumentException;
use ArrayAccess\TrayDigita\Exceptions\Logical\OutOfRangeException;
use ArrayAccess\TrayDigita\Exceptions\Runtime\RuntimeException;
use function array_filter;
use function array_map;
use function array_merge;
use function array_search;
use function array_shift;
use function base64_encode;
use function error_clear_last;
use function explode;
use function fclose;
use function fread;
use function fsockopen;
use function fwrite;
use function implode;
use function is_array;
use function is_string;
use function ord;
use function pack;
use function parse_url;
use function rand;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_replace;
use function strlen;
use function strtoupper;
use function substr;
use function trim;
use function unpack;

class Dns
{
    const TYPE = [
        1 => "A", // RFC1035
        2 => "NS", // RFC1035
        5 => "CNAME", // RFC1035
        6 => "SOA", // RFC1035 RFC2308
        12 => "PTR", // RFC1035
        13 => "HINFO",
        14 => "MINFO",
        15 => "MX", // RFC1035 RFC7505
        16 => "TXT", // RFC1035
        17 => "RP", // RFC1183
        18 => "AFSDB", // RFC1183 RFC5864
        19 => "X25", // RFC1183
        20 => "ISDN", // RFC1183
        21 => "RT", // RFC1183
        22 => "NSAP", // RFC1706
        23 => "NSAP-PTR", // RFC1348 RFC1637 RFC1706
        24 => "SIG", // RFC4034 RFC3755 RFC2535 RFC2536 RFC2537 RFC3008 RFC3110
        25 => "KEY", // RFC2930 RFC4034 RFC2535 RFC2536 RFC2537 RFC3008 RFC3110
        26 => "PX", // RFC2136
        27 => "GPOS", // RFC1712
        28 => "AAAA", // RFC3596
        29 => "LOC", // RFC1876
        31 => "EID",
        32 => "NIMLOC",
        33 => "SRV", // RFC2782
        34 => "ATMA",
        35 => "NAPTR", // RFC3403
        36 => "KX", // RFC2230
        37 => "CERT", // RFC4398
        39 => "DNAME", // RFC2672
        40 => "SINK",
        41 => "OPT", // RFC6891 RFC3658
        42 => "APL",
        43 => "DS", // RFC4034 RFC3658
        44 => "SSHFP", // RFC4255
        45 => "IPSECKEY", // RFC4025
        46 => "RRSIG", // RFC4034 RFC3755
        47 => "NSEC", // RFC4034 RFC3755
        48 => "DNSKEY", // RFC4034 RFC3755
        49 => "DHCID", // RFC4701
        50 => "NSEC3", // RFC5155
        51 => "NSEC3PARAM", // RFC5155
        52 => "TLSA", // RFC6698
        55 => "HIP", // RFC5205
        56 => "NINFO",
        57 => "RKEY",
        58 => "TALINK",
        59 => "CDS", // RFC7344
        60 => "CDNSKEY", // RFC7344
        61 => "OPENPGPKEY", // internet draft
        62 => "CSYNC", // RFC7477
        99 => "SPF", // RFC4408 RFC7208
        100 => "UNIFO", // IANA Reserved
        101 => "UID", // IANA Reserved
        102 => "GID", // IANA Reserved
        103 => "UNSPEC", // IANA Reserved
        104 => "NID", // RFC6742
        105 => "L32", // RFC6742
        106 => "L64", // RFC6742
        107 => "LP", // RFC6742
        108 => "EUI48", // RFC7043
        109 => "EUI64", // RFC7043
        249 => "TKEY", // RFC2930
        250 => "TSIG", // RFC2845
        251 => "IXFR", // RFC1995
        252 => "AXFR", // RFC1035 RFC5936
        253 => "MAILB", // RFC1035
        254 => "MAILA", // RFC1035
        255 => "ANY", // RFC1035 RFC6895
        256 => "URI", // RFC7553
        257 => "CAA", // RFC6844
        32768 => "TA",
        32769 => "DLV",
        65534 => "TYPE65534", // Eurid uses this one?
    ];

    final const DEFAULT_NAMESERVER = [
        '1.1.1.1',
        '8.8.8.8',
        '8.8.4.4',
        '1.1.0.0'
    ];

    public static function record(
        string $type,
        string $host,
        string|array|null $server = null,
        int $timeout = 5,
        &$is_authoritative = null
    ): array {
        $type = strtoupper(trim($type));
        if (!($idType = array_search($type, self::TYPE))) {
            throw new InvalidArgumentException(
                sprintf('Dns type %s is not valid', $type)
            );
        }

        $parsed = parse_url(trim($host));
        $host = $parsed['host']??$parsed['path']??null;
        if (is_string($host) && !isset($parsed['scheme'])) {
            $host = str_replace('\\', '/', $host);
            $host = trim($host, '/');
            $host = explode('/', $host, 2);
            $host = array_shift($host);
        }
        if (!$host) {
            throw new EmptyArgumentException(
                'Hostname is invalid'
            );
        }

        $socket = self::createSocket($server, $timeout, $useServer);
        $labels = explode('.', $host);
        $question_binary = '';
        foreach ($labels as $label) {
            $question_binary .= pack("C", strlen($label)); // size byte first
            $question_binary .= $label; // add label
        }
        $question_binary .= pack("C", 0); // end it off
        $id = rand(1, 255)|(rand(0, 255)<<8); // generate the ID
        // Set standard codes and flags
        $flags = (0x0100 & 0x0300) | 0x0020; // recursion & queryspecmask | authenticated data

        $opcode = 0x0000; // opcode
        // Build the header
        $domainLabel = pack("n", $id);
        $domainLabel .= pack("n", $opcode | $flags);
        $domainLabel .= pack("nnnn", 1, 0, 0, 0);
        $domainLabel .= $question_binary;
        $domainLabel .= pack("n", $idType);
        $domainLabel .= pack("n", 0x0001); // internet class
        $headerSize = strlen($domainLabel);
        // $headersizebin = pack("n", $headerSize);
        $written = fwrite($socket, $domainLabel, $headerSize);
        if ($written === false) {
            fclose($socket);
            throw new RuntimeException(
                "Could not communicate DNS query"
            );
        }
        $rawBuffer = fread($socket, 4096);
        fclose($socket);
        if (strlen($rawBuffer) < 12) {
            throw new OutOfRangeException(
                "DNS query return buffer too small"
            );
        }
        $pos = 0;
        $domainLabel = unpack(
            "nid/nflags/nqdcount/nancount/nnscount/narcount",
            self::readBufferLength(12, $rawBuffer, $pos)
        );
        if (!isset($domainLabel['flags'])) {
            throw new RuntimeException(
                "Could read dns flags"
            );
        }
        $flags = sprintf("%016b\n", $domainLabel['flags']);
        // No answers
        if (!$domainLabel['ancount']) {
            return [];
        }

        $is_authoritative = $flags[5] === 1;

        // Question section
        if ($domainLabel['qdcount']) {
            // Skip name
            self::readName($rawBuffer, $pos);
            // skip question part
            $pos += 4; // 4 => QTYPE + QCLASS
        }

        $responses = [];

        for ($a = 0; $a < $domainLabel['ancount']; $a++) {
            $host = self::readName($rawBuffer, $pos); // Skip name
            $ans_header = unpack("ntype/nclass/Nttl/nlength", self::readBufferLength(10, $rawBuffer, $pos));
            $ans_header = !is_array($ans_header) ? [] : $ans_header;
            $resType = self::TYPE[$ans_header['type']] ?? null;
            if ($type !== 'ANY' && $resType !== $type) {
                // Skip type that was not requested
                $resType = null;
            }
            // https://www.iana.org/assignments/dns-parameters/dns-parameters.xhtml
            $ans = match ($ans_header['class']) {
                0x0000,
                0xFFFF => 'RESERVED',
                0x0001 => 'IN',
                0x0002 => 'UNASSIGNED',
                0x0003 => 'CH',
                0x0004 => 'HS',
                0x00FE => 'NONE',
                0x00FF => 'ANY',
                default => null
            };
            if ($ans === null) {
                $ans = 'UNKNOWN';
                if ($ans_header['class'] >= 0x0005
                    && $ans_header['class'] <= 0x00FD
                ) {
                    $ans = 'UNASSIGNED';
                } elseif ($ans_header['class'] >= 0xFF00
                    && $ans_header['class'] <= 0xFFFE
                ) {
                    $ans = 'RESERVED';
                }
            }
            switch ($resType) {
                case 'A':
                    $responses[$resType][] = [
                        'host' => $host,
                        'ttl' => $ans_header['ttl'],
                        'class' => $ans,
                        'length' => $ans_header['length'],
                        'ip' => implode(
                            ".",
                            unpack(
                                "Ca/Cb/Cc/Cd",
                                self::readBufferLength(4, $rawBuffer, $pos)
                            )
                        )
                    ];
                    break;
                case 'AAAA':
                    $responses[$resType][] = [
                        'host' => $host,
                        'ttl' => $ans_header['ttl'],
                        'class' => $ans,
                        'length' => $ans_header['length'],
                        'ip' => implode(
                            ':',
                            unpack(
                                "H4a/H4b/H4c/H4d/H4e/H4f/H4g/H4h",
                                self::readBufferLength(16, $rawBuffer, $pos)
                            )
                        )
                    ];
                    break;
                case 'MX':
                    $priority = unpack('nprio', self::readBufferLength(2, $rawBuffer, $pos)); // priority
                    $responses[$resType][] = [
                        'host' => self::readName($rawBuffer, $pos),
                        'type' => $type,
                        'ttl' => $ans_header['ttl'],
                        'class' => $ans,
                        'length' => $ans_header['length'],
                        'priority' => $priority['prio'],
                    ];
                    break;
                case 'NS':
                case 'CNAME':
                case 'PTR':
                    $responses[$resType][] = [
                        'host' => self::readName($rawBuffer, $pos),
                        'type' => $type,
                        'ttl' => $ans_header['ttl'],
                        'class' => $ans,
                        'length' => $ans_header['length'],
                    ];
                    break;
                case 'TXT':
                    $responses[$resType][] = [
                        'host' => $host,
                        'type' => $type,
                        'ttl' => $ans_header['ttl'],
                        'class' => $ans,
                        'length' => $ans_header['length'],
                        'value' => self::readBufferLength(
                            (int) $ans_header['length'],
                            $rawBuffer,
                            $pos
                        )
                    ];
                    break;
                case 'SOA':
                    $mname = self::readName($rawBuffer, $pos);
                    $rname = self::readName($rawBuffer, $pos);
                    $extras = unpack(
                        "Nserial/Nrefresh/Nretry/Nexpiry/Nminttl",
                        self::readBufferLength(20, $rawBuffer, $pos)
                    );
                    $responses[$resType][] = [
                        'host' => $host,
                        'type' => $type,
                        'class' => $ans,
                        'ttl' => $ans_header['ttl'],
                        'mname' => $mname,
                        'rname' => $rname,
                        'serial' => $extras['serial'],
                        'refresh' => $extras['refresh'],
                        'expire' => $extras['expiry'],
                        'minimum_ttl' => $extras['minttl'],
                    ];
                    break;
                case 'DNSKEY':
                    $stuff = self::readBufferLength(
                        (int) $ans_header['length'],
                        $rawBuffer,
                        $pos
                    );
                    $extras = unpack("nflags/Cprotocol/Calgorithm/a*pubkey", $stuff);
                    $flags = sprintf("%016b\n", $extras['flags']);
                    $ac = 0;
                    for ($i = 0; $i < $ans_header['length']; $i++) {
                        $keyPack = unpack("C", $stuff[$i]);
                        $ac += (($i & 1) ? $keyPack[1] : $keyPack[1] << 8);
                    }
                    $ac += ($ac >> 16) & 0xFFFF;
                    $keyTag = $ac & 0xFFFF;

                    $zoneKey = (int) $flags[7] === 1;
                    $zoneSep = (int) $flags[15] === 1;
                    $responses[$resType][] = [
                        'host' => $host,
                        'ttl' => $ans_header['ttl'],
                        'class' => $ans_header['class'],
                        'key' => $zoneKey,
                        'sep' => $zoneSep,
                        'protocol' => $extras['protocol'],
                        'algorithm' => $extras['algorithm'],
                        'public_key' => base64_encode($extras['pubkey']),
                        'key_id' => $keyTag,
                        'flags' => $extras['flags'],
                    ];
                    break;
                case 'DS':
                    $stuff = self::readBufferLength(
                        (int) $ans_header['length'],
                        $rawBuffer,
                        $pos
                    );
                    $length = (($ans_header['length'] - 4) * 2) - 8;
                    $stuff = unpack("nkeytag/Calgo/Cdigest/H" . $length . "string/H*rest", $stuff);
                    $responses[$resType][] = [
                        'host' => $host,
                        'ttl' => $ans_header['ttl'],
                        'class' => $ans_header['class'],
                        'key_tag' => $stuff['keytag'],
                        'algorithm' => $stuff['algo'],
                        'digest' => $stuff['digest'],
                        'rest' => strtoupper($stuff['rest']),
                        'string' => strtoupper($stuff['string']),
                    ];
                    break;
                case 'RRSIG':
                    $stuff = self::readBufferLength(18, $rawBuffer, $pos);
                    //$length = $ans_header['length'] - 18;
                    $test = unpack("ntype/calgorithm/clabels/Noriginalttl/Nexpiration/Ninception/nkeytag", $stuff);
                    $name = self::readName($rawBuffer, $pos);
                    $sig = self::readBufferLength($ans_header['length'] - (strlen($name) + 2) - 18, $rawBuffer, $pos);
                    $responses[$resType][] = [
                        'host' => $host,
                        'ttl' => $ans_header['ttl'],
                        'class' => $ans_header['class'],
                        'type' => $test['type'],
                        'labels' => $test['labels'],
                        'originalttl' => $test['originalttl'],
                        'expiration' => $test['expiration'],
                        'inception' => $test['inception'],
                        'keytag' => $test['keytag'],
                        'algorithm' => $test['algorithm'],
                        'signer' => $name,
                        'signature' => base64_encode($sig),
                    ];
                    break;
                default:
                    // Skip
                    $responses[$resType][] = [
                        'host' => $host,
                        'ttl' => $ans_header['ttl'],
                        'class' => $ans_header['class'],
                        'length' => $ans_header['length'],
                        'value' => self::readBufferLength(
                            (int) $ans_header['length'],
                            $rawBuffer,
                            $pos
                        )
                    ];
                    break;
            }
        }

        return $responses[$type];
    }

    protected static function readNamingPosition(int $offset, string $rawBuffer): array
    {
        $out = [];
        while (($len = ord(substr($rawBuffer, $offset, 1))) && $len > 0) {
            $out[] = substr($rawBuffer, $offset + 1, $len);
            $offset += $len + 1;
        }
        return $out;
    }

    protected static function readName(string $rawBuffer, int &$pos): string
    {
        $out = [];

        while (($len = ord(self::readBufferLength(1, $rawBuffer, $pos))) && $len > 0) {
            if ($len >= 64) {
                $offset = (($len & 0x3f) << 8) + ord(self::readBufferLength(1, $rawBuffer, $pos));
                $out = array_merge($out, self::readNamingPosition($offset, $rawBuffer));
                break;
            } else {
                $out[] = self::readBufferLength($len, $rawBuffer, $pos);
            }
        }

        return implode('.', $out);
    }

    protected static function readBufferLength(int $length, string $rawBuffer, int &$pos): string
    {
        $out = substr($rawBuffer, $pos, $length);
        $pos += $length;
        return $out;
    }

    protected static function createSocket(string|array|null $servers, int $timeout, &$useServer)
    {
        if (is_string($servers)) {
            $servers = [trim($servers)];
        }
        if (is_array($servers)) {
            $servers = array_map('trim', array_filter($servers, 'is_string'));
        }
        if (!is_array($servers) || empty($servers)) {
            $servers = self::DEFAULT_NAMESERVER;
        }
        $socket = null;
        $errorCode = null;
        $errorMessage = null;
        $useServer = null;
        foreach ($servers as $server) {
            $host = "udp://$server";
            $errCode = 0;
            $errMessage = null;
            set_error_handler(static function ($errorCode, $errorMessage) use (&$errCode, &$errMessage) {
                $errCode = $errorCode;
                $errMessage = $errorMessage;
                error_clear_last();
            });
            $socket = fsockopen($host, 53, $errno, $errstr, $timeout);
            restore_error_handler();
            if ($socket) {
                $useServer = $server;
                break;
            }
            $errorCode = $errno === 0 && $errCode !== 0 ? $errCode : $errno;
            $errorMessage = !$errstr && $errMessage !== null ? $errMessage : $errno;
        }
        if (!$socket) {
            throw new RuntimeException(
                $errorCode??0,
                $errorMessage??'There was an error while creating socket'
            );
        }
        return $socket;
    }
}
