#!/usr/bin/env bash
# Append a work log entry to docs/WORK_LOG.md.
# Usage: ./scripts/log-work.sh [summary] [IDE]   (run from repo root)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(dirname "$SCRIPT_DIR")"
LOG_FILE="$ROOT_DIR/docs/WORK_LOG.md"

if [ ! -d "$ROOT_DIR/docs" ]; then
    echo "❌ docs/ not found. Run from repo root."
    exit 1
fi

# Ensure the log file exists
if [ ! -f "$LOG_FILE" ]; then
    echo "# Work Log" > "$LOG_FILE"
    echo "Tracking daily development work and IDE sources." >> "$LOG_FILE"
    echo "" >> "$LOG_FILE"
fi

# Get date
DATE=$(date +"%Y-%m-%d")

# Prompt for details if not provided as arguments
SUMMARY=$1
if [ -z "$SUMMARY" ]; then
    echo "Enter a brief summary of changes:"
    read SUMMARY
fi

IDE=$2
if [ -z "$IDE" ]; then
    echo "Which IDE/Source did you use? (e.g., Cursor, VS Code, Codex, Manual):"
    read IDE
fi

# Append to log
echo "## $DATE" >> "$LOG_FILE"
echo "**Source**: $IDE" >> "$LOG_FILE"
echo "**Changes**:" >> "$LOG_FILE"
echo "- $SUMMARY" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

echo "Logged to $LOG_FILE"
