#!/usr/bin/env python3
"""
=============================================================================
         HELMETSAN MULTI-BOT SWARM LAUNCHER & SUPERVISOR
=============================================================================
Orchestrates multiple concurrent bot instances running across different
shards and order directions to maximize throughput and achieve zero lock
contention across all target languages.

Usage:
  # Launch 3 bots (e.g. Shard 0/3, Shard 1/3, Shard 2/3)
  python3 HelmetsanWeb/scripts/launch_multi_swarm.py --num-bots 3 --workers-per-bot 4 --langs all --continuous

  # Check status across all running bots & catalog
  python3 HelmetsanWeb/scripts/launch_multi_swarm.py --status
"""

import sys
import os
import time
import signal
import argparse
import subprocess
from typing import List

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
BOT_SCRIPT = os.path.join(SCRIPT_DIR, "swarm_translation_bot.py")

GREEK_LETTERS = ["alpha", "beta", "gamma", "delta", "epsilon", "zeta", "eta", "theta"]


def main():
    parser = argparse.ArgumentParser(description="Helmetsan Multi-Bot Swarm Supervisor")
    parser.add_argument("--num-bots", type=int, default=4, help="Number of concurrent bot instances (default: 4)")
    parser.add_argument("--workers-per-bot", type=int, default=4, help="Worker threads per bot (default: 4)")
    parser.add_argument("--langs", default="all", help="Target languages or 'all'")
    parser.add_argument("--limit", type=int, default=20, help="Batch limit per fetch (default: 20)")
    parser.add_argument("--batch-size", type=int, default=10, help="Batch size before flushing to remote WP (default: 10)")
    parser.add_argument("--continuous", action="store_true", help="Keep bots running continuously")
    parser.add_argument("--dry-run", action="store_true", help="Run without persisting translations to WordPress")
    parser.add_argument("--status", action="store_true", help="Show active bot status and catalog counts")
    args = parser.parse_args()

    if args.status:
        subprocess.run([sys.executable, BOT_SCRIPT, "--status"])
        return

    num_bots = max(1, min(8, args.num_bots))
    processes: List[subprocess.Popen] = []

    print("=" * 70)
    print("   HELMETSAN MULTI-BOT SWARM SUPERVISOR")
    print(f"   Launching {num_bots} autonomous bot instances...")
    print(f"   Threads per bot: {args.workers_per_bot} | Total Concurrency: {num_bots * args.workers_per_bot} workers")
    print(f"   Target Locales: {args.langs} | Mode: {'Continuous Loop' if args.continuous else 'Single Batch'}")
    print("=" * 70)

    def shutdown(sig, frame):
        print("\n🛑 Shutdown signal received! Terminating swarm bots gracefully...")
        for p in processes:
            try:
                p.terminate()
            except Exception:
                pass
        for p in processes:
            try:
                p.wait(timeout=5)
            except Exception:
                p.kill()
        print("✅ All swarm bots stopped.")
        sys.exit(0)

    signal.signal(signal.SIGINT, shutdown)
    signal.signal(signal.SIGTERM, shutdown)

    # Spawn bot instances with complementary sharding and ordering strategies
    for i in range(num_bots):
        bot_name = f"bot-{GREEK_LETTERS[i % len(GREEK_LETTERS)]}"
        order = "desc" if (i % 2 == 1) else "asc"

        cmd = [
            sys.executable,
            "-u", # Unbuffered stdout
            BOT_SCRIPT,
            f"--langs={args.langs}",
            f"--workers={args.workers_per_bot}",
            f"--limit={args.limit}",
            f"--batch-size={args.batch_size}",
            f"--shard-id={i}",
            f"--num-shards={num_bots}",
            f"--order={order}",
            f"--instance-id={bot_name}"
        ]

        if args.continuous:
            cmd.append("--continuous")
        if args.dry_run:
            cmd.append("--dry-run")

        print(f"🚀 Spawning [{bot_name.upper()}]: Shard {i}/{num_bots} | Order: {order.upper()}...")
        p = subprocess.Popen(cmd)
        processes.append(p)
        time.sleep(1.0) # Stagger launches

    print(f"\n⚡ All {num_bots} swarm bots active and translating in parallel!\n")

    # Monitor loop
    try:
        while True:
            alive = sum(1 for p in processes if p.poll() is None)
            if alive == 0:
                print("🏁 All bot processes completed.")
                break
            time.sleep(2)
    except KeyboardInterrupt:
        shutdown(None, None)


if __name__ == "__main__":
    main()
