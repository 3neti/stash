#!/usr/bin/env zsh

# Colors for output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# PID file to track background processes
PIDFILE=".dev.pids"

# Default transaction ID (can be overridden with TRANSACTION_ID env var)
TRANSACTION_ID=${TRANSACTION_ID:-"EKYC-1764773764-3863"}

# Default campaign (can be overridden with CAMPAIGN env var)
CAMPAIGN=${CAMPAIGN:-"e-signature-workflow"}

# Keep services running after completion (env override or --keep flag)
KEEP_ALIVE=${KEEP_ALIVE:-0}

function start() {
    local keep_flag="$1"
    echo "${GREEN}Starting Stash development environment...${NC}\n"
    
    # Clear all caches for clean state
    echo "${YELLOW}[0/5] Clearing caches and queues...${NC}"
    php artisan optimize:clear > /dev/null 2>&1  # Clears config, route, view, cache, and opcache
    php artisan queue:clear > /dev/null 2>&1 || true  # Clear Laravel queues (safer than FLUSHALL)
    
    # Clean up old PIDs and callback lock
    rm -f "$PIDFILE" storage/logs/.callback-triggered
    
    # 1. Optimize and start Vite
    echo "${YELLOW}[1/5] Starting Vite dev server (Herd serves Laravel)...${NC}"
    php artisan optimize:clear > /dev/null 2>&1
    npm run dev > storage/logs/vite.log 2>&1 &
    echo $! >> "$PIDFILE"
    
    # 2. Start Reverb
    echo "${YELLOW}[2/5] Starting Reverb websocket server...${NC}"
    php artisan reverb:start --debug > storage/logs/reverb.log 2>&1 &
    echo $! >> "$PIDFILE"
    
    # 3. Clear logs, migrate (no seed), create tenant (triggers auto-onboarding), start queue worker
    echo "${YELLOW}[3/5] Resetting database, creating tenant, and starting queue worker...${NC}"
    truncate -s0 storage/logs/laravel.log
    php artisan migrate:fresh > storage/logs/migration.log 2>&1
    
    # REAL-WORLD: Create tenant (triggers TenantObserver → auto-onboarding)
    echo "${YELLOW}  ✓${NC} Creating tenant: Default Organization (default)"
    php artisan tenant:create "Default Organization" \
        --slug=default \
        --email=admin@example.com \
        >> storage/logs/migration.log 2>&1
    
    # Wait for observer to complete onboarding (database, migrations, processors, templates)
    echo "${YELLOW}  ⏳${NC} Waiting for auto-onboarding to complete..."
    sleep 3  # Give observer time to complete asynchronously
    
    # Verify campaigns were created from templates
    echo "${YELLOW}  ✓${NC} Verifying campaigns created from templates:"
    campaign_count=$(php artisan tinker --execute="\$tenant = \App\Models\Tenant::on('central')->where('slug', 'default')->first(); \App\Tenancy\TenantContext::run(\$tenant, function() { echo \App\Models\Campaign::count(); });" 2>/dev/null | tail -1)
    
    if [[ "$campaign_count" -eq "0" ]]; then
        echo "${RED}  ✗ No campaigns found! Auto-onboarding may have failed.${NC}"
        echo "${YELLOW}  → Check storage/logs/laravel.log for errors${NC}"
        stop
        exit 1
    fi
    
    # List created campaigns
    php artisan tinker --execute="\$tenant = \App\Models\Tenant::on('central')->where('slug', 'default')->first(); \App\Tenancy\TenantContext::run(\$tenant, function() { foreach (\App\Models\Campaign::all() as \$c) { echo '    - ' . \$c->slug . ' (" . \$c->name . ")' . PHP_EOL; } });" 2>/dev/null
    
    # Verify the target campaign exists
    campaign_exists=$(php artisan tinker --execute="\$tenant = \App\Models\Tenant::on('central')->where('slug', 'default')->first(); \App\Tenancy\TenantContext::run(\$tenant, function() { echo \App\Models\Campaign::where('slug', '$CAMPAIGN')->exists() ? '1' : '0'; });" 2>/dev/null | tail -1)
    
    if [[ "$campaign_exists" != "1" ]]; then
        echo "${RED}  ✗ Campaign '$CAMPAIGN' not found!${NC}"
        echo "${YELLOW}  → Available campaigns listed above${NC}"
        echo "${YELLOW}  → Update CAMPAIGN env var or DEFAULT_CAMPAIGN_TEMPLATES in .env${NC}"
        stop
        exit 1
    fi
    
    echo "${YELLOW}  ✓${NC} Campaign '$CAMPAIGN' verified"
    
    php artisan queue:work > storage/logs/queue.log 2>&1 &
    echo $! >> "$PIDFILE"
    
    # 4. Process test document and capture output
    echo "${YELLOW}[4/6] Processing test document (waiting for workflow)...${NC}"
    echo "${YELLOW}Campaign:${NC} $CAMPAIGN"
    local doc_output="storage/logs/document-process.log"
    rm -f "$doc_output"
    
    # Run document processing (will wait for workflow completion)
    php artisan document:process ~/Downloads/Invoice.pdf \
        --campaign="$CAMPAIGN" \
        --wait \
        --show-output 2>&1 | tee "$doc_output" &
    local doc_process_pid=$!
    
    echo "${YELLOW}  ⏱${NC}  Waiting for workflow completion (may wait for callback signal)..."
    
    # 5. Extract redirect_url and trigger KYC callback (once)
    echo "${YELLOW}[5/6] Waiting for redirect_url...${NC}"
    (while ! grep -q 'redirect_url' "$doc_output" 2>/dev/null; do sleep 0.5; done && \
     sleep 1 && \
     callback_url=$(grep -o 'http://stash.test/kyc/callback/[a-f0-9-]*' "$doc_output" | head -1 | uniq) && \
     if [ -n "$callback_url" ] && [ ! -f storage/logs/.callback-triggered ]; then \
         touch storage/logs/.callback-triggered && \
         echo "  → Triggering: ${callback_url}?transactionId=$TRANSACTION_ID&status=auto_approved" && \
         curl -s "${callback_url}?transactionId=$TRANSACTION_ID&status=auto_approved" \
         > storage/logs/kyc-callback.log 2>&1 && \
         echo "  ✓ Callback completed at $(date '+%H:%M:%S')" && \
         echo "  ⏳ Waiting 5 seconds for queue job to signal workflow..." && \
         sleep 5; \
     fi) &
    
    # Wait for document processing to complete
    wait $doc_process_pid
    
    # Echo pipeline config for the campaign (after tenant is initialized)
    echo "\n${YELLOW}Campaign Pipeline Config (from database):${NC}"
    php artisan tinker --execute="\$tenant = \App\Models\Tenant::on('central')->where('slug', 'default')->first(); \App\Tenancy\TenantContext::run(\$tenant, function() { \$campaign = \App\Models\Campaign::where('slug', '$CAMPAIGN')->first(); if (\$campaign && isset(\$campaign->pipeline_config['processors'])) { foreach (\$campaign->pipeline_config['processors'] as \$p) { echo '  - id: ' . (\$p['id'] ?? 'N/A') . ', type: ' . (\$p['type'] ?? 'N/A') . PHP_EOL; } } else { echo '  Campaign not found or no processors' . PHP_EOL; } });" 2>/dev/null
    
    echo "\n${GREEN}✓ Development environment ready!${NC}"
    
    # Extract and display document links
    if [[ -f "$doc_output" ]]; then
        signed_doc_url=$(grep -o 'http://stash.test/storage/tenants/[0-9]*/qr_watermarked_[^"]*\.pdf' "$doc_output" | head -1)
        verification_url=$(grep -o 'http://stash.test/documents/verify/[a-f0-9-]*/[A-Z0-9-]*' "$doc_output" | head -1)
        
        if [[ -n "$signed_doc_url" || -n "$verification_url" ]]; then
            echo "${YELLOW}Document Links:${NC}"
            [[ -n "$signed_doc_url" ]] && echo "  Signed PDF:  $signed_doc_url"
            [[ -n "$verification_url" ]] && echo "  Verify:      $verification_url"
            echo ""
        fi
    fi
    
    echo "${YELLOW}Logs:${NC}"
    echo "  Vite:        tail -f storage/logs/vite.log"
    echo "  Reverb:      tail -f storage/logs/reverb.log"
    echo "  Queue:       tail -f storage/logs/queue.log"
    echo "  Laravel:     tail -f storage/logs/laravel.log"
    echo "  KYC Callback: cat storage/logs/kyc-callback.log"
    echo "\n${YELLOW}Stop all:${NC} ./dev.sh stop"

    # Auto-stop unless keep-alive is requested
    if [[ "$keep_flag" == "--keep" || "$keep_flag" == "-k" || "$KEEP_ALIVE" == "1" ]]; then
        echo "${YELLOW}Keep-alive enabled:${NC} services left running. Use ./dev.sh stop to halt them."
    else
        echo "${YELLOW}Auto-stopping background services...${NC}"
        stop
    fi
}

function stop() {
    if [[ ! -f "$PIDFILE" ]]; then
        echo "${YELLOW}No running processes found.${NC}"
        return 0
    fi
    
    echo "${YELLOW}Stopping all development processes...${NC}"
    
    while read pid; do
        if ps -p $pid > /dev/null 2>&1; then
            kill $pid 2>/dev/null && echo "  ✓ Stopped process $pid"
        fi
    done < "$PIDFILE"
    
    rm -f "$PIDFILE"
    echo "${GREEN}✓ All processes stopped.${NC}"
}

function status() {
    if [[ ! -f "$PIDFILE" ]]; then
        echo "${RED}No running processes found.${NC}"
        exit 1
    fi
    
    echo "${YELLOW}Development process status:${NC}\n"
    local running=0
    local stopped=0
    
    while read pid; do
        if ps -p $pid > /dev/null 2>&1; then
            echo "  ${GREEN}✓${NC} Process $pid is running"
            ((running++))
        else
            echo "  ${RED}✗${NC} Process $pid has stopped"
            ((stopped++))
        fi
    done < "$PIDFILE"
    
    echo "\n${YELLOW}Summary:${NC} $running running, $stopped stopped"
}

function logs() {
    echo "${YELLOW}Tailing all logs (Ctrl+C to stop)...${NC}\n"
    tail -f storage/logs/{vite,reverb,queue,laravel}.log
}

function show_help() {
    cat << 'EOF'
┏━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┓
┃  Stash Development Environment Manager                                  ┃
┗━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━┛

USAGE:
  ./dev.sh <command> [options]

COMMANDS:
  start     Start all development services and process test document
            - Starts Vite dev server (with HMR)
            - Starts Reverb websocket server
            - Resets database with fresh migrations (no seeds)
            - Creates tenant (triggers auto-onboarding via TenantObserver)
            - Verifies campaigns created from templates
            - Starts queue worker
            - Processes test document through workflow
            - Auto-triggers KYC callback
            - Auto-stops services when done (unless --keep is used)

  stop      Stop all background services (Vite, Reverb, Queue)

  restart   Stop all services, then start them again

  status    Check which background processes are currently running

  logs      Tail all log files simultaneously (Vite, Reverb, Queue, Laravel)

  help      Show this help message

OPTIONS:
  --keep, -k    Keep services running after document processing completes
                (default: auto-stop when done)

ENVIRONMENT VARIABLES:
  CAMPAIGN          Campaign slug to use for document processing
                    Default: "e-signature"
                    Example: CAMPAIGN=invoice-processing ./dev.sh start

  TRANSACTION_ID    HyperVerge KYC transaction ID for callback
                    Default: "EKYC-1764773764-3863"
                    Example: TRANSACTION_ID=EKYC-999 ./dev.sh start

  KEEP_ALIVE        Set to "1" to keep services running after completion
                    Default: "0" (auto-stop)
                    Example: KEEP_ALIVE=1 ./dev.sh start

EXAMPLES:
  # Quick test run (auto-stops when done)
  ./dev.sh start

  # Keep services running for development
  ./dev.sh start --keep

  # Use different campaign
  CAMPAIGN=csv-import ./dev.sh start

  # Use different transaction ID
  TRANSACTION_ID=EKYC-1234567890-5678 ./dev.sh start

  # Combine multiple options
  CAMPAIGN=invoice-processing TRANSACTION_ID=EKYC-123 ./dev.sh start --keep

  # Check which services are running
  ./dev.sh status

  # Watch all logs in real-time
  ./dev.sh logs

  # Stop all services manually
  ./dev.sh stop

LOG FILES:
  storage/logs/vite.log               Vite dev server output
  storage/logs/reverb.log             Reverb websocket server output
  storage/logs/queue.log              Queue worker output
  storage/logs/laravel.log            Laravel application logs
  storage/logs/kyc-callback.log       KYC callback response
  storage/logs/document-process.log   Document processing output

DOCUMENT PROCESSING WORKFLOW:
  1. Fresh database migration (no DatabaseSeeder)
  2. Create tenant via tenant:create command
  3. TenantObserver → TenantOnboardingService auto-onboarding:
     - Create tenant database
     - Run tenant migrations
     - Seed processors via ProcessorSeeder
     - Import campaign templates from campaigns/templates/
  4. Verify campaigns created from templates (configurable via .env)
  5. Start queue worker for workflows
  6. Upload Invoice.pdf to campaign
  7. Start Laravel Workflow execution
  8. Process through eKYC Verification processor
  9. Wait for workflow to reach "awaiting_callback" state
  10. Auto-trigger KYC callback with transaction ID
  11. Queue worker processes callback job and signals workflow
  12. Workflow resumes and continues through Electronic Signature processor
  12. Generate signed PDF with QR watermark and verification stamp
  13. Display clickable document links
  14. Auto-stop services (unless --keep flag used)

REQUIREMENTS:
  - Laravel Herd (serves stash.test automatically)
  - ~/Downloads/Invoice.pdf (test document)
  - PostgreSQL database
  - Redis (for queues)

NOTES:
  - All services run in background except document processing
  - PID tracking file: .dev.pids
  - Callback lock file: storage/logs/.callback-triggered (auto-cleaned)
  - Reverb may fail if port 8080 is already in use (non-fatal)
  - Workflow may pause waiting for external callbacks (eKYC verification)
  - Callback signals are processed asynchronously via queue workers
  - Use Ctrl+C to stop document processing if workflow hangs

For more information, see: WARP.md

EOF
}

# Main command handler
case "$1" in
    start)
        start "$2"
        ;;
    stop)
        stop
        ;;
    restart)
        stop
        sleep 2
        start
        ;;
    status)
        status
        ;;
    logs)
        logs
        ;;
    help|--help|-h)
        show_help
        ;;
    *)
        echo "${RED}Error: Unknown command '$1'${NC}"
        echo ""
        echo "Usage: ./dev.sh <command> [options]"
        echo ""
        echo "Commands: start, stop, restart, status, logs, help"
        echo ""
        echo "Run './dev.sh help' for detailed documentation."
        exit 1
        ;;
esac
