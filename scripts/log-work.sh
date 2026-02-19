#!/bin/bash

# Definition of the log file
LOG_FILE="docs/WORK_LOG.md"

# Ensure the log file exists
if [ ! -f "$LOG_FILE" ]; then
    echo "# Work Log" > "$LOG_FILE"
    echo "Tracking daily development work and IDE sources." >> "$LOG_FILE"
    echo "" >> "$LOG_FILE"
fi

# Get date
DATE=$(date +"%Y-%m-%d")

# Prompt for details if not provided as sizing_fit arguments
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
