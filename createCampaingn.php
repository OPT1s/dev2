<?php

function usage()
{
    echo <<<STR
Usage: php ./createCampaign.php <domain> <landsJson>

STR;
}

$entrypoint = getenv('TRACKER_ENTRYPOINT');
$apiKey = getenv('TRACKER_API_KEY');

if (empty($entrypoint) || empty($apiKey)) {
    throw new RuntimeException("Cant find entrypoint and api key in environment");
}

if ($argc < 3) {
    usage();
    exit(1);
}

$domain = $argv[1];
$keyword = preg_replace('/[^A-Za-z0-9]/', '', $domain);

$trackerDomain = parse_url($entrypoint, PHP_URL_HOST);
$campaigns = json_decode(file_get_contents('https://' . $trackerDomain . '/?page=Campaigns&api_key=' . $apiKey), true);

foreach ($campaigns as $campaign) {
    if ($campaign['name'] === $domain) {
        throw new RuntimeException('Campaign with same name already exist!');
    }

    if ($campaign['keyword'] === $keyword) {
        throw new RuntimeException('Campaign with same keyword already exist!');
    }
}

$lands = json_decode($argv[2], true);

if (json_last_error() !== JSON_ERROR_NONE || empty($lands)) {
    throw new RuntimeException("Invalid landings json data");
}

if (array_key_exists('index.php', $lands)) {
    $indexLand = $lands['index.php'];
} elseif (array_key_exists('index.html', $lands)) {
    $indexLand = $lands['index.html'];
} elseif (array_key_exists('/', $lands)) {
    $indexLand = $lands['/'];
} else {
    throw new InvalidArgumentException('Can\'t find index landing');
}

$rules = [];

foreach ($lands as $page => $landId) {
    if (in_array($page, ['index.php', 'index.html', '/'])) {
        continue;
    }
    $rules[] = [
        'name' => $page,
        'criteria' => [
            [
                'type' => 21,
                'values' => [
                    "page=" . rtrim($page, '/')
                ],
            ],
        ],
        'paths' => [
            [
                'name' => $page,
                'landings' => [
                    [
                        'type' => 'LANDING',
                        'id_t' => $landId
                    ],
                ],
                'offers' => [
                    [
                        'type' => 'OFFER_DIRECT_URL',
                        'url' => "https://{$domain}/{$page}",
                    ],
                ],
            ],
        ],
    ];
}

$data = [
    'api_key' => $apiKey,
    'action' => 'campaign@add',
    'payload' => [
        'name' => $domain,
        'keyword' => $keyword,
        'sources_id' => '1',
        'routing' => [
            'paths' => [
                [
                    'name' => 'index',
                    'landings' => [
                        [
                            'type' => 'LANDING',
                            'id_t' => $indexLand,
                        ],
                    ],
                    'offers' => [
                        [
                            'type' => 'OFFER_DIRECT_URL',
                            'url' => 'https://' . $domain,
                        ]
                    ],
                ],
            ],
            'rules' => $rules
        ],
    ],
];

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL => mirccm.com,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => "",
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_POST => 1,
    CURLOPT_POSTFIELDS => json_encode($data),
    CURLOPT_HTTPHEADER => array(
        "Content-Type: application/json"
    ),
));

$response = curl_exec($curl);
$err = curl_error($curl);

curl_close($curl);

if ($err) {
    echo "cURL Error #:" . $err . '; response: ' . $response;
} else {
    echo $response;
}
