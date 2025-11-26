#!/bin/bash

# Sail Environment Switcher
# Usage: ./sail-env.sh [up|down|test]

case "$1" in
    up)
        echo "🚢 Starting Sail with .env.sail configuration..."
        cp .env.sail .env.sail.backup 2>/dev/null
        ./vendor/bin/sail up -d
        echo "✅ Sail is running!"
        echo "   - App: http://localhost"
        echo "   - PostgreSQL: localhost:5432"
        echo "   - Redis: localhost:6379"
        echo "   - Minio: http://localhost:9000"
        echo "   - Minio Console: http://localhost:8900"
        ;;
    down)
        echo "🛑 Stopping Sail..."
        ./vendor/bin/sail down
        echo "✅ Sail stopped. Back to Herd development (stash.test)"
        ;;
    test)
        echo "🧪 Running tests in Sail environment..."
        ./vendor/bin/sail artisan test
        ;;
    meta)
        echo "🤖 Running Meta-Campaign in Sail sandbox..."
        ./vendor/bin/sail artisan meta:new
        ;;
    *)
        echo "Usage: $0 {up|down|test|meta}"
        echo ""
        echo "Commands:"
        echo "  up    - Start Sail/Docker for testing"
        echo "  down  - Stop Sail, return to Herd"
        echo "  test  - Run tests in Sail environment"
        echo "  meta  - Run Meta-Campaign in sandbox"
        exit 1
        ;;
esac
