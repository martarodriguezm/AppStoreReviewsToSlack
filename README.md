# App Store Reviews to Slack Notifier

This project queries the App Store Connect API to retrieve app reviews and sends notifications to Slack. It also provides an endpoint for Slack users to reply directly to reviews. The application is structured into modules, separating the responsibilities of querying the App Store, sending Slack notifications, and handling interactive Slack requests all in PHP.

## Features

- **Retrieve Reviews:** Fetches the list of published apps and retrieves recent customer reviews from App Store Connect.
- **Slack Notifications:** Sends a Slack message with the recent reviews or a notification when there are no new reviews.
- **Interactive Slack Endpoint:** Allows users to reply to reviews directly from Slack. The reply is forwarded to the App Store Connect API.

## Project Structure

    project/
    ├── libs/                      # Third-party libraries (do not modify)
    ├── private/                   # Sensitive configuration and keys (do not version control)
    │   └── config.php             # Configuration file with private keys and credentials
    ├── AppStore/
    │   └── AppStoreConnect.php     # Handles querying apps, reviews, and sending responses
    ├── Slack/
    │   ├── SlackNotifier.php       # Sends messages to Slack
    │   └── SlackHandler.php        # Processes incoming Slack interactions
    ├── Utils/
    │   ├── JWTGenerator.php        # Generates JWT for the Apple API
    │   └── Utils.php               # Utility functions (error handling, signature verification, etc.)
    ├── cron/
    │   └── reviews.php            # Cron script to retrieve reviews and notify Slack
    └── public/
        └── slack_handler.php      # Endpoint for processing Slack interactions

## Requirements

- PHP 7.4 or higher
- Access to the App Store Connect API (credentials and a .p8 private key). Unfortunately, Apple requires an API Key with Admin permissions to respond to reviews.
- A Slack account with a configured webhook and signing secret for verifying requests

## Installation

1. **Clone the Repository:**

    ```bash
    git clone https://github.com/your_username/your_repository.git
    cd your_repository
    ```

2. **Modify paths as needed**

3. **Configure Private Settings:**

Create the private/config.php file (do not commit this file to version control) with the following format:

    ```php
    <?php
    return [
        "privateKeyPath"    => "/path/to/your/key.p8",
        "issuerId"          => "your_issuer_id",
        "keyId"             => "your_key_id",
        "slackWebhookUrl"   => "https://hooks.slack.com/services/XXX/XXX/XXX",
        "slackSigningSecret"=> "your_slack_signing_secret",
        "companyName" => "Apple account name or the name you want in the slack notification text"
    ];
    ?>
    ```

## Usage

### Cron Execution

The script cron/reviews.php performs the following tasks:

- Generates a JWT for authenticating with App Store Connect.
- Retrieves the list of apps and their recent reviews.
- Filters reviews from the last 24 hours.
- Sends a Slack message with the retrieved reviews (or a notification if there are no new reviews).

Add an entry to your crontab similar to:
    ```
    0 0 * * * /usr/bin/php /path/to/your_project/cron/reviews.php
    ```

### Slack Endpoint

The file public/slack_handler.php serves as the endpoint for handling Slack interactions. Configure this endpoint in your Slack app settings to manage:

- The "Reply" action for reviews.
- Submitting the reply, which is then forwarded to the App Store Connect API.

Ensure you secure this endpoint and configure signature verification using the slackSigningSecret from your configuration.

## Development and Contribution

- Modular Structure: The project is organized into modules (src/AppStore, src/Slack, and src/Utils) to facilitate extension and maintenance.
- Logging and Debugging: The project includes error logging and Slack notifications for troubleshooting issues such as interactive actions and signature verification.

Feel free to fork the repository and open pull requests with improvements or bug fixes.

## License

This project is distributed under the MIT License.
