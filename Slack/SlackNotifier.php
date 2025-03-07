<?php

class SlackNotifier {
    private string $webhookUrl;
    
    public function __construct(string $webhookUrl) {
        $this->webhookUrl = $webhookUrl;
    }
    
    public function sendMessage(array $message): void {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->webhookUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
        curl_exec($ch);
        curl_close($ch);
    }
}
