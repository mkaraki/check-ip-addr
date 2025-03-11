<?php
require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/_config.php';
require_once __DIR__ . '/utils/ntt_map-e.php';

function get_addr_info(string $address, int $port): array|false {
    if (!filter_var($address, FILTER_VALIDATE_IP)) {
        return false;
    }

    $return_obj = [];

    $return_obj['address'] = $address;
    $return_obj['port'] = $port;

    $return_obj['ptr'] = gethostbyaddr($address);

    $geoAsn = new GeoIp2\Database\Reader(GEO_IP_DIR . '/GeoLite2-ASN.mmdb');
    $geoCity = new GeoIp2\Database\Reader(GEO_IP_DIR . '/GeoLite2-City.mmdb');

    try {
        $asInfo = $geoAsn->asn($address);
        $return_obj['asn'] = $asInfo->autonomousSystemNumber;
        $return_obj['as_org'] = $asInfo->autonomousSystemOrganization;
    }
    catch (Exception $e) {
        $return_obj['asn'] = 0;
        $return_obj['as_org'] = 'Unknown';
    }
    try {
        $cityInfo = $geoCity->city($address);
        $return_obj['country'] = $cityInfo->country->isoCode;
        $return_obj['city'] = $cityInfo->city->name ?? 'Unknown';
    }
    catch (Exception $e) {
        $return_obj['country'] = 'XX';
        $return_obj['city'] = 'Unknown';
    }

    return $return_obj;
}

$client_ip_addr = $_SERVER['REMOTE_ADDR'];
$client_port = $_SERVER['REMOTE_PORT'];

$is_v4 = filter_var($client_ip_addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
$is_v6 = filter_var($client_ip_addr, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);

if (
    $is_v4 === false &&
    $is_v6 === false
) {
    die('{"error": "Not supported IP address family"}');
}

$is_chained = isset($_GET['chained_family']) && isset($_GET['chained_addr']) && isset($_GET['chained_port']);

$result = [
    'contact_family' => $is_v4 ? 'ip4' : 'ip6',
    'is_chained' => $is_chained,
];

if ($is_v4) {
    $result['v4'] = get_addr_info($client_ip_addr, $client_port);
}
if ($is_v6) {
    $result['v6'] = get_addr_info($client_ip_addr, $client_port);
}

if ($is_chained) {
    if (!filter_var($_GET['chained_port'], FILTER_VALIDATE_INT)) {
        die('{"error": "Invalid chained port"}');
    }
    $chain_port = intval($_GET['chained_port']);

    $is_chained_v4 = false;
    $is_chained_v6 = false;

    switch ($_GET['chained_family']) {
        case "ip4":
            $is_chained_v4 = filter_var($_GET['chained_addr'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            if ($is_chained_v4 === false) {
                die('{"error": "Invalid chained address"}');
            }
            break;

        case "ip6":
            $is_chained_v6 = filter_var($_GET['chained_addr'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6);
            if ($is_chained_v6 === false) {
                die('{"error": "Invalid chained address"}');
            }
            break;

        default:
            die('{"error": "Invalid chain information"}');
    }

    $v4_info = null;
    $v6_info = null;
    if ($is_chained_v4) {
        $v4_info = get_addr_info($_GET['chained_addr'], $chain_port);
    }
    if ($is_v4) {
        $v4_info = get_addr_info($client_ip_addr, $client_port);
    }
    if ($is_chained_v6) {
        $v6_info = get_addr_info($_GET['chained_addr'], $chain_port);
    }
    if ($is_v6) {
        $v6_info = get_addr_info($client_ip_addr, $client_port);
    }

    $result['v4'] = $v4_info;
    $result['v6'] = $v6_info;

    // ================================================================
    //  MAP-E
    // ================================================================

    if (
        $result['v4'] !== null && $result['v6'] !== null &&
        $v4_info['asn'] === 4713 /* OCN */ &&
        $v4_info['country'] === 'JP' &&
        $v6_info['asn'] === 4713 /* OCN */ &&
        $v6_info['country'] === 'JP' &&
        str_ends_with($v4_info['ptr'], '.ipoe.ocn.ne.jp')
    ) {
        $v4_ports = get_map_e_ocnvc_ports($v6_info['address']);
        $is_mape_port = in_array($v4_info['port'], $v4_ports);

        $result['map-e']['status'] = 'Known';
        $result['map-e']['corp'] = 'OCN';
        $result['map-e']['ports'] = $v4_ports;
        $result['map-e']['is_map-e_port'] = $is_mape_port;
    }
    else if (
        $result['v4'] !== null && $result['v6'] !== null &&
        $v4_info['asn'] === 2516 /* KDDI */ &&
        $v4_info['country'] === 'JP' &&
        $v6_info['country'] === 'JP' &&
        str_ends_with($v4_info['ptr'], '.v4.enabler.ne.jp')
    ) {
        $v4_ports = get_map_e_v6plus_ports($v6_info['address']);
        $is_mape_port = in_array($v4_info['port'], $v4_ports);

        $result['map-e']['status'] = 'Known';
        $result['map-e']['corp'] = 'KDDI';
        $result['map-e']['ports'] = $v4_ports;
        $result['map-e']['is_map-e_port'] = $is_mape_port;
    }
    else if (
        $result['v4'] !== null && $result['v6'] !== null
    ) {
        $result['map-e']['status'] = 'Unknown';
    } else {
        $result['map-e']['status'] = 'Not available. MAP-E requires both IPv4 and IPv6 addresses.';
    }
}

if (basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    echo json_encode($result, JSON_PRETTY_PRINT);
}
