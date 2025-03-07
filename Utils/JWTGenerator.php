<?php
require_once '../libs/JWT.php';
require_once '../libs/Key.php';
require_once '../libs/BeforeValidException.php';
require_once '../libs/ExpiredException.php';
require_once '../libs/SignatureInvalidException.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTGenerator {
    public static function generate(string $teamId, string $keyId, string $privateKeyPath, string $slackWebhookUrl): string {
        if (!file_exists($privateKeyPath)) {
            Utils::sendErrorToSlack("The .p8 file was not found at: $privateKeyPath", $slackWebhookUrl);
            die("The .p8 file was not found at: $privateKeyPath");
        }
        
        $privateKey = file_get_contents($privateKeyPath);
        if (!$privateKey) {
            Utils::sendErrorToSlack("Could not read the private key.", $slackWebhookUrl);
            die("Could not read the private key.");
        }
        
        $now = time();
        $payload = [
            'iss' => $teamId,
            'exp' => $now + 600, // Expires in 10 minutes
            'aud' => 'appstoreconnect-v1'
        ];
        
        return JWT::encode($payload, $privateKey, 'ES256', $keyId);
    }
}
