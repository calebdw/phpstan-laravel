#!/usr/bin/env bash

# Analyses a Laravel project with this extension installed from source.
# Runs identically locally and in CI.
#
# Usage:
#   tests/analyse.sh <target> [--fresh] [laravel-constraint]
#
#   laravel               A freshly installed laravel/laravel skeleton
#   monicahq-monica       Real-world application, pinned commit
#   filamentphp-filament  Real-world package, pinned commit
#
#   --fresh               Recreate the project from scratch
#   laravel-constraint    Only for the `laravel` target (default ^12.0)
#
# Projects are created under build/<target>/ so nothing is written outside this
# directory. Real-world targets are analysed with the checked-in config in
# e2e/<target>.neon, which refers to that path.

set -euo pipefail

PACKAGE_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# CI runs with no memory limit, but a stock php.ini caps at 128M which is not
# enough to analyse an application.
MEMORY_LIMIT="${PHPSTAN_MEMORY_LIMIT:-1G}"

# Laravel versions that are past their security support window have unpatched
# security advisories, which Composer >= 2.9 refuses to install by default.
export COMPOSER_NO_SECURITY_BLOCKING=1

TARGET="${1:-}"
FRESH=0
LARAVEL_VERSION_CONSTRAINT="^12.0"

for arg in "${@:2}"; do
    case "$arg" in
        --fresh) FRESH=1 ;;
        --*) echo "Unknown option: ${arg}" >&2; exit 1 ;;
        *) LARAVEL_VERSION_CONSTRAINT="$arg" ;;
    esac
done

REPOSITORY=""
REF=""
FORCE_PHPSTAN_VERSION=0

case "$TARGET" in
    laravel)
        ;;
    monicahq-monica)
        REPOSITORY="monicahq/monica"
        REF="e08e91734170b6bbd582cb578532c3948196124e"
        ;;
    filamentphp-filament)
        REPOSITORY="filamentphp/filament"
        REF="2f10baac09333f33ccf6cb8ec133897ba001056a"
        FORCE_PHPSTAN_VERSION=1
        ;;
    *)
        echo "Usage: tests/analyse.sh <laravel|monicahq-monica|filamentphp-filament> [--fresh]" >&2
        exit 1
        ;;
esac

PROJECT_DIR="${PACKAGE_DIR}/build/${TARGET}"

if [ "$FRESH" -eq 1 ]; then
    rm -rf "$PROJECT_DIR"
fi

# --- Fetch the project ------------------------------------------------------

if [ -d "$PROJECT_DIR" ]; then
    echo "==> Reusing build/${TARGET} (pass --fresh to recreate)"
elif [ "$TARGET" = "laravel" ]; then
    echo "==> Installing Laravel ${LARAVEL_VERSION_CONSTRAINT} into build/${TARGET}"
    mkdir -p "$(dirname "$PROJECT_DIR")"
    composer create-project --quiet --prefer-dist \
        "laravel/laravel:${LARAVEL_VERSION_CONSTRAINT}" "$PROJECT_DIR"
else
    echo "==> Cloning ${REPOSITORY} at ${REF:0:8} into build/${TARGET}"
    mkdir -p "$PROJECT_DIR"
    git init -q "$PROJECT_DIR"
    git -C "$PROJECT_DIR" remote add origin "https://github.com/${REPOSITORY}.git"
    # Not every server allows fetching a bare commit; fall back to a full fetch.
    if ! git -C "$PROJECT_DIR" fetch -q --depth 1 origin "$REF" 2>/dev/null; then
        git -C "$PROJECT_DIR" fetch -q origin
    fi
    git -C "$PROJECT_DIR" checkout -q "$REF"
fi

cd "$PROJECT_DIR"

if [ "$TARGET" != "laravel" ]; then
    echo "==> Installing ${TARGET} dependencies"
    composer install --no-scripts --no-interaction
fi

composer show --direct

# --- Install this extension from source -------------------------------------

# Composer copies path repositories verbatim and ignores .gitignore, so stage a
# clean tree first: otherwise build/ - which holds these very projects - would be
# copied into every install.
STAGE_DIR="${PACKAGE_DIR}/build/package"

echo "==> Staging the package"
mkdir -p "$STAGE_DIR"
rsync -a --delete \
    --exclude='/build/' --exclude='/vendor/' --exclude='/.git/' --exclude='/.jj/' \
    "${PACKAGE_DIR}/" "${STAGE_DIR}/"

echo "==> Adding phpstan-laravel from source"
composer config minimum-stability dev
# Copied rather than symlinked, on purpose: this puts the extension at
# <project>/vendor/calebdw/phpstan-laravel, the same layout as a normal install.
# bootstrap.php locates the application by walking up from its own directory, so
# a symlink here would point outside the project and break app discovery.
composer config repositories.0 \
    "{\"type\": \"path\", \"url\": \"${STAGE_DIR}\", \"options\": { \"symlink\": false }}"

if [ "$TARGET" = "monicahq-monica" ]; then
    composer remove --dev -n tomasvotruba/bladestan
fi

# No version information with "type":"path"
if [ "$FORCE_PHPSTAN_VERSION" -eq 1 ]; then
    composer require --dev --update-with-all-dependencies \
        "calebdw/phpstan-laravel:*" "phpstan/phpstan:*"
else
    composer require --dev --optimize-autoloader --update-with-all-dependencies \
        "calebdw/phpstan-laravel:*"
fi

# Composer will not re-copy a path package that is already installed at the same
# version, so refresh it to pick up local changes.
rsync -a --delete "${STAGE_DIR}/" "${PROJECT_DIR}/vendor/calebdw/phpstan-laravel/"

# --- Analyse ----------------------------------------------------------------

if [ "$TARGET" = "laravel" ]; then
    cat >phpstan.neon <<"EOF"
includes:
    - ./vendor/calebdw/phpstan-laravel/extension.neon
parameters:
    level: 5
    paths:
        - app/
EOF
    CONFIG="${PROJECT_DIR}/phpstan.neon"
else
    CONFIG="${PACKAGE_DIR}/e2e/${TARGET}.neon"

    if [ ! -f "$CONFIG" ]; then
        echo "Missing config: ${CONFIG}" >&2
        exit 1
    fi
fi

echo "==> Analysing ${TARGET}"
composer exec phpstan analyse -- -c "$CONFIG" --memory-limit "$MEMORY_LIMIT"

if [ "$TARGET" = "laravel" ]; then
    echo "==> Analysing from another working directory"
    cd "$(mktemp -d)"
    "${PROJECT_DIR}/vendor/bin/phpstan" analyse \
        --configuration="$CONFIG" --memory-limit "$MEMORY_LIMIT"
fi
