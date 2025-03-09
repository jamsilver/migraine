#!/usr/bin/env bash

function run() {
    local RED='\033[0;31m'
    local GREEN='\033[0;32m'
    local BLUE='\033[0;34m'
    local NC='\033[0m' # No Color

    echo -e "${BLUE}$ $@${NC}"

    if "$@"; then
        echo -e "${GREEN}✓ Command completed successfully${NC}"
        return 0
    else
        local exit_code=$?
        echo -e "${RED}✗ Command failed with exit code $exit_code${NC}"
        return $exit_code
    fi
}
