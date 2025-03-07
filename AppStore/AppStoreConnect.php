<?php
class AppStoreConnect {
    private string $jwtToken;

    public function __construct(string $jwtToken) {
        $this->jwtToken = $jwtToken;
    }

    public function getAllApps(): ?array {
        $url = "https://api.appstoreconnect.apple.com/v1/apps";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->jwtToken}",
            "Accept: application/json"
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    public function getRecentReviews(string $appId): ?array {
        $url = "https://api.appstoreconnect.apple.com/v1/apps/{$appId}/customerReviews?limit=200";
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->jwtToken}",
            "Accept: application/json"
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return json_decode($response, true);
    }

    public function sendReviewResponse(string $reviewId, string $responseText): array {
        $url = "https://api.appstoreconnect.apple.com/v1/customerReviewResponses";
        $data = [
            "data" => [
                "type" => "customerReviewResponses",
                "attributes" => [
                    "responseBody" => $responseText
                ],
                "relationships" => [
                    "review" => [
                        "data" => [
                            "id" => $reviewId,
                            "type" => "customerReviews"
                        ]
                    ]
                ]
            ]
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer {$this->jwtToken}",
            "Content-Type: application/json",
            "Accept: application/json"
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return ['httpCode' => $httpCode, 'response' => $response];
    }
}
