<?php

class Utils {
    public static function sendErrorToSlack(string $message, string $slackWebhookUrl): void {
        error_log("Sending error to Slack: $message");
        $payload = ["text" => "❌ " . $message];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $slackWebhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        error_log("Slack responded with code: $http_code, response: $response");
    }
    
    public static function verifySlackSignature(string $slackSigningSecret, array $server, string $body): bool {
        $slackSignature = $server['HTTP_X_SLACK_SIGNATURE'] ?? '';
        $slackTimestamp = $server['HTTP_X_SLACK_REQUEST_TIMESTAMP'] ?? '';
        if (abs(time() - (int)$slackTimestamp) > 300) {
            error_log("Timestamp too old: " . $slackTimestamp);
            return false;
        }
        $baseString = "v0:$slackTimestamp:$body";
        $computedSignature = 'v0=' . hash_hmac('sha256', $baseString, $slackSigningSecret);
        if (!hash_equals($computedSignature, $slackSignature)) {
            error_log("Invalid Slack signature.");
            return false;
        }
        return true;
    }
}
