#!/bin/bash

# This script helps identify test files that need tenant context
# Run manually and review each file

echo "Identifying test files needing tenant context fixes..."

# Find test files that create Campaign/Document factories without UsesDashboardSetup
FILES=$(grep -l "Campaign::factory()->create()" tests/Feature/DeadDrop/**/*.php 2>/dev/null | \
  xargs grep -L "UsesDashboardSetup" 2>/dev/null | \
  grep -v ".skip")

if [ -z "$FILES" ]; then
  echo "No files found needing fixes"
  exit 0
fi

echo "Files needing tenant context:"
echo "$FILES"
echo ""
echo "To fix each file:"
echo "1. Add 'use Tests\Support\UsesDashboardSetup;'"
echo "2. Add 'uses(UsesDashboardSetup::class)' after imports"
echo "3. Wrap test logic in TenantContext::run()"
echo ""
echo "Example pattern in WARP.md under 'Pattern for Fixing Remaining Tests'"
