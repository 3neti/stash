#!/bin/bash

# Phase 3.1 Dashboard Test Setup Script
# Quick setup for visual testing of the Subscriber Dashboard

set -e

echo "🚀 Setting up Dashboard Test Environment..."
echo ""

# Check if --fresh flag is passed
FRESH=false
if [[ "$1" == "--fresh" ]]; then
    FRESH=true
    echo "⚠️  Fresh installation requested"
    read -p "This will delete ALL data. Continue? (y/N) " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "Setup cancelled."
        exit 1
    fi
fi

# Run the Artisan command
if [ "$FRESH" = true ]; then
    php artisan dashboard:setup-test --fresh --no-interaction
else
    php artisan dashboard:setup-test --no-interaction
fi

echo ""
echo "✅ Done! You can now run: composer run dev"
