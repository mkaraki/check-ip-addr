<?php
require_once __DIR__ . '/json.php';
global $result;

const V4_ENDPOINT = 'http://127.0.0.1:8080/';
const V6_ENDPOINT = 'http://[::1]:8080/';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <title>IP address</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link href="style.css" rel="stylesheet" />
</head>
<body>
<header>
    <div class="grand-title-bar">
        <div class="left">
            IP address checker
        </div>
        <div class="right">
            &nbsp;
        </div>
    </div>
    <nav class="top-level-nav">
        <a href="#">IP</a>
        <a href="#">TCP</a>
        <a href="#">HTTP</a>
    </nav>
</header>

<div class="container-fluid">
    <div class="row main-section">
        <div class="col-9 content">
            <main>
                <h2>IPv4 Info</h2>
                <table>
                    <tbody id="tbody-v4">
                    <tr><th>Status</th><td>No data</td></tr>
                    <?php if ($result['contact_family'] === 'ip4'): ?>
                        <?php foreach ($result['v4'] as $key => $value): ?>
                            <tr>
                                <th><?= $key ?></th>
                                <?php if (get_resource_type($value) === 'string'): ?>
                                    <td><?= htmlentities($value) ?></td>
                                <?php elseif (get_resource_type($value) === 'integer'): ?>
                                    <td><?= $value ?></td>
                                <?php elseif (get_resource_type($value) === 'array') : ?>
                                    <td><?= htmlentities(var_export($value, true)) ?></td>
                                <?php else : ?>
                                    <td>Unable to display</td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                <h2>IPv6 Info</h2>
                <table>
                    <tbody id="tbody-v6">
                    <tr><th>Status</th><td>No data</td></tr>
                    <?php if ($result['contact_family'] === 'ip6'): ?>
                        <?php foreach ($result['v6'] as $key => $value): ?>
                            <tr>
                                <th><?= $key ?></th>
                                <?php if (is_string($value)): ?>
                                    <td><?= htmlentities($value) ?></td>
                                <?php elseif (is_int($value)): ?>
                                    <td><?= $value ?></td>
                                <?php elseif (is_array($value)) : ?>
                                    <td><?= htmlentities(var_export($value, true)) ?></td>
                                <?php else : ?>
                                    <td>Unable to display</td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
                <h2>MAP-E Tunnel Info</h2>
                <table>
                    <tbody id="tbody-map-e">
                    <tr><th>Status</th><td>No data</td></tr>
                    </tbody>
                </table>
            </main>
        </div>
        <div class="col-3 help">
            <h2>ASN/Country/City</h2>
            <hr />
            IPアドレスのAS番号、国、市の情報をGeoLite2から取得します。
            <hr />
            <h2>MAP-E Tunnel Info</h2>
            <hr />
            OCN Virtual Connectとv6プラスのMAP-E接続について、簡易的な判定を行います。
        </div>
    </div>
</div>
<script>
    const v4Info = <?= json_encode($result['v4'] ?? []) ?>;
    const isV4Available = <?= $result['contact_family'] === 'ip4' ? 'true' : 'false' ?>;
    const v6Info = <?= json_encode($result['v6'] ?? []) ?>;
    const isV6Available = <?= $result['contact_family'] === 'ip6' ? 'true' : 'false' ?>;

    function applyInfoToTable(info, tbody) {
        tbody.innerHTML = '';
        for (const [key, value] of Object.entries(info)) {
            const tr = document.createElement('tr');
            const th = document.createElement('th');
            th.textContent = key;
            const td = document.createElement('td');
            if (typeof value === 'string') {
                td.textContent = value;
            } else if (typeof value === 'number') {
                td.textContent = value;
            } else if (typeof value === 'object') {
                td.textContent = JSON.stringify(value);
            } else {
                td.textContent = 'Unable to display';
            }
            tr.appendChild(th);
            tr.appendChild(td);
            tbody.appendChild(tr);
        }
    }

    function applyInfo(info) {
        if (info.v4) {
            applyInfoToTable(info.v4, document.getElementById('tbody-v4'));
        }
        if (info.v6) {
            applyInfoToTable(info.v6, document.getElementById('tbody-v6'));
        }
        if (info['map-e']) {
            applyInfoToTable(info['map-e'], document.getElementById('tbody-map-e'));
        }
    }

    if (!isV4Available && isV6Available) {
        fetch('<?= V4_ENDPOINT ?>/json.php?chained_family=ip6&chained_addr=' + v6Info.address + '&chained_port=' + v6Info.port)
            .then(response => response.json())
            .then(data => {
                applyInfo(data);
            });
    }

    if (!isV6Available && isV4Available) {
        fetch('<?= V6_ENDPOINT ?>/json.php?chained_family=ip4&chained_addr=' + v4Info.address + '&chained_port=' + v4Info.port)
            .then(response => response.json())
            .then(data => {
                applyInfo(data);
            });
    }
</script>
</body>
</html>
