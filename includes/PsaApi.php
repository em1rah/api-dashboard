<?php
class PsaApi {
    private $baseUrl = 'https://openstat.psa.gov.ph/PXWeb/pxweb/en/DB/DB__2M__NFG/0032M4AFN06.px';

    private function makeCurlRequest($url, $isPost = false, $postData = null, $retries = 3) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);  // Increased to 2 minutes
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; AgriDashboard/1.0)');  // Helps bypass blocks
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

        if ($isPost) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);

            if ($error) {
                echo "Attempt $attempt failed: $error<br>";
                if ($attempt < $retries) sleep(5);  // Wait 5 seconds before retry
                continue;
            }

            if ($httpCode === 200 && !empty($response)) {
                curl_close($ch);
                return $response;
            }

            echo "Attempt $attempt: HTTP $httpCode for $url<br>";
            if ($attempt < $retries) sleep(10);  // Longer wait for server issues
        }

        curl_close($ch);
        die("Failed after $retries attempts. PSA server may be down – try again in 10 minutes. Or visit: $url manually.");
    }

    public function fetchTable($tableCode) {
        $url = $this->baseUrl . '/' . $tableCode . '.px';

        // Step 1: Get metadata with retry
        echo "Connecting to PSA metadata... ";
        $metadataJson = $this->makeCurlRequest($url, false);
        echo "OK!<br>";

        $metadata = json_decode($metadataJson, true);
        if (!$metadata || !isset($metadata['variables'])) {
            die("Invalid metadata from PSA. Raw response: " . substr($metadataJson, 0, 500) . "...");
        }

        // Step 2: Build query
        $query = [
            "query" => [],
            "response" => ["format" => "json"]
        ];

        foreach ($metadata['variables'] as $var) {
            if (!isset($var['code']) || $var['code'] === 'Value') continue;
            $query['query'][] = [
                "code" => $var['code'],
                "selection" => [
                    "filter" => "all",
                    "values" => ["*"]
                ]
            ];
        }

        // Step 3: POST data with retry
        echo "Fetching full data... ";
        $dataJson = $this->makeCurlRequest($url, true, json_encode($query));
        echo "OK!<br>";

        $data = json_decode($dataJson, true);
        if (!$data || !isset($data['dataset'])) {
            die("Invalid data response from PSA. Check console or try manual export from: $url");
        }

        return $data;
    }
}
?>