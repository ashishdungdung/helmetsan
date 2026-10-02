# Adversarial cross-verification and enhancement report

## Executive verdict

**Do not execute the supplied code in production.** The visible Context 1 implementation contains dry-run violations, unsafe lifecycle assumptions, and a lease design that does not prevent stale workers from mutating content. The supplied blueprint also **ends mid-expression inside the Class D branch**; it is not a complete, runnable script.

Contexts 2–5 are not present in the material supplied. There is no gettext cache, image pipeline, metadata sanitizer, Nginx configuration, Cloudflare policy, schema output, currency implementation, or recommendation formula to verify directly. The findings below therefore distinguish between **defects confirmed in the visible code** and **requirements for a defensible implementation**. Any claim that those omitted contexts are “production-safe” is unverified.

---

## 1. Context 1 — Orphan remediation and translation healing

### 1.1 Class A/B/C/D classification

The four classes are a useful workflow outline, but they are not a sufficient classification specification.

- **Class A — duplicate:** The manifest’s confidence and reason are trusted without being checked. The code does not constrain the target to an approved post type, verify matching product identifiers, verify that the canonical post is actually canonical, or verify that the canonical belongs to the appropriate language/translation group.
- **Class B — translation cluster:** The code checks that there are at least two existing posts, but does not verify that every ID is of the expected type, has the declared language, is unique, or is not already linked to a conflicting cluster. It can overwrite an existing Polylang grouping.
- **Class C — unique content:** This is not a remediation class so much as a workflow state. The code creates translation jobs without a deduplication key or idempotency check.
- **Class D — corrupt content:** The code says “mark them 410,” but storing `_hs_http_status = 410` does not itself make WordPress, Nginx, or the SEO layer return HTTP 410. The draft transition is also not equivalent to implementing and verifying a 410 response.

Require an allowlisted post type, explicit source evidence and versioned rules, and revalidation of the relevant evidence immediately before mutation. Approval should be bound to a manifest digest, run ID, environment, and approver—not merely to the existence of a JSON file.

### 1.2 Confirmed dry-run and execution defects

There are two direct contradictions of the stated dry-run-first control:

1. **Class C always creates a job**, even when `$apply` is false. The log labels it as unapplied, but `wp_insert_post()` has already mutated the database.
2. **Class D always writes post metadata**, even when `$apply` is false. Only the draft transition is gated by `$apply`.

Class B and Class A perform read-only work in their shown dry-run branches, but that does not make the overall script safe. The code is also **truncated in the middle of the Class D lease-release call**, so it cannot be run as presented.

### 1.3 Lease locking: race and recovery risks

The table can prevent some simultaneous claims, but it is not a safe mutation lock.

- **No fencing or ownership recheck at mutation time.** A worker can acquire a lease, pause long enough for it to expire, and then continue mutating after another worker has acquired the object. Checking the token only at acquisition does not fence a stale worker.
- **No heartbeat/renewal.** A long operation can outlive the 900-second lease.
- **A post-level lease does not protect a translation cluster.** Class B locks only the manifest item’s `post_id`, not every post in `translation_ids`. Different workers can concurrently modify overlapping Polylang clusters.
- **No `finally`-style release is shown.** Exceptions or process termination can leave a lease until expiry. Conversely, releasing by token does not prevent a stale worker that already passed its checks from continuing afterward.
- **No idempotency protection.** Expired `done` or `queued` leases can later be reclaimed, and Class A/Class C work can be duplicated.
- **Time handling deserves explicit verification.** The code writes a `gmdate()` value and compares it with `UTC_TIMESTAMP()`. That is coherent only if the database column and all writers are consistently treated as UTC.
- The upsert’s conditional assignments rely on MariaDB’s evaluation behavior and should be tested against the exact deployed MariaDB version. Avoid making correctness depend on subtle assignment-order assumptions.

**Enhance it:** use a monotonically increasing fencing/version value per lease, renew the lease during work, and require a current fencing value immediately before every mutation. For Class B, acquire locks for the *whole sorted cluster* or use a transaction/serialization mechanism that prevents overlapping cluster edits. Make operations idempotent with a unique remediation operation key and explicit state transitions.

### 1.4 Polylang serialized translation data and active swarms

If a background swarm directly edits Polylang’s serialized translation relationships, read-modify-write races can lose updates:

1. Worker A reads the old language-to-post map.
2. Worker B reads the same map and saves a different change.
3. Worker A saves its stale map, silently overwriting B’s change.

There are additional risks: two workers can create inconsistent groups, attach one post to competing groups, or leave one language pointing to a deleted or repurposed post. Caching can make the visible state stale even if the database write itself succeeds.

Use supported Polylang APIs, not direct serialized-meta edits, and serialize changes for the complete affected cluster. After saving, reread the group through Polylang and verify all members and languages. Drain or pause competing translation jobs during repair. The code’s public API preference is directionally sensible, but the internal `PLL()->model...` fallback is version-sensitive and should not be treated as a stable production contract.

### 1.5 `hs_redirect` insertion and `wp_delete_post()` lifecycle

The proposed sequence is not yet a working redirect lifecycle:

- A `wp_insert_post()` call does not implement redirect resolution. The shown code does not register `hs_redirect` or provide a resolver that reads its metadata and returns a 301.
- Inserting the redirect **before** deleting the source creates a window where the redirect and source coexist. A crash can leave that state behind.
- If deletion fails after redirect insertion, the redirect may shadow a still-live source. A rerun may insert duplicate redirect records.
- The target path is accepted from the manifest; it is not checked against the canonical post’s actual permalink. `esc_url_raw()` and extracting a path