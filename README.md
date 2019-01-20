# Petition tracker

Tracks daily signature volume at GOVUK Petitions https://petition.parliament.uk/

## Generate stats

Tracks stats (once every 5 mins) and outputs stats HTML page.

```
php tracker.php

```

## Site URLs

* Live - https://tracker.simonrjones.net/
* Development - http://local.tracker/

## Deployment

1. Ensure you have the `s24_secrets` sparse bundle setup with the singlecloud1 SSH keys
2. Connect to S24 VPN
3. Use the following Deployer commands to deploy the site:

```
# Production
dep deploy production
```

## Requirements

- [Composer](https://getcomposer.org/)
- [Deployer](https://deployer.org/)
