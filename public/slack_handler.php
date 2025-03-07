<?php
require_once '../Slack/SlackHandler.php';

$config = require '../private/config.php';

$slackHandler = new SlackHandler($config);
$slackHandler->handleRequest();
