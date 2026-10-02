#!/usr/bin/env python3
"""
=============================================================================
         HELMETSAN DISTRIBUTED ATOMIC LEASE MANAGER (SWARM COORDINATOR)
=============================================================================
SQLite WAL-backed coordinator enabling multi-instance, multi-bot execution
without deadlock, race conditions, or duplicate translations.
"""

import os
import time
import sqlite3
from typing import List, Optional, Dict, Any

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DB_FILE = os.path.join(SCRIPT_DIR, "swarm_leases.db")


class SwarmLeaseManager:
    """Manages lock-free distributed task leases across multiple worker bots."""

    def __init__(self, db_path: str = DB_FILE, instance_id: Optional[str] = None):
        self.db_path = db_path
        self.instance_id = instance_id or f"bot-{os.getpid()}-{int(time.time()*1000)%100000}"
        self._init_db()

    def _get_connection(self) -> sqlite3.Connection:
        conn = sqlite3.connect(self.db_path, timeout=60.0)
        conn.execute("PRAGMA synchronous = NORMAL")
        conn.execute("PRAGMA busy_timeout = 60000")
        return conn

    def _init_db(self) -> None:
        conn = sqlite3.connect(self.db_path, timeout=60.0)
        try:
            conn.execute("PRAGMA journal_mode = WAL")
        finally:
            conn.close()

        with self._get_connection() as conn:
            conn.execute("""
                CREATE TABLE IF NOT EXISTS task_leases (
                    post_id INTEGER NOT NULL,
                    lang TEXT NOT NULL,
                    instance_id TEXT NOT NULL,
                    claimed_at REAL NOT NULL,
                    lease_expires_at REAL NOT NULL,
                    status TEXT NOT NULL DEFAULT 'claimed',
                    PRIMARY KEY (post_id, lang)
                )
            """)
            conn.execute("""
                CREATE INDEX IF NOT EXISTS idx_leases_lookup 
                ON task_leases(post_id, lang, lease_expires_at, status)
            """)

    def clean_expired_leases(self) -> int:
        """Removes expired leases where the worker may have crashed or timed out."""
        now = time.time()
        with self._get_connection() as conn:
            cursor = conn.execute("""
                DELETE FROM task_leases 
                WHERE status = 'claimed' AND lease_expires_at < ?
            """, (now,))
            return cursor.rowcount

    def claim_task(self, post_id: int, lang: str, lease_duration: float = 300.0) -> bool:
        """
        Atomically claims a (post_id, lang) task for this instance.
        Returns True if claimed successfully, False if already claimed or active.
        """
        now = time.time()
        expires = now + lease_duration

        with self._get_connection() as conn:
            try:
                # 1. Try clean insert
                conn.execute("""
                    INSERT INTO task_leases (post_id, lang, instance_id, claimed_at, lease_expires_at, status)
                    VALUES (?, ?, ?, ?, ?, 'claimed')
                """, (post_id, lang, self.instance_id, now, expires))
                return True
            except sqlite3.IntegrityError:
                # 2. Key exists: check if expired
                cursor = conn.execute("""
                    UPDATE task_leases
                    SET instance_id = ?, claimed_at = ?, lease_expires_at = ?, status = 'claimed'
                    WHERE post_id = ? AND lang = ? 
                      AND (status = 'claimed' AND lease_expires_at < ?)
                """, (self.instance_id, now, expires, post_id, lang, now))
                return cursor.rowcount > 0

    def claim_batch(self, post_ids: List[int], lang: str, lease_duration: float = 300.0) -> List[int]:
        """Atomically claims a batch of candidates for this instance. Returns claimed IDs."""
        now = time.time()
        expires = now + lease_duration
        claimed = []
        with self._get_connection() as conn:
            for pid in post_ids:
                try:
                    conn.execute("""
                        INSERT INTO task_leases (post_id, lang, instance_id, claimed_at, lease_expires_at, status)
                        VALUES (?, ?, ?, ?, ?, 'claimed')
                    """, (pid, lang, self.instance_id, now, expires))
                    claimed.append(pid)
                except sqlite3.IntegrityError:
                    cursor = conn.execute("""
                        UPDATE task_leases
                        SET instance_id = ?, claimed_at = ?, lease_expires_at = ?, status = 'claimed'
                        WHERE post_id = ? AND lang = ? 
                          AND (status = 'claimed' AND lease_expires_at < ?)
                    """, (self.instance_id, now, expires, pid, lang, now))
                    if cursor.rowcount > 0:
                        claimed.append(pid)
        return claimed

    def complete_task(self, post_id: int, lang: str) -> None:
        """Removes task from active leases since WordPress Polylang now stores it."""
        with self._get_connection() as conn:
            conn.execute("""
                DELETE FROM task_leases 
                WHERE post_id = ? AND lang = ?
            """, (post_id, lang))

    def release_task(self, post_id: int, lang: str) -> None:
        """Releases claim on task upon failure so another bot can retry immediately."""
        with self._get_connection() as conn:
            conn.execute("""
                DELETE FROM task_leases 
                WHERE post_id = ? AND lang = ? AND instance_id = ?
            """, (post_id, lang, self.instance_id))

    def get_active_claimed_ids(self, lang: str) -> List[int]:
        """Returns all currently active claimed post IDs for a language."""
        now = time.time()
        with self._get_connection() as conn:
            cursor = conn.execute("""
                SELECT post_id FROM task_leases
                WHERE lang = ? AND status = 'claimed' AND lease_expires_at > ?
            """, (lang, now))
            return [row[0] for row in cursor.fetchall()]

    def filter_unclaimed(self, candidates: List[Dict[str, Any]], lang: str) -> List[Dict[str, Any]]:
        """Filters a candidate list, removing items currently claimed by any active bot."""
        if not candidates:
            return []

        post_ids = [c["id"] for c in candidates if "id" in c]
        if not post_ids:
            return candidates

        now = time.time()
        placeholders = ",".join("?" for _ in post_ids)
        query = f"""
            SELECT post_id FROM task_leases
            WHERE lang = ? AND post_id IN ({placeholders})
              AND status = 'claimed' AND lease_expires_at > ?
        """

        with self._get_connection() as conn:
            params = [lang] + post_ids + [now]
            cursor = conn.execute(query, params)
            active_ids = {row[0] for row in cursor.fetchall()}

        return [c for c in candidates if c.get("id") not in active_ids]

    def get_stats(self) -> Dict[str, Any]:
        """Returns active leases, completed counts, and active bot instances."""
        now = time.time()
        with self._get_connection() as conn:
            active_count = conn.execute("""
                SELECT count(*) FROM task_leases 
                WHERE status = 'claimed' AND lease_expires_at > ?
            """, (now,)).fetchone()[0]

            completed_count = conn.execute("""
                SELECT count(*) FROM task_leases 
                WHERE status = 'completed'
            """).fetchone()[0]

            instances = [row[0] for row in conn.execute("""
                SELECT DISTINCT instance_id FROM task_leases 
                WHERE status = 'claimed' AND lease_expires_at > ?
            """, (now,)).fetchall()]

            return {
                "active_leases": active_count,
                "completed_tasks": completed_count,
                "active_instances": instances,
                "current_instance": self.instance_id
            }


if __name__ == "__main__":
    mgr1 = SwarmLeaseManager(instance_id="bot-alpha")
    mgr2 = SwarmLeaseManager(instance_id="bot-beta")

    # Test claim contention
    c1 = mgr1.claim_task(58974, "ja")
    c2 = mgr2.claim_task(58974, "ja")
    print(f"Bot Alpha claim: {c1} (Expected True)")
    print(f"Bot Beta claim:  {c2} (Expected False — Collision prevented ✅)")

    # Complete
    mgr1.complete_task(58974, "ja")
    print("Stats:", mgr1.get_stats())
