#!/bin/bash

# Install dependencies
composer require --dev drupal/coder squizlabs/php_codesniffer

# Run PHP CodeSniffer
php ./vendor/bin/phpcs --standard=Drupal,DrupalPractice --extensions=php,module,install .

# Clean up
composer remove --dev drupal/coder squizlabs/php_codesniffer