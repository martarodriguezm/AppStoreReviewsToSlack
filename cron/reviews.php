<?php
require_once '../AppStore/AppStoreConnect.php';
require_once '../Slack/SlackNotifier.php';
require_once '../Utils/JWTGenerator.php';
require_once '../Utils/Utils.php';

// Load configuration from the private folder
$config = require '../private/config.php';

$privateKeyPath = $config['privateKeyPath'];
$issuerId = $config['issuerId'];
$keyId = $config['keyId'];
$slackWebhookUrl = $config['slackWebhookUrl'];
$companyName = $config['companyName'];

// Generate the JWT token
$jwtToken = JWTGenerator::generate($issuerId, $keyId, $privateKeyPath, $slackWebhookUrl);
$appStoreConnect = new AppStoreConnect($jwtToken);

// Get all apps
$apps = $appStoreConnect->getAllApps();

if (!isset($apps['data']) || !is_array($apps['data'])) {
    Utils::sendErrorToSlack("Error retrieving apps: " . json_encode($apps), $slackWebhookUrl);
    die("❌ ERROR: The API did not return a valid list of apps.");
}

$reviewsByApp = [];
$now = new DateTime("now", new DateTimeZone("UTC"));

foreach ($apps['data'] as $app) {
    $appId = $app['id'];
    $appName = $app['attributes']['name'];
    $reviewsData = $appStoreConnect->getRecentReviews($appId);
    
    $filteredReviews = [];
    if (!empty($reviewsData['data'])) {
        foreach ($reviewsData['data'] as $review) {
            $createdDate = new DateTime($review['attributes']['createdDate']);
            $interval = $now->diff($createdDate);
            if ($interval->days == 0 && $interval->h < 24) {
                $filteredReviews[] = [
                    'id' => $review['id'],
                    'rating' => $review['attributes']['rating'],
                    'title' => $review['attributes']['title'],
                    'body' => $review['attributes']['body'],
                    'date' => $review['attributes']['createdDate']
                ];
            }
        }
    }
    
    if (!empty($filteredReviews)) {
        $reviewsByApp[$appName] = $filteredReviews;
    }
}

// Send filtered reviews to Slack
$slackNotifier = new SlackNotifier($slackWebhookUrl);
if (empty($reviewsByApp)) {
    $message = ["text" => "📢 $companyName: No new reviews in the last 24 hours."];
    $slackNotifier->sendMessage($message);
} else {
    $blocks = [];
    $blocks[] = [
        "type" => "section",
        "text" => ["type" => "mrkdwn", "text" => "📢 *$companyName: New reviews in the last 24 hours:*"]
    ];
    foreach ($reviewsByApp as $appName => $reviews) {
        $blocks[] = [
            "type" => "section",
            "text" => ["type" => "mrkdwn", "text" => "🟢 *$appName*"]
        ];
        foreach ($reviews as $review) {
            $reviewId = $review['id'];
            $reviewText = "🆔 *{$review['id']}*\n" .
                          "⭐ *{$review['rating']}/5*\n" .
                          "📝 *{$review['title']}*\n" .
                          "💬 {$review['body']}\n" .
                          "📅 {$review['date']}\n";
            $blocks[] = [
                "type" => "section",
                "text" => ["type" => "mrkdwn", "text" => $reviewText]
            ];
            $blocks[] = [
                "type" => "actions",
                "elements" => [
                    [
                        "type" => "button",
                        "text" => ["type" => "plain_text", "text" => "💬 Reply"],
                        "style" => "primary",
                        "value" => "review_$reviewId",
                        "action_id" => "reply_to_review"
                    ]
                ]
            ];
        }
    }
    $message = ["blocks" => $blocks];
    $slackNotifier->sendMessage($message);
}

// Display JSON for debugging
header('Content-Type: application/json');
echo json_encode($reviewsByApp, JSON_PRETTY_PRINT);
