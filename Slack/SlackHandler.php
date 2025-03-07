<?php
require_once '../Utils/JWTGenerator.php';
require_once '../AppStore/AppStoreConnect.php';
require_once '../Utils/Utils.php';

class SlackHandler {
    private string $slackWebhookUrl;
    private string $privateKeyPath;
    private string $issuerId;
    private string $keyId;
    private string $slackSigningSecret;
    
    public function __construct(array $config) {
        $this->privateKeyPath = $config['privateKeyPath'];
        $this->issuerId = $config['issuerId'];
        $this->keyId = $config['keyId'];
        $this->slackSigningSecret = $config['slackSigningSecret'];
        $this->slackWebhookUrl = $config['slackWebhookUrl'];
    }
    
    public function handleRequest(): void {
        $headers = getallheaders();
        $body = file_get_contents("php://input");
        
        if (empty($body)) {
            Utils::sendErrorToSlack("No data received from Slack.", $this->slackWebhookUrl);
            die("No data received from Slack.");
        }
        
        parse_str($body, $data);
        if (!isset($data['payload'])) {
            Utils::sendErrorToSlack("Slack payload is missing.", $this->slackWebhookUrl);
            die("Slack payload is missing.");
        }
        
        $payload = json_decode($data['payload'], true);
        if ($payload === null) {
            Utils::sendErrorToSlack("Failed to decode JSON.", $this->slackWebhookUrl);
            die("Failed to decode JSON.");
        }
        
        // Verify Slack signature
        if (!Utils::verifySlackSignature($this->slackSigningSecret, $_SERVER, file_get_contents("php://input"))) {
            Utils::sendErrorToSlack("Invalid Slack signature.", $this->slackWebhookUrl);
            die(json_encode(["text" => "Invalid Slack signature."]));
        }
        
        $action = $payload['actions'][0] ?? null;
        if (!$action) {
            echo json_encode(["text" => "Unrecognized action."]);
            exit;
        }
        
        $actionId = $action['action_id'];
        $value = $action['value'];
        $responseUrl = $payload['response_url'] ?? null;
        
        if ($actionId === "reply_to_review") {
            $reviewId = str_replace("review_", "", $value);
            if (!$responseUrl) {
                die("response_url not found in the request.");
            }
            $responseMessage = [
                "response_type" => "ephemeral",
                "replace_original" => false,
                "blocks" => [
                    [
                        "type" => "section",
                        "text" => [
                            "type" => "mrkdwn",
                            "text" => "✏️ *Write your response to the review:*"
                        ]
                    ],
                    [
                        "type" => "input",
                        "block_id" => "review_input",
                        "element" => [
                            "type" => "plain_text_input",
                            "action_id" => "review_reply"
                        ],
                        "label" => [
                            "type" => "plain_text",
                            "text" => "Your response:"
                        ]
                    ],
                    [
                        "type" => "actions",
                        "elements" => [
                            [
                                "type" => "button",
                                "text" => [
                                    "type" => "plain_text",
                                    "text" => "Send response"
                                ],
                                "style" => "primary",
                                "value" => "send_response_" . $reviewId,
                                "action_id" => "send_review_reply"
                            ]
                        ]
                    ]
                ]
            ];
            $this->sendToResponseUrl($responseUrl, $responseMessage);
            http_response_code(200);
            header('Content-Type: application/json');
            exit;
        }
        
        if ($actionId === "send_review_reply") {
            $reviewId = str_replace("send_response_", "", $value);
            $stateValues = $payload['state']['values'] ?? [];
            $responseText = "";
            foreach ($stateValues as $block) {
                if (isset($block['review_reply']['value'])) {
                    $responseText = $block['review_reply']['value'];
                    break;
                }
            }
            
            if (empty($responseText)) {
                error_log("No input text found.");
                Utils::sendErrorToSlack("No input text found.", $this->slackWebhookUrl);
                die(json_encode(["text" => "No input text found."]));
            }
            
            $jwtToken = JWTGenerator::generate($this->issuerId, $this->keyId, $this->privateKeyPath, $this->slackWebhookUrl);
            $appStoreConnect = new AppStoreConnect($jwtToken);
            $result = $appStoreConnect->sendReviewResponse($reviewId, $responseText);
            $httpCode = $result['httpCode'];
            $responseFromApple = $result['response'];
            
            if ($httpCode === 201) {
                $responseMessage = [
                    "response_type" => "ephemeral",
                    "text" => "✅ *Response successfully sent to the App Store.*"
                ];
            } elseif ($httpCode === 409) {
                $responseMessage = [
                    "response_type" => "ephemeral",
                    "text" => "⚠️ ERROR 409: $responseFromApple"
                ];
            } else {
                $responseMessage = [
                    "response_type" => "ephemeral",
                    "text" => "❌ Error sending the response. Code: $httpCode, error = $responseFromApple"
                ];
            }
            
            $this->sendToResponseUrl($responseUrl, $responseMessage);
            http_response_code(200);
            header('Content-Type: application/json');
            exit;
        }
        
        echo json_encode(["text" => "Unrecognized action."]);
        exit;
    }
    
    private function sendToResponseUrl(string $responseUrl, array $message): void {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $responseUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_exec($ch);
        curl_close($ch);
    }
}
