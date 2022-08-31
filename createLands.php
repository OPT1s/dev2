<?php

function usage()
{
    echo <<<STR
Usage: php ./createLands.php <domain> <landsJson>

STR;
}

function sendRequest(string $entrypoint, string $method, array $data): array
{
    if ($method === 'GET') {
        $response = file_get_contents("{$entrypoint}?" . http_build_query($data));
    } elseif ($method === 'POST') {
        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $entrypoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ],
            CURLOPT_POSTFIELDS => json_encode($data)
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new Exception('CURL error: ' . curl_error($curl));
        }
    } else {
        throw new BadMethodCallException();
    }

    $result = json_decode($response, true);

    if (array_key_exists('error', $result)) {
        throw new Exception('Error: ' . $data['error']);
    }

    return $result;
}

$entrypoint = getenv('TRACKER_ENTRYPOINT');
$apiKey = getenv('TRACKER_API_KEY');

if ($argc < 3) {
    usage();
    exit(1);
}

$domain = filter_var($argv[1], FILTER_VALIDATE_DOMAIN);

if (false === $domain) {
    echo "Invalid domain name";
    exit(1);
}

$lands = json_decode($argv[2], true);

if (json_last_error() !== JSON_ERROR_NONE || empty($lands)) {
    echo "Invalid landings json data" . PHP_EOL;
    usage();
    exit(1);
}

$responseData = sendRequest($entrypoint, 'GET', ['api_key' => $apiKey, 'action' => 'landing@get_all']);

$alreadyExists = [];

foreach ($responseData as $land) {
    if (strpos($land['name'], $domain) !== false && !empty($land['group_lp'])) {
        $groupId = $land['group_lp'];
    }
    $alreadyExists[$land['name']] = $land['id'];
}

$result = [];

foreach ($lands as $page => $path) {

    $name = in_array($page, ['index.php', 'index.html', '/']) ? "$domain" : "$domain $page";

    if (array_key_exists($name, $alreadyExists)) {
        $result[$page] = $alreadyExists[$name];
        continue;
    }

    $data = [
        'api_key' => $apiKey,
        'action' => 'landing@add',
        'payload' => [
            'name' => $name,
            'url' => 'landers/' . $path,
            'offers' => '1',
            'type' => 2
        ]
    ];

    if (!empty($groupId)) {
        $data['payload']['group_lp'] = $groupId;
    }

    $responseData = sendRequest($entrypoint, 'POST', $data);

    $id = $responseData['id'];

    $result[$page] = $id;

    if (empty($groupId)) {
        $landData = sendRequest($entrypoint, 'GET', ['api_key' => $apiKey, 'action' => 'landing@get', 'extrafields[]' => 'groupsLanding', 'id' => $id]);
        $groups = $landData['extradata']['groupsLanding'];
        foreach ($groups as $group) {
            if ($group['name'] === $domain) {
                $groupId = $group['id'];
                break;
            }
        }

        if (empty($groupId)) {
            $groupData = sendRequest($entrypoint, 'POST', [
                'api_key' => $apiKey,
                'action' => 'group@add',
                'payload' => [
                    'name' => $domain,
                    'type' => 'LANDING',
                ]
            ]);
            $groupId = $groupData['id'];
        }

        $editData = [
            'api_key' => $apiKey,
            'action' => 'landing@edit',
            'payload' => [
                'id' => $id,
                'group_lp' => $groupId
            ]
        ];

        sendRequest($entrypoint, 'POST', $editData);
    }
}

echo json_encode($result);
exit(0);

