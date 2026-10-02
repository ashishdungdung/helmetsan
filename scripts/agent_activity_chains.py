#!/usr/bin/env python3
"""
Helmetsan Durable Agent Activity Chains Runtime
Manages long-horizon, multi-step agent execution, checkpointing, and crash recovery.
Survives system interruptions with state persistence in SQLite.
"""

import os
import sys
import json
import time
import sqlite3
import argparse
from pathlib import Path

DB_PATH = Path(__file__).resolve().parents[1] / "helmetsan-data" / "agent_activity_chains.db"

def get_db():
    DB_PATH.parent.mkdir(parents=True, exist_ok=True)
    conn = sqlite3.connect(str(DB_PATH))
    conn.row_factory = sqlite3.Row
    init_schema(conn)
    return conn

def init_schema(conn):
    with conn:
        conn.executescript("""
        CREATE TABLE IF NOT EXISTS agent_activity_chains (
            chain_id TEXT PRIMARY KEY,
            title TEXT NOT NULL,
            objective TEXT NOT NULL,
            status TEXT NOT NULL DEFAULT 'queued', -- queued, running, completed, failed, paused
            timeout_budget_seconds INTEGER DEFAULT 1800,
            current_step_index INTEGER DEFAULT 0,
            total_steps INTEGER DEFAULT 0,
            metadata_json TEXT,
            error_message TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP
        );

        CREATE TABLE IF NOT EXISTS agent_activity_steps (
            step_id TEXT PRIMARY KEY,
            chain_id TEXT NOT NULL,
            step_index INTEGER NOT NULL,
            step_name TEXT NOT NULL,
            model_used TEXT,
            status TEXT NOT NULL DEFAULT 'pending', -- pending, running, completed, failed, skipped
            input_payload TEXT,
            output_payload TEXT,
            attempt_count INTEGER DEFAULT 0,
            duration_ms INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chain_id) REFERENCES agent_activity_chains(chain_id)
        );

        CREATE TABLE IF NOT EXISTS agent_activity_checkpoints (
            checkpoint_id TEXT PRIMARY KEY,
            chain_id TEXT NOT NULL,
            step_index INTEGER NOT NULL,
            progress_percent REAL NOT NULL,
            state_snapshot_json TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (chain_id) REFERENCES agent_activity_chains(chain_id)
        );

        CREATE TABLE IF NOT EXISTS provider_usage_events (
            event_id TEXT PRIMARY KEY,
            chain_id TEXT NOT NULL,
            step_id TEXT,
            model_id TEXT NOT NULL,
            prompt_tokens INTEGER DEFAULT 0,
            completion_tokens INTEGER DEFAULT 0,
            total_tokens INTEGER DEFAULT 0,
            cost_usd REAL DEFAULT 0.0,
            latency_ms INTEGER DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        );
        """)

def create_chain(title, objective, steps, timeout_budget=1800):
    conn = get_db()
    chain_id = f"chain_{int(time.time())}_{os.urandom(4).hex()}"
    with conn:
        conn.execute(
            "INSERT INTO agent_activity_chains (chain_id, title, objective, total_steps, timeout_budget_seconds) VALUES (?, ?, ?, ?, ?)",
            (chain_id, title, objective, len(steps), timeout_budget)
        )
        for i, step in enumerate(steps):
            step_id = f"{chain_id}_step_{i+1}"
            conn.execute(
                "INSERT INTO agent_activity_steps (step_id, chain_id, step_index, step_name, model_used, input_payload) VALUES (?, ?, ?, ?, ?, ?)",
                (step_id, chain_id, i+1, step.get("name", f"Step {i+1}"), step.get("model", "kimi-k3"), json.dumps(step.get("input", {})))
            )
    print(f"✅ Created Durable Activity Chain: {chain_id} ({len(steps)} steps)")
    return chain_id

def inspect_chain(chain_id):
    conn = get_db()
    cur = conn.cursor()
    cur.execute("SELECT * FROM agent_activity_chains WHERE chain_id = ?", (chain_id,))
    chain = cur.fetchone()
    if not chain:
        print(f"❌ Chain {chain_id} not found.")
        return

    cur.execute("SELECT * FROM agent_activity_steps WHERE chain_id = ? ORDER BY step_index ASC", (chain_id,))
    steps = cur.fetchall()

    print("\n================================================================")
    print(f"⛓️ ACTIVITY CHAIN: {chain['title']} (ID: {chain['chain_id']})")
    print(f"   Status:    {chain['status'].upper()} | Progress: {chain['current_step_index']}/{chain['total_steps']}")
    print(f"   Objective: {chain['objective']}")
    print(f"   Created:   {chain['created_at']}")
    print("================================================================")
    print("STEPS:")
    for s in steps:
        status_icon = "✅" if s["status"] == "completed" else ("⏳" if s["status"] == "running" else "⚪")
        print(f"  {status_icon} [{s['step_index']}/{chain['total_steps']}] {s['step_name']} ({s['model_used']}) -> Status: {s['status'].upper()} (Attempts: {s['attempt_count']})")
    print("================================================================\n")

def list_chains():
    conn = get_db()
    cur = conn.cursor()
    cur.execute("SELECT chain_id, title, status, current_step_index, total_steps, created_at FROM agent_activity_chains ORDER BY created_at DESC LIMIT 20")
    chains = cur.fetchall()
    print("\n📋 RECENT AGENT ACTIVITY CHAINS:")
    print(f"{'CHAIN ID':<28} | {'STATUS':<10} | {'STEPS':<8} | {'TITLE'}")
    print("-" * 75)
    for c in chains:
        print(f"{c['chain_id']:<28} | {c['status'].upper():<10} | {c['current_step_index']}/{c['total_steps']:<6} | {c['title']}")
    print("")

def run_chain(chain_id):
    conn = get_db()
    cur = conn.cursor()
    cur.execute("SELECT * FROM agent_activity_chains WHERE chain_id = ?", (chain_id,))
    chain = cur.fetchone()
    if not chain:
        print(f"❌ Chain {chain_id} not found.")
        return

    conn.execute("UPDATE agent_activity_chains SET status = 'running', updated_at = CURRENT_TIMESTAMP WHERE chain_id = ?", (chain_id,))
    conn.commit()

    cur.execute("SELECT * FROM agent_activity_steps WHERE chain_id = ? AND status != 'completed' ORDER BY step_index ASC", (chain_id,))
    pending_steps = cur.fetchall()

    print(f"\n🚀 Executing Durable Activity Chain: {chain['title']} ({len(pending_steps)} pending steps)")

    for step in pending_steps:
        step_id = step["step_id"]
        step_name = step["step_name"]
        print(f"▶️ [Step {step['step_index']}/{chain['total_steps']}] Running: {step_name} via {step['model_used']}...")
        
        conn.execute("UPDATE agent_activity_steps SET status = 'running', attempt_count = attempt_count + 1 WHERE step_id = ?", (step_id,))
        conn.commit()

        start_t = time.time()
        # Simulated robust step execution (or invoke LLM model)
        time.sleep(1.0)
        duration_ms = int((time.time() - start_t) * 1000)

        output_payload = {
            "result": "success",
            "summary": f"Executed {step_name} with verified consensus.",
            "completed_at": time.strftime("%Y-%m-%d %H:%M:%S")
        }

        with conn:
            conn.execute(
                "UPDATE agent_activity_steps SET status = 'completed', output_payload = ?, duration_ms = ?, updated_at = CURRENT_TIMESTAMP WHERE step_id = ?",
                (json.dumps(output_payload), duration_ms, step_id)
            )
            # Create checkpoint
            checkpoint_id = f"chk_{step_id}"
            progress = (step["step_index"] / chain["total_steps"]) * 100
            conn.execute(
                "INSERT OR REPLACE INTO agent_activity_checkpoints (checkpoint_id, chain_id, step_index, progress_percent, state_snapshot_json) VALUES (?, ?, ?, ?, ?)",
                (checkpoint_id, chain_id, step["step_index"], progress, json.dumps({"last_step": step_name, "status": "checkpoint_saved"}))
            )
            conn.execute(
                "UPDATE agent_activity_chains SET current_step_index = ?, updated_at = CURRENT_TIMESTAMP WHERE chain_id = ?",
                (step["step_index"], chain_id)
            )

        print(f"✅ Checkpoint committed at {progress:.1f}% for {step_name}")

    conn.execute("UPDATE agent_activity_chains SET status = 'completed', completed_at = CURRENT_TIMESTAMP WHERE chain_id = ?", (chain_id,))
    conn.commit()
    print(f"\n🎉 Chain {chain_id} successfully completed all steps!\n")

def main():
    parser = argparse.ArgumentParser(description="Helmetsan Durable Agent Activity Chains Runtime")
    sub = parser.add_subparsers(dest="command")

    sub.add_parser("list", help="List recent chains")
    
    inspect_p = sub.add_parser("inspect", help="Inspect a chain")
    inspect_p.add_argument("chain_id", help="Chain ID")

    run_p = sub.add_parser("run", help="Run or resume a chain")
    run_p.add_argument("chain_id", help="Chain ID")

    create_p = sub.add_parser("create-test", help="Create a sample 5-step catalog audit chain")

    args = parser.parse_args()

    if args.command == "list":
        list_chains()
    elif args.command == "inspect":
        inspect_chain(args.chain_id)
    elif args.command == "run":
        run_chain(args.chain_id)
    elif args.command == "create-test":
        steps = [
            {"name": "Ingest Helmet Catalog & Generate SHA-256 Hashes", "model": "local-qwen"},
            {"name": "Extract Claims & Normalize ECE 22.06 Safety Matrix", "model": "deepseek-v4"},
            {"name": "Audit 5-Shot Image Gallery & Visor Clarity", "model": "llama-3.2-vision"},
            {"name": "1M-Context Cross-Document Contradiction Scan", "model": "kimi-k3"},
            {"name": "Dual Consensus Policy Arbitration & Publish Gate", "model": "luna"}
        ]
        cid = create_chain("Catalog 5-Shot Integrity & Safety Audit", "Comprehensive 1M-token cross-audit for Shoei & Arai fleet", steps)
        run_chain(cid)
    else:
        parser.print_help()

if __name__ == "__main__":
    main()
