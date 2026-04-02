#!/bin/bash

# Helmetsan Autonomous Keeper
# 24/7 Data Guard Service
# Prevents duplicate runs and manages the continuous-sweep cycle.

PROJECT_ROOT="/Users/anumac/Documents/ Projects/Helmetsan"
SCRIPT_PATH="$PROJECT_ROOT/scripts/continuous-sweep.php"
LOG_DIR="$PROJECT_ROOT/data/logs"
LOG_FILE="$LOG_DIR/keeper-$(date +%Y-%m-%d).log"
LOCK_FILE="/tmp/helmetsan-keeper.lock"

# Ensure log directory exists
mkdir -p "$LOG_DIR"

# PID Locking logic
if [ -f "$LOCK_FILE" ]; then
    PID=$(cat "$LOCK_FILE")
    if ps -p "$PID" > /dev/null; then
        echo "$(date): Keeper already running (PID $PID). Exiting." >> "$LOG_FILE"
        exit 0
    fi
fi

# Write current PID to lock file
echo $$ > "$LOCK_FILE"

echo "$(date): Keeper started sweep cycle." >> "$LOG_FILE"

# Run the sweep script
# Note: Use default mode (local with server fallback)
/usr/bin/php "$SCRIPT_PATH" >> "$LOG_FILE" 2>&1

echo "$(date): Keeper completed sweep cycle." >> "$LOG_FILE"

# Remove lock
rm "$LOCK_FILE"
