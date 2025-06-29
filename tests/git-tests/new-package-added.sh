#!/usr/bin/env bash

set -euo pipefail

source "$(dirname "$0")/lib.sh"
FIX_CONFLICTS_CMD=$(realpath "$(dirname "$0")/../../bin/composer-fix-conflicts")

setUp
trap 'tearDown' EXIT

git init --initial-branch=main
cat >composer.json <<'EOF'
{
    "name": "test/test",
    "version": "0.1.0",
    "require": {
        "symfony/process": "^5.4"
    },
    "autoload": {
        "psr-4": {
            "Test\\Test\\": "src/"
        }
    },
    "authors": [
        {
            "name": "Jelle Besseling",
            "email": "jelle@pingiun.com"
        }
    ]
}
EOF
composer install
git add composer.json composer.lock
git commit -m "Initial commit"
git switch -c feature-branch
composer require symfony/console ^5.4
git add composer.json composer.lock
git commit -m "Add console feature"
git switch main
composer require symfony/process ^7.3
composer install
git add composer.json composer.lock
git commit -m "Update process"
git merge feature-branch || true
assertOk grep '>>>>>>' composer.json
assertOk grep '>>>>>>' composer.lock
# Should not yet be added
assertOk sh -c 'git diff --cached --name-only --diff-filter=U | grep composer.json'
assertOk sh -c 'git diff --cached --name-only --diff-filter=U | grep composer.lock'
printf "o\nm\n^7.3\n" | $FIX_CONFLICTS_CMD

assertEqual "$(cat composer.json)" '{
    "name": "test/test",
    "version": "0.1.0",
    "require": {
        "symfony/process": "^7.3",
        "symfony/console": "^7.3"
    },
    "autoload": {
        "psr-4": {
            "Test\\Test\\": "src/"
        }
    },
    "authors": [
        {
            "name": "Jelle Besseling",
            "email": "jelle@pingiun.com"
        }
    ]
}'

assertNotOk grep '>>>>>>' composer.json
assertNotOk grep '>>>>>>' composer.lock
# Check that the files were added
assertNotOk sh -c 'git diff --cached --name-only --diff-filter=U | grep composer.json'
assertNotOk sh -c 'git diff --cached --name-only --diff-filter=U | grep composer.lock'
