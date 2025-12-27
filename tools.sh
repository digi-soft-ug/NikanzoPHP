#!/bin/sh
set -e

case "$1" in
  test)
    composer install --no-interaction --no-progress --prefer-dist
    vendor/bin/phpunit
    ;;
  phpstan)
    vendor/bin/phpstan analyse
    ;;
  psalm)
    vendor/bin/psalm --show-info=true
    ;;
  cs-fix)
    vendor/bin/php-cs-fixer fix --dry-run --diff
    ;;
  *)
    echo "Usage: $0 {test|phpstan|psalm|cs-fix}"
    exit 1
    ;;
esac