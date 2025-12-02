#!/bin/bash

# Batch fix remaining test issues
# This script addresses common patterns across multiple test files

echo "Fixing remaining test issues..."

# Fix: Replace 'status' => 'paused' with proper state
find tests -name "*.php" ! -name "*.skip" -type f -exec sed -i.bak "s/'status' => 'paused'/'state' => \\\\App\\\\States\\\\Campaign\\\\PausedCampaignState::class/g" {} \;

# Fix: Replace 'status' => 'draft' with proper state  
find tests -name "*.php" ! -name "*.skip" -type f -exec sed -i.bak "s/'status' => 'draft'/'state' => \\\\App\\\\States\\\\Campaign\\\\DraftCampaignState::class/g" {} \;

# Clean up backup files
find tests -name "*.bak" -delete

echo "Done! Please review changes and run tests."
