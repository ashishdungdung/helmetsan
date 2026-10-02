# Red-Team cross-audit

## Executive finding

**Do not authorize deletion of 969 posts from this excerpt.** It supports a count of 969 language-assigned posts that are *not counted as clustered*—assuming the queries and snapshots are sound. It does **not** prove that all 969 are duplicates, disposable, or even erroneous.

There is also a definite arithmetic defect in the directive: the per-language rows do not sum to the stated totals. I cannot independently verify “live forensic audit data” from the excerpt; the underlying queries, timestamps, database state, and Polylang relationships were not supplied.

## 1. Forensic-data and remediation defects

### The table totals are wrong

Summing the displayed rows gives:

- **Total published rows:** 99,084, not 99,204.
- **Total clustered rows:** 98,115, not 98,235.
- **Difference:** 969.

The per-language orphan figures do sum to **969**, so the subtraction still works because both stated aggregates are inflated by the same 120. But the “exact mathematical diagnosis” is not exact as printed. Correct the totals and retain the query output that produced them before using these figures for a production change.

The comparison also assumes that \(C_L\) is a subset of \(T_L\), both are measured at the same point in time, and both use identical post-type, status, language, and cluster-validity rules. Those assumptions need to be demonstrated—not inferred from the table.

### “Unclustered” does not mean “duplicate”

The data, as presented, establishes at most that 969 rows are not participating in whatever the audit defines as a valid cluster. It does not establish that they are extra duplicates. They may be unique products, incomplete translations, wrong-language assignments, damaged relationships, or query artifacts.

The retry race is a plausible failure mode, **not a demonstrated root cause**. No worker logs, import identities, duplicate fingerprints, or transaction traces are provided. Likewise, calling the rows “additional published rows outside valid translation clusters” is safer than calling them duplicate products; the directive should not turn that description into a deletion assumption.

### The proposed repair-table lock is not transaction protection

A `LOCKED` status or `SELECT … FOR UPDATE` on the repair queue does not make a repair atomic across WordPress post writes, taxonomy changes, Polylang operations, redirect deployment, and cache purges. A worker can fail between any of those steps. The operation must be restartable and idempotent, with a durable state machine and post-action verification. Do not rely on a spreadsheet or a queue-row lock as proof that partial work cannot occur.

The supplied excerpt ends mid-instruction at “supported Polylang in…”, so the complete healing procedure cannot be audited. In particular, it is not possible to confirm that later steps avoid direct Polylang table or taxonomy writes.

---

## 2. Fatal risks in orphan remediation

### Direct MariaDB deletion

**Highest risk; do not use as the normal deletion path.** Standard WordPress installations generally do not enforce foreign keys between core tables. Therefore a direct `DELETE` from `wp_posts` may succeed while leaving:

- `wp_postmeta` rows;
- `wp_term_relationships` rows and potentially stale taxonomy counts;
- plugin-specific records or Polylang-related state;
- stale WordPress object-cache, sitemap, SEO-plugin, and page-cache entries;
- attachment or other post relationships that WordPress hooks would normally process.

If the installation has custom foreign keys, direct deletion may instead fail or trigger custom cascades; neither behavior should be assumed. Direct SQL also bypasses WordPress and plugin lifecycle hooks. The precise damage depends on the schema and plugin versions.

### WP-CLI

WP-CLI is **not inherently safe because it