#!/bin/sh
cd /app || exit 1
if [ ! -x vendor/bin/phpunit ]; then
    composer install --no-interaction --no-progress --prefer-dist || exit 1
fi
echo "PHP $(php -r 'echo PHP_VERSION;'), Quelle: ${LMO_SOURCE:-/app}, Modus: ${GOLDEN_MODE:-compare}, Coverage: ${GOLDEN_COVERAGE:-0}"

coverage=${GOLDEN_COVERAGE:-0}
if [ "$coverage" = "1" ]; then
    if ! php -m | grep -qi '^xdebug$'; then
        echo "Xdebug ist nicht geladen. Image neu bauen: docker compose build tests" >&2
        exit 1
    fi
    rm -rf coverage
    mkdir -p coverage/raw
fi

status=0
vendor/bin/phpunit "$@" || status=$?

if [ "$coverage" = "1" ]; then
    php tests/GoldenMaster/bin/coverage_report.php /app || true
fi
exit $status
