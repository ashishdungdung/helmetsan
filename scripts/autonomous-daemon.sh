#!/bin/bash

# Helmetsan Autonomous Enrichment Daemon
# Designed for production VPS (Linux). Runs 24/7 with randomized intervals.

# 1. SETUP PATHS
# If running as systemd, CWD should be the repo root.
REPO_ROOT="$(pwd)"
DATA_DIR="$REPO_ROOT/wp-content/uploads/helmetsan-data"
LOG_DIR="$DATA_DIR/logs"
LOG_FILE="$LOG_DIR/daemon-$(date +%Y-%m).log"
LOCK_FILE="/tmp/hs_enrich_daemon.lock"

mkdir -p "$LOG_DIR"

# 2. CONCURRENCY LOCK
if [ -f "$LOCK_FILE" ]; then
    PID=$(cat "$LOCK_FILE")
    if ps -p "$PID" > /dev/null; then
        echo "$(date): Daemon already running (PID $PID). Exiting." >> "$LOG_FILE"
        exit 0
    fi
fi
echo $$ > "$LOCK_FILE"

echo "=== Helmetsan Autonomous Enrichment Daemon Started (PID $$) ===" >> "$LOG_FILE"

# 3. THE LOOP
while true; do
    echo "$(date): Cycle Started." >> "$LOG_FILE"
    
    # 3.0 CHECK FOR MANUAL SYNC LOCK (With 30-min Auto-Expire)
    # If the lock exists and is < 30 mins old, skip the cycle.
    SYNC_LOCK="/tmp/helmetsan_sync.lock"
    if [ -f "$SYNC_LOCK" ]; then
        if find "$SYNC_LOCK" -mmin -30 | grep -q "$SYNC_LOCK"; then
            echo "$(date): Manual sync in progress (lock active). Skipping cycle." >> "$LOG_FILE"
            sleep 600 # Wait 10 mins before checking again
            continue
        else
            echo "$(date): Found stale sync lock (>30m). Removing." >> "$LOG_FILE"
            rm -f "$SYNC_LOCK"
        fi
    fi

    # 3.5 SYNC REMOTE CHANGES FIRST (Prevent Conflicts)
    if [ -d "$DATA_DIR/.git" ]; then
        echo "$(date): Pulling latest changes from GitHub..." >> "$LOG_FILE"
        cd "$DATA_DIR"
        git stash >> "$LOG_FILE" 2>&1
        git pull --rebase -X ours origin main >> "$LOG_FILE" 2>&1
        git stash pop >> "$LOG_FILE" 2>&1
        cd "$REPO_ROOT"
    fi

    # 4. RUN THE SWEEP (Server Mode, 20 items per cycle)
    # Using the local PHP script but pointing to the dynamic data dir
    /usr/bin/php "$REPO_ROOT/scripts/continuous-sweep.php" --mode=server --limit=20 >> "$LOG_FILE" 2>&1
    
    # 4.5 GENERATE MORNING REPORT
    /usr/bin/php "$REPO_ROOT/scripts/generate-morning-report.php" >> "$LOG_FILE" 2>&1

    # 5. GIT PUSH CHANGES
    if [ -d "$DATA_DIR/.git" ]; then
        echo "$(date): Synchronizing changes to GitHub..." >> "$LOG_FILE"
        cd "$DATA_DIR"
        git add .
        # Only commit if there are changes
        if ! git diff-index --quiet HEAD --; then
            git commit -m "Autonomous Heal: $(date '+%Y-%m-%d %H:%M')"
            git push origin main >> "$LOG_FILE" 2>&1
            echo "   ✓ Committed and Pushed to GitHub." >> "$LOG_FILE"
        else
            echo "   - No changes to commit." >> "$LOG_FILE"
        fi
        cd "$REPO_ROOT"
    fi

    # 5. RANDOMIZED SLEEP (6 to 10 hours)
    # 6 hours = 21600 seconds
    # Random addition up to 4 hours (14400 seconds)
    RAND_WAIT=$((21600 + RANDOM % 14400))
    WAIT_HOURS=$(echo "scale=2; $RAND_WAIT / 3600" | bc)
    
    echo "$(date): Cycle Finished. Sleeping for $WAIT_HOURS hours..." >> "$LOG_FILE"
    sleep "$RAND_WAIT"
done

rm "$LOCK_FILE"
