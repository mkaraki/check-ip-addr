<?php

// Reference:
//  - http://taiyaki.seesaa.net/article/450972796.html
//  - https://www.rfc-editor.org/rfc/rfc7597

// This can guess access from MAP-E network (false positive 15/4096)

function get_map_e_v6plus_ports(string $v6_addr): array|false {
    $addr_bin = inet_pton($v6_addr);

    if ($addr_bin === false) {
        return false;
    }

    $octet_4 = ord($addr_bin[6]) << 8 + ord($addr_bin[7]);
    $psid = dechex(($octet_4 & 0xff00) >> 8);

    $ports = [];

    for ($i = 1; $i <= 0xf; $i++) {
        for ($j = 0; $j <= 0xf; $j++) {
            $port_hex = dechex($i) . $psid . dechex($j);
            $ports[] = hexdec($port_hex);
        }
    }

    return $ports;
}

function get_map_e_ocnvc_ports(string $v6_addr): array|false {
    $addr_bin = inet_pton($v6_addr);

    if ($addr_bin === false) {
        return false;
    }

    $octet_4 = ord($addr_bin[6]) << 8 + ord($addr_bin[7]);
    $psid = ($octet_4 & 0x3f00) >> 8;

    $ports = [];

    for ($i = 1; $i <= 0b111111 /* 6 offset bits */; $i++) {
        for ($j = 0; $j <= 0b1111 /* 16 - 6 - 6 = 4 bits */; $j++) {
            $port_hex = dechex(($i << 10) +
                ($psid << 4) +
                $j);
            $ports[] = hexdec($port_hex);
        }
    }

    return $ports;
}

