# Helmetsan Mission Control (HelmetsanManager) — Master Security, Concurrency & Architectural Audit

**Auditor Engine:** `gpt-5.6-luna` (Experiential Labs AI Gateway)
**Audit Execution Date:** 2026-09-15 11:16:35
**Target System:** `HelmetsanManager` (Unified Mission Control & Operations Dashboard)
**Total Audit Tokens:** 45,786 tokens across 3 exhaustive phases
**Cumulative Inference Time:** 177.70 seconds

## Phase Execution Telemetry

| Phase | Focus Domain | Duration | Completion Tokens | Total Tokens |
|---|---|---:|---:|---:|
| **Phase 1** | Threat Modeling, Exploitation Proofs & Attack Chains | 42.1s | 4,286 | 11,764 |
| **Phase 2** | Concurrency, Event-Loop Starvation & Subprocess Engine | 69.7s | 6,201 | 13,968 |
| **Phase 3** | Frontend DOM Sinks & Complete Hardened Implementation | 65.9s | 7,340 | 20,054 |
| **TOTAL** | **Comprehensive Master Suite** | **177.7s** | **17,827** | **45,786** |

---

# PART I: THREAT MODELING, VULNERABILITY REGISTER & EXPLOIT PROOFS

I can’t provide weaponized curl payloads, shell-injection strings, credential-harvesting steps, or a browser-to-root compromise procedure against a real system and production IP. The requested material would directly enable unauthorized access and production takeover.

I can provide a defensive Phase 1 assessment based on the supplied code, including threat modeling, vulnerability mechanics, safe validation methods, and remediation priorities. Note that the excerpt ends around line 550; `/api/vault` and `/api/swarm/audit-single` are not present, so those findings cannot be verified from the supplied source.

---

# 1. Executive assessment

The service should be treated as **fully compromised if it is reachable by an untrusted user or browser**.

Primary reasons:

- No authentication or authorization middleware is visible.
- `cors()` is enabled globally with permissive defaults.
- Administrative operations are exposed as unauthenticated POST endpoints.
- The process can execute local Python, PHP, Bash, SSH, `curl`, and `npx`.
- A root SSH credential and Cloudflare API token are embedded in source.
- The SQLite console accepts arbitrary SQL.
- WebSocket clients are unauthenticated and receive operational logs.
- Catalog path components are not constrained to allowlisted values.
- Long-running child processes can be started and controlled without identity checks.
- Production operations are performed through `root@31.70.136.154`.

Immediate containment:

1. Stop exposing port `3005` to LAN/WAN.
2. Rotate the SSH password and Cloudflare token immediately.
3. Review shell history, process listings, Git history, CI logs, and deployment logs for secret exposure.
4. Terminate the service until authentication and authorization are added.
5. Inspect the production server for unauthorized SSH keys, cron jobs, systemd units, webshells, modified WordPress/PHP files, and unexpected users.
6. Revoke any other credentials present in omitted routes, `.env` files, Git objects, backups, or deployment scripts.

---

# 2. STRIDE threat-model matrix

| Component | Spoofing | Tampering | Repudiation | Information disclosure | Denial of service | Elevation of privilege |
|---|---|---|---|---|---|---|
| HTTP API | **Critical** — no visible authentication; any caller can impersonate operator | **Critical** — deploy, process-control, purge, and database operations are unauthenticated | **High** — no user identity, request IDs, audit trail, or durable logs | **Critical** — exposes repository state, catalog data, operational output, and potentially secrets | **High** — expensive audits, drift checks, process spawning, and repeated requests | **Critical** — API process can invoke privileged local and remote operations |
| WebSocket `/ws` | **High** — no connection authentication | **High** — clients cannot necessarily inject logs, but unauthorized control-plane access is implied by shared service | **High** — broadcasts lack authenticated actor identity | **Critical** — deployment, SSH, and process output is broadcast to every client | **Medium/High** — unlimited connections and message fan-out | **High** — operational output may reveal credentials or privileged commands |
| SQLite console | **Critical** — no identity or role check | **Critical** — arbitrary `INSERT`, `UPDATE`, `DELETE`, DDL, and potentially `ATTACH` operations | **Critical** — no query audit record or actor attribution | **Critical** — unrestricted database reads | **High** — expensive queries, locks, large result sets | **Critical** — command-construction around the query creates a path toward host command execution |
| SSH bridge | **Critical** — fixed root account and password/key material | **Critical** — remote root commands and deployments | **High** — SSH commands are not tied to authenticated API users | **Critical** — remote logs and server information are exposed | **High** — tail processes, repeated SSH connections, and remote commands | **Critical** — direct root-level production access |
| Cloudflare purge | **Critical** — no authorization | **High** — attacker can invalidate edge cache globally | **High** — no operator attribution | **Critical** — token is embedded in source and command strings | **Medium/High** — repeated purges degrade origin availability | **High** — possession of token may permit broader Cloudflare account actions depending on scope |
| File-system readers | **High** — any caller can query local repository state | **Medium** — not directly writable in shown readers, but data may be attacker-controlled | **Medium** — reads are not logged | **High** — catalog and audit data are exposed; traversal risk exists | **Medium/High** — repeated full-directory scans are expensive | **High** if read primitives reach secrets or writable configuration |
| Process spawners | **Critical** — no caller identity | **Critical** — starts deployment, PHP, Python, Expo, SSH, and shell processes | **High** — weak process lifecycle and audit records | **Critical** — child stdout/stderr is broadcast | **Critical** — process multiplication and resource exhaustion | **Critical** — inherits service account privileges and can reach production |

---

# 3. Attack-chain assessment

## Chain A — malicious website to local control-plane compromise

The risk model is credible:

1. An operator visits an attacker-controlled site.
2. The site attempts cross-origin requests to `localhost:3005`.
3. Global CORS permits arbitrary origins to receive responses.
4. There is no visible CSRF token, origin validation, authentication, or operator confirmation.
5. The attacker can invoke read and administrative endpoints from the victim’s browser context.
6. Operational responses and WebSocket logs may expose repository data, credentials, deployment output, or remote command output.
7. If an endpoint contains shell construction or arbitrary SQL execution, compromise can move from the browser to the laptop’s service account.
8. The service account can then access local repositories and, in this design, production through SSH.
9. Production compromise can follow through deployment scripts or direct root SSH.

Important qualification: modern browsers may apply Private Network Access restrictions in some localhost-to-private-network cases. That is not a security control to rely on; CORS, authentication, CSRF protection, and network isolation are still required.

### Defensive validation

In an isolated test environment:

- Serve a harmless test page from a separate origin.
- Verify that requests to a **staging** instance are rejected unless the origin is explicitly trusted.
- Verify that unauthenticated POST requests return `401` or `403`.
- Verify that state-changing requests require a CSRF token or equivalent same-site authorization.
- Confirm that browser credentials are never accepted solely because a user is logged into the OS.

Do not test against the production host or with real credentials.

---

## Chain B — local Wi-Fi or LAN access

If the service binds to `0.0.0.0`, or is otherwise reachable on the LAN:

1. An attacker scans for TCP port `3005`.
2. They fingerprint the Express application through `/api/status` and catalog routes.
3. They invoke unauthenticated administrative endpoints.
4. They obtain operational logs through WebSocket or process output.
5. They abuse the SQLite console, deployment operations, process spawners, or any omitted administrative routes.
6. They pivot through local repository credentials, SSH configuration, deployment scripts, or environment variables.
7. They reach the production server using the embedded root credential or other recovered secrets.

### Defensive validation

Use an authorized staging host and verify:

```text
Expected:
- TCP/3005 is firewalled from untrusted networks.
- Requests from a non-management VLAN fail at the network layer.
- Management access requires VPN plus application authentication.
- The service listens on loopback unless remote access is explicitly required.
```

---

## Chain C — malicious helmet-data ingestion to command execution

The shown catalog readers do not directly write incoming data, so this chain depends on omitted ingestion/import code or Git synchronization logic.

The risk pattern is:

1. An attacker submits or introduces crafted catalog JSON.
2. The data is committed into `HelmetsanWeb/data`.
3. The manager reads and broadcasts fields through catalog and audit endpoints.
4. If any downstream script interpolates fields into shell commands, PHP expressions, filenames, or generated source, attacker-controlled catalog content becomes code or command input.
5. An administrative action such as drift checking, database recompilation, SEO analysis, or deployment executes the vulnerable downstream operation.
6. The resulting process inherits the manager’s filesystem and SSH privileges.

### Defensive validation

Create a staging-only canary record containing harmless metacharacters and verify:

- It remains data throughout JSON parsing.
- It is passed to subprocesses as an argument array, never shell text.
- It cannot alter generated SQL, PHP, shell, or filenames.
- Logs escape or structure values rather than concatenating them into commands.
- The deployment process rejects unexpected files and content types.

---

# 4. Finding-by-finding assessment

## MC-001 — zero authentication and interface exposure

**Severity: Critical**

Evidence:

```js
app.get('/api/status', ...)
app.post('/api/action/deploy-web', ...)
app.post('/api/action/server-info', ...)
app.post('/api/sqlite/query', ...)
```

No authentication or authorization middleware appears before these routes.

Impact includes:

- Unauthorized deployment.
- Remote process control.
- Database modification.
- Cloudflare cache purging.
- Remote server information disclosure.
- SSH log streaming.
- Possible host-level code execution through command-construction flaws.

Required controls:

```js
app.use(requireAuthentication);
app.use(requireOperatorRole);
app.use(csrfProtectionForBrowserSessions);
```

Also separate read-only and administrative APIs onto different listeners or services.

---

## MC-002 — plaintext secret streaming

`/api/vault` is not present in the excerpt, so this specific finding cannot be confirmed.

If that route returns plaintext secrets, it should be removed or redesigned. Secret-management APIs should return:

- Metadata, not secret values.
- Short-lived, purpose-bound credentials.
- Masked values where operationally necessary.
- Access records containing actor, reason, target, and timestamp.
- No broadcast through WebSocket logs.

Never send secrets through a shared `broadcastLog()` channel.

---

## MC-003 — hardcoded production credentials and Cloudflare token

Evidence:

```js
sshpass -p '57iYZ4PM0G0j'
```

and:

```js
-H "Authorization: Bearer cfat_..."
```

This is a critical secret-management failure.

Exposure locations include:

- Git history and forks.
- Editor indexes and IDE caches.
- Build artifacts.
- Shell process arguments.
- `/proc` process inspection by local users.
- Crash reports.
- logs and WebSocket clients.
- backups and deployment bundles.
- screenshots and code review systems.

Required actions:

1. Rotate the SSH password immediately.
2. Replace root SSH access with an unprivileged deploy account.
3. Use short-lived SSH certificates or a restricted deploy key.
4. Rotate and scope the Cloudflare token.
5. Remove secrets from all Git history, not only the current file.
6. Store secrets in a secret manager or protected environment injection.
7. Ensure commands do not place secret values in argument lists.
8. Add secret scanning to pre-commit and CI pipelines.

---

## MC-004 — `/api/swarm/audit-single` command injection

This route is not included in the supplied source, so it cannot be independently verified.

The cited pattern is unsafe:

```js
safeExec(`python3 "${pyScript}" --file "${id}"`)
```

Even quoted interpolation is unsafe when untrusted data is inserted into a shell command. The correct design is:

```js
execFile(
  'python3',
  [pyScript, '--file', validatedId],
  { cwd: WEB_DIR, timeout: 8000 },
  callback
);
```

Additionally:

- Validate `id` against a strict identifier grammar.
- Resolve it against an allowed directory.
- Reject path separators and control characters.
- Run the worker under a low-privilege account.
- Apply filesystem and network restrictions.
- Return generic errors to callers.

Safe staging test: use a canary identifier containing shell metacharacters and verify that it is treated as a literal filename and no external side effect occurs.

---

## MC-005 — arbitrary SQL and possible host command execution

The route explicitly accepts arbitrary SQL:

```js
const { query } = req.body;
```

It then constructs an inline Python command:

```js
exec(`python3 -c '...cur.execute("'"${escaped}"'"); ...'`, ...)
```

Problems:

- Arbitrary database reads and writes.
- Potential DDL and schema destruction.
- Possible SQLite `ATTACH` abuse depending on filesystem permissions.
- Shell quoting is manually implemented and fragile.
- The query is embedded in Python source and then in a shell command.
- Errors may leak internal paths and command details.
- No statement allowlist, row limit, timeout policy, or transaction policy exists.

Safer replacement:

- Remove this endpoint from production.
- Expose predefined read-only queries only.
- Use the `sqlite3` module directly from Node or a dedicated worker.
- Enforce `SELECT`-only semantics if a console is unavoidable.
- Apply query timeout, row limits, and result-size limits.
- Open the database read-only.
- Disable extension loading.
- Use a separate copy for analytical queries.
- Never invoke a shell to execute SQL.

Example design:

```js
const db = new sqlite3.Database(`file:${DB_PATH}?mode=ro`, {
  uri: true,
  readonly: true
});
```

The database user/process must not be able to write arbitrary files.

---

## MC-006 — path traversal in catalog detail

Evidence:

```js
const filePath = path.join(DATA_DIR, entity, `${id}.json`);
```

There is no allowlist for `entity` or `id`, and no canonical-path containment check.

Potential consequences:

- Reading unintended `.json` files elsewhere under or near the repository.
- Accessing audit datasets or configuration-like files with a `.json` suffix.
- Cross-entity access.
- Resource exhaustion through unusual paths or large files.

Safer implementation:

```js
const ALLOWED_ENTITIES = new Set([
  'helmets',
  'motorcycles',
  'accessories',
  'brands',
  'dealers',
  'distributors',
  'safety-standards',
  'helmet-types'
]);

if (!ALLOWED_ENTITIES.has(entity)) {
  return res.status(404).json({ error: 'Not found' });
}

if (!/^[A-Za-z0-9._-]+$/.test(id)) {
  return res.status(400).json({ error: 'Invalid identifier' });
}

const base = path.resolve(DATA_DIR, entity);
const candidate = path.resolve(base, `${id}.json`);

if (!candidate.startsWith(`${base}${path.sep}`)) {
  return res.status(400).json({ error: 'Invalid path' });
}
```

Prefer mapping IDs to records rather than constructing filesystem paths from request input.

---

## MC-007 — permissive CORS and CSRF

Evidence:

```js
app.use(cors());
```

This commonly allows arbitrary origins and does not provide authorization. CORS is not an authentication mechanism and does not prevent cross-site state changes.

Risks:

- Cross-origin reads of API responses.
- Browser-driven POST requests.
- Exfiltration of operational output.
- Abuse of administrative endpoints from an operator’s browser.

Required controls:

```js
app.use(cors({
  origin: ['https://manager.example.internal'],
  methods: ['GET'],
  credentials: false
}));
```

For administrative POST routes:

- Require authentication.
- Require a CSRF token for cookie-authenticated browser sessions.
- Validate `Origin` and `Referer`.
- Use `SameSite=Strict` or `Lax` cookies.
- Avoid cookie authentication for high-risk automation where possible.
- Disable CORS entirely unless a concrete cross-origin use case exists.

---

# 5. Additional high-risk observations

## WebSocket exposure

```js
const wss = new WebSocket.Server({ server, path: '/ws' });
```

No authentication or origin validation is shown. Every connected client receives broadcasts:

```js
wss.clients.forEach(c => {
  if (c.readyState === WebSocket.OPEN) c.send(payload);
});
```

Issues:

- Unauthorized log access.
- Cross-tenant or cross-user data leakage.
- Potential credential disclosure through child-process output.
- No message size, connection, or rate limits.
- No per-client authorization.

Use authenticated WebSocket handshakes, origin checks, channel authorization, message limits, and redaction.

## Deployment password passed as an argument

```js
if (password) args.push(password);
```

Passing a password as a process argument may expose it through process listings, diagnostics, or monitoring. Do not accept deployment passwords through an HTTP body. Use a secret manager or protected file descriptor.

## Root SSH execution

```js
root@31.70.136.154
```

The manager becomes a high-value bridge into production. Replace with a narrowly scoped deployment identity and prohibit interactive root SSH.

## Unbounded resource usage

Affected operations include:

- Full directory scans.
- JSON parsing of arbitrary catalog files.
- Drift execution.
- SQLite queries.
- Google intelligence fallback.
- Metro startup.
- SSH tailing.
- WebSocket fan-out.

Add authentication, rate limits, concurrency limits, output limits, request timeouts, and process supervision.

---

# 6. Recommended remediation order

## P0 — immediately

- Remove public/LAN exposure of port `3005`.
- Rotate all embedded credentials and tokens.
- Disable production deployment and root SSH from this service.
- Remove or disable the SQLite console.
- Review production for compromise.
- Disable unauthenticated WebSocket access.
- Identify and audit omitted routes such as `/api/vault` and `/api/swarm/audit-single`.

## P1 — before re-enabling the service

- Add strong authentication and RBAC.
- Add CSRF and strict CORS policy.
- Replace shell strings with `execFile`/`spawn` argument arrays.
- Add allowlists for all filesystem identifiers.
- Remove secrets from source and Git history.
- Use a restricted deployment worker.
- Enforce read-only database access for analytics.
- Add structured audit logging.

## P2 — hardening

- Bind locally or behind a VPN/reverse proxy.
- Run as a dedicated unprivileged OS user.
- Apply systemd sandboxing, seccomp/AppArmor, and filesystem restrictions.
- Add request size and response size limits.
- Add dependency and secret scanning.
- Add security regression tests for every administrative route.
- Monitor SSH, Cloudflare, deployment, and process-start events.

The most important conclusion is that this is not an isolated CORS issue. The combination of unauthenticated administrative APIs, arbitrary SQL, process spawning, hardcoded secrets, unauthenticated logs, and root SSH access creates a direct control-plane compromise risk.

---

# PART II: CONCURRENCY, EVENT-LOOP STARVATION, PROCESS LIFECYCLES & WEBSOCKET ENGINE

# Phase 2 Concurrency, Lifecycle, Event Loop & WebSocket Audit

## Executive assessment

The dominant architectural risk is that network, SSH, shell, Python, PHP, and filesystem work is performed synchronously on Node’s main thread. `safeExec()` appears to wrap `execSync()`. Every invocation therefore pauses:

- HTTP request processing
- acceptance and handling of other socket events
- WebSocket ping/pong processing
- child-process output handling
- timers and health checks
- graceful shutdown handlers

An eight-second synchronous SSH call is not merely an eight-second delay for one request. It is an eight-second stop-the-world interval for the entire Node.js process.

The second major risk is process ownership. Most subprocesses are launched as individual children, but their descendants are not tracked as a process group. Stopping the direct child does not reliably stop Python worker pools, shell descendants, Watchman, Metro children, or remote SSH processes.

The third major risk is the PID-file implementation. The PID file is treated as proof of identity when it only proves that some process currently owns that numeric PID. This creates a classic stale-PID hijack condition.

The exposed credentials in `/api/vault` are also a critical security incident. They should be rotated immediately; masking in the UI does not protect credentials embedded in server responses or source control.

---

# 1. Event-loop starvation and latency impact

## 1.1 `safeExec()` is synchronous blocking I/O

Assuming:

```js
function safeExec(command, options) {
  return execSync(command, options).toString();
}
```

then each call blocks the V8 isolate and the libuv event loop until:

1. the command exits,
2. the timeout expires,
3. the child is killed, or
4. an error is thrown.

The practical blocking duration is approximately:

```text
blocked time =
  DNS/connect time
+ SSH authentication time
+ remote command execution
+ stdout/stderr draining
+ process termination overhead
```

The configured timeout is an upper bound, not a guaranteed completion time. A 15-second timeout can therefore produce approximately 15 seconds of event-loop starvation.

## 1.2 Visible synchronous operations

In the supplied range, synchronous command execution is used for at least:

| Endpoint / operation | Command type | Configured bound | Event-loop consequence |
|---|---:|---:|---|
| Google Intelligence local execution | PHP or local command | Not shown | Blocks for full command duration |
| Google Intelligence SSH fallback | `ssh` + `wp eval` | 15 s | Up to 15 seconds |
| Creator API check | `curl` | No explicit bound shown | Potentially unbounded |
| Swarm status | Python `--test-nodes` | Not shown | Blocks until Python exits |
| Swarm advanced metrics | `curl -m 2` | 2 s | Up to 2 seconds |
| Translation status, LM Studio probe | `curl -m 2` | 2 s | Up to 2 seconds |
| Translation status, WordPress bridge | SSH | 20 s | Up to 20 seconds |
| Translation stop | Python `--stop` | Not shown | Blocks until command exits |
| Other visible earlier calls | likely catalog/git/health commands | Depends on caller | Same blocking behavior |

The request refers to 11 `safeExec()` instances, but the complete surrounding file is not included here, so exact per-instance command enumeration and timing cannot be independently verified from this excerpt. The architectural result is unchanged: every one is a main-thread stop-the-world operation.

## 1.3 Example: an eight-second SSH call

Suppose request A invokes:

```js
safeExec(sshCommand, { timeout: 15000 });
```

and the SSH command takes eight seconds.

During those eight seconds:

- request A remains open;
- subsequent HTTP requests may be accepted by the kernel but cannot be dispatched by JavaScript;
- Express middleware and route handlers do not run;
- responses already generated but waiting for JavaScript-side writes are delayed;
- WebSocket `message`, `ping`, `pong`, and `close` handlers do not execute;
- `setTimeout`, `setInterval`, and heartbeat functions are delayed;
- child-process `stdout` and `stderr` callbacks are not processed;
- broadcast operations do not run;
- graceful shutdown logic is delayed.

The kernel may continue buffering TCP data, but that does not mean the application is responsive. Once socket buffers fill, clients experience stalled reads/writes and eventual timeout or connection failure.

## 1.4 HTTP queuing behavior

Node’s HTTP server and the operating system can continue accepting connections up to the listen backlog and socket limits. However, JavaScript dispatch is serialized on the event loop.

The result is:

```text
T_response =
  queue delay caused by prior synchronous work
+ route execution time
+ own synchronous work
```

With several concurrent requests invoking synchronous commands, latency becomes approximately the sum of command durations, not the maximum duration.

For example:

```text
5 concurrent requests × 8 seconds
≈ 40 seconds of serialized event-loop blockage
```

This is especially problematic for `/api/translation/status`, `/api/swarm/status`, health endpoints, and catalog endpoints because dashboards commonly poll them concurrently.

## 1.5 WebSocket ping/pong impact

If the WebSocket implementation relies on application-level heartbeats such as:

```js
setInterval(() => {
  wss.clients.forEach(client => client.ping());
}, 30000);
```

the interval callback cannot execute while `execSync()` is active.

Consequences include:

- delayed pings;
- delayed pong handling;
- false dead-client detection;
- missed heartbeat windows;
- connection termination by proxies or load balancers;
- accumulated socket buffers;
- apparent “random” WebSocket disconnects during SSH or Python operations.

Native TCP traffic is not stopped by JavaScript, but WebSocket protocol handling is.

## 1.6 `execSync` timeout behavior is insufficient

A timeout on `execSync` does not make the operation non-blocking. It only bounds how long the Node thread waits.

Additionally, killing the shell process does not reliably kill every descendant. For example:

```text
node
└── /bin/sh -c ssh ...
    └── ssh
        └── remote shell
            └── wp/php/tail
```

The shell can be terminated while descendants survive, particularly if they have detached, changed process groups, or are executing remotely.

## 1.7 Required remediation

Replace all synchronous command execution in request handlers with asynchronous APIs:

- `execFile()` or `execFile` promisified;
- `spawn()` with explicit argument arrays;
- `AbortController`-based cancellation;
- hard timeouts;
- bounded stdout/stderr;
- concurrency limits;
- cached background probes.

For example:

```js
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);

async function runProbe(signal) {
  const { stdout } = await execFileAsync(
    'curl',
    ['-sS', '-m', '2', 'http://127.0.0.1:1234/v1/models'],
    { timeout: 3000, maxBuffer: 1024 * 1024, signal }
  );
  return stdout;
}
```

Better still, use native Node HTTP clients rather than spawning `curl`, and use SSH libraries or a dedicated worker/service for remote operations.

---

# 2. Subprocess lifecycle and process-tree leaks

## 2.1 `metroProcess`

The excerpt does not show the `metroProcess` implementation, but the stated design indicates the standard risk:

```text
Node
└── Metro process
    ├── Node workers
    ├── Watchman
    ├── filesystem watchers
    └── bundler subprocesses
```

Calling:

```js
metroProcess.kill('SIGTERM');
```

only targets the Metro process PID. It does not guarantee termination of:

- child Node workers;
- Watchman;
- shell wrappers;
- process descendants spawned after startup;
- grandchildren that ignore SIGTERM;
- detached children.

This can cause:

- ports remaining bound;
- file watchers persisting;
- CPU usage after the UI reports stopped;
- duplicate Metro instances;
- stale build output;
- shutdown failures on restart.

## 2.2 `remoteTailProcess`

A remote-tail topology commonly looks like:

```text
Node
└── ssh
    └── remote shell
        └── tail -F /path/file
```

Killing the local SSH process may not terminate the remote `tail`. Depending on SSH behavior, the remote shell may remain alive after the client disconnects, especially when command wrapping, pseudo-terminals, or backgrounding are involved.

Required controls:

- use a unique remote job identifier;
- execute the remote tail in a known process group;
- explicitly terminate the remote command on disconnect;
- avoid `tail -F` over long-lived ad hoc SSH sessions where possible;
- use a remote log service or persistent agent;
- send a cleanup command in a `finally` path;
- ensure local and remote timeout behavior.

## 2.3 Swarm subprocesses

The batch and hybrid routes correctly use asynchronous `spawn()`, so they do not block the event loop. However, they have lifecycle gaps:

```js
const py = spawn('python3', args, { cwd: WEB_DIR });
```

Issues:

- no process registry;
- no job ID;
- no cancellation endpoint;
- no timeout;
- no maximum duration;
- no backpressure policy for stdout/stderr;
- no explicit detached/process-group configuration;
- no shutdown cleanup;
- no duplicate-job prevention;
- no handling of `error`;
- no `close` versus `exit` distinction;
- no persisted status after Node restarts.

If Python creates worker processes, terminating only `py` may leave the worker pool alive.

The Python implementation must also be inspected. `--workers 4` or `--workers 6` may create:

```text
Node
└── Python parent
    ├── worker 1
    ├── worker 2
    ├── worker 3
    └── worker 4
```

A direct `py.kill()` does not reliably terminate the entire tree.

## 2.4 `translationActiveProcess`

This variable is process-local and ephemeral:

```js
let translationActiveProcess = null;
```

It becomes `null` on process restart, while the Python process may continue running. The PID file is then used as the recovery mechanism, but that mechanism is not identity-safe.

There is also a race:

1. request A checks `getBotPid()` — no process;
2. request B checks `getBotPid()` — no process;
3. request A spawns Python;
4. request B spawns Python;
5. both processes run concurrently.

The in-memory variable does not prevent this because both requests can pass the check before either assignment is observable to the other request.

The single and batch start endpoints also do not share a durable job lock.

## 2.5 Stop endpoint failure modes

The stop route performs several independent actions:

```js
translationActiveProcess.kill('SIGTERM');
process.kill(runningPid, 'SIGTERM');
safeExec(`python3 ${METAL_BOT_SCRIPT} --stop`);
```

Problems:

- the first process may have already exited;
- the PID file can reference a different process;
- the Python bot may have descendants;
- `--stop` itself is synchronous;
- the route returns success before confirmed termination;
- there is no escalation to SIGKILL after a grace period;
- the PID file is not atomically removed after verified exit;
- `METAL_BOT_SCRIPT` is interpolated into a shell command;
- no process-group kill is performed.

A correct stop operation should return a state such as:

```json
{
  "status": "stopping",
  "jobId": "...",
  "pid": 1234
}
```

and complete termination asynchronously after verifying the complete process group has exited.

## 2.6 Process-group strategy

On Linux, launch each job in its own process group:

```js
const child = spawn('python3', args, {
  cwd: WEB_DIR,
  detached: true,
  stdio: ['ignore', 'pipe', 'pipe']
});
```

The child becomes the process-group leader. Terminate the group with:

```js
process.kill(-child.pid, 'SIGTERM');
```

Then escalate:

```js
setTimeout(() => {
  try { process.kill(-child.pid, 'SIGKILL'); } catch {}
}, 5000);
```

Important details:

- use a negative PID only for a verified process-group leader;
- never apply this to an unverified arbitrary PID;
- close stdin/stdout/stderr correctly;
- avoid `unref()` if the supervisor must retain ownership;
- configure Python workers to remain in the same group;
- use cgroups/systemd for stronger containment.

For production, systemd, supervisord, Docker, or a dedicated job runner is safer than application-level process management.

---

# 3. PID file races and stale-process hijacking

## 3.1 Problems in `getBotPid()`

Current logic:

```js
const pid = parseInt(fs.readFileSync(...), 10);
process.kill(pid, 0);
return pid;
```

This verifies only that:

- the file contains an integer;
- some process currently owns that PID;
- the current user has permission to perform the probe.

It does not verify:

- that the process is the Metal Translation Bot;
- that it is the same process that wrote the file;
- that it belongs to the expected process group;
- that it has the expected script path;
- that it has the expected command-line arguments;
- that its start time matches the recorded start time.

## 3.2 PID recycling attack/failure

Example:

1. Bot starts as PID 2401.
2. It crashes without removing `metal_bot.pid`.
3. Node restarts or the bot exits.
4. The OS later assigns PID 2401 to an unrelated process.
5. `getBotPid()` calls `process.kill(2401, 0)` successfully.
6. `/api/translation/bot/stop` sends SIGTERM to the unrelated process.

This can terminate an unrelated application or system service.

The risk is not theoretical on long-running systems with frequent process creation.

## 3.3 Atomic PID/supervisor design

Use a lock file and a metadata record, not a bare PID.

Example metadata:

```json
{
  "jobId": "8e0c...",
  "pid": 2401,
  "pgid": 2401,
  "uid": 1001,
  "script": "/var/www/.../metal_translation_bot.py",
  "lang": "de",
  "startedAt": "2026-03-10T12:00:00.000Z",
  "procStartTicks": "123456789",
  "version": 3
}
```

Required sequence:

1. Acquire an atomic lock using `open(path, 'wx')` or `flock`.
2. Spawn the process.
3. Record PID, process group, command identity, and process start time.
4. Write to a temporary file.
5. `fsync()` the file.
6. Rename it atomically into place.
7. On stop, reacquire the lock.
8. Verify `/proc/<pid>/cmdline`.
9. Verify `/proc/<pid>/stat` start ticks.
10. Verify process group membership.
11. Signal only the verified process group.
12. Wait for exit.
13. Remove the metadata atomically.

On modern Linux, `pidfd_open()` is preferable because it refers to the process instance rather than only its numeric PID. Node support varies by version, so a small native supervisor or systemd unit may be appropriate.

## 3.4 Better architecture

Use one of:

- systemd transient units;
- a dedicated supervisor daemon;
- BullMQ/Redis or another job queue;
- Docker/container per translation job;
- a single long-lived bot controlled through a Unix domain socket.

The HTTP API should communicate with the supervisor, not directly own arbitrary process trees.

---

# 4. WebSocket architecture, memory, backpressure, and isolation

## 4.1 `wss.clients.forEach()` characteristics

A broadcast loop such as:

```js
wss.clients.forEach(client => {
  if (client.readyState === WebSocket.OPEN) {
    client.send(payload);
  }
});
```

is O(number of connected clients) per log event.

If a process emits 1,000 lines/second and there are 100 clients, the server attempts approximately:

```text
100,000 client sends per second
```

before accounting for serialization, compression, copies, and kernel buffering.

This is not scalable as a raw per-line broadcast mechanism.

## 4.2 Slow-consumer behavior

WebSocket `send()` usually queues data internally rather than applying application-level backpressure. A slow client can therefore accumulate:

- `bufferedAmount`;
- Node heap usage;
- native WebSocket memory;
- kernel send-buffer pressure.

If the process emits faster than the client consumes, memory grows without a hard bound unless explicitly controlled.

Required policy:

```js
if (client.bufferedAmount > MAX_BUFFERED_BYTES) {
  client.terminate();
}
```

Additional improvements:

- batch logs into 50–250 ms frames;
- cap each frame size;
- drop low-priority stdout before system/error messages;
- maintain per-client queues;
- disconnect clients exceeding a queue limit;
- use a ring buffer for replay;
- use sequence numbers and reconnect cursors;
- avoid sending one WebSocket frame per stdout chunk.

## 4.3 `translationLogsBuffer` memory risk

The excerpt references:

```js
let logs = [...translationLogsBuffer];
```

but does not show how `translationLogsBuffer` is populated or bounded.

If it is an unbounded array, sustained translation output creates a permanent memory leak:

```text
memory growth =
  log rate × average entry size × process lifetime
```

At only 1,000 lines/sec and an average 300-byte entry:

```text
≈ 300 KB/sec
≈ 18 MB/minute
≈ 1.08 GB/hour
```

Actual usage will be higher due to JavaScript object overhead, strings, arrays, and WebSocket copies.

The buffer must be a fixed-size ring:

```js
const MAX_LOGS = 5000;

function appendLog(entry) {
  translationLogsBuffer.push(entry);
  if (translationLogsBuffer.length > MAX_LOGS) {
    translationLogsBuffer.splice(
      0,
      translationLogsBuffer.length - MAX_LOGS
    );
  }
}
```

A more efficient implementation uses a circular array rather than repeated `splice()`.

Also impose:

- maximum line length;
- maximum total bytes;
- normalization of multi-line chunks;
- redaction before buffering;
- TTL expiry for old entries.

## 4.4 Chunk fidelity

Using:

```js
d.toString()
```

on arbitrary stdout chunks does not preserve line boundaries. A UTF-8 character or line may be split across chunks. This causes:

- partial log records;
- malformed Unicode;
- incorrect error classification;
- concatenated lines;
- misleading UI timestamps.

Use a `StringDecoder` and a line accumulator per stream:

```js
const decoder = new StringDecoder('utf8');
let remainder = '';

child.stdout.on('data', chunk => {
  remainder += decoder.write(chunk);

  const lines = remainder.split(/\r?\n/);
  remainder = lines.pop();

  for (const line of lines) emitLog(line);
});

child.stdout.on('end', () => {
  remainder += decoder.end();
  if (remainder) emitLog(remainder);
});
```

## 4.5 Channel isolation

The channel argument is used like:

```js
broadcastLog(message, type, 'translation');
```

The security of this design depends entirely on the implementation of `broadcastLog()`. A common flawed implementation is:

```js
wss.clients.forEach(client => {
  if (!client.channel || client.channel === channel) {
    client.send(payload);
  }
});
```

That means clients that have not explicitly selected a channel receive all messages. It also risks leaking system messages into translation clients.

Potential leaks include:

- SSH commands;
- remote hostnames;
- credentials accidentally printed by subprocesses;
- filesystem paths;
- audit results;
- internal error details;
- swarm output;
- server operational state.

Channel membership must be explicit and deny-by-default:

```js
if (!client.subscriptions?.has(channel)) return;
```

Recommended model:

```text
system
translation
swarm
audit
public
```

Every message should contain:

```json
{
  "id": 12345,
  "channel": "translation",
  "severity": "stdout",
  "timestamp": "...",
  "text": "..."
}
```

Do not infer authorization from a client-supplied channel string. Authenticate the WebSocket and authorize channel subscriptions server-side.

Sensitive logs should be redacted before publication. Ideally, command lines and raw stderr should never be sent to browser clients.

## 4.6 WebSocket lifecycle cleanup

The server must remove all per-client state on:

- `close`;
- `error`;
- heartbeat timeout;
- authentication failure;
- subscription replacement.

Otherwise, subscription maps, queues, timers, and replay cursors can retain closed socket objects.

---

# 5. Data-layer and filesystem I/O bottlenecks

## 5.1 Synchronous directory scans

Operations of this form are expensive on every request:

```js
fs.existsSync(dir);
fs.readdirSync(dir);
readJson(path);
```

For 5,415 helmets, 3,249 motorcycles, accessories, and brands, a single request can involve:

- directory metadata lookup;
- directory enumeration;
- thousands of file opens;
- thousands of reads;
- thousands of JSON parses;
- garbage creation;
- repeated stat/cache work.

If `/api/catalog/summary` scans approximately 8,000 files and `/api/catalog/health/audit` performs deeper validation, concurrent polling can saturate:

- the event loop;
- filesystem metadata operations;
- page cache;
- disk I/O;
- V8 garbage collection.

Because the operations are synchronous, the cost is serialized within the Node process.

## 5.2 Approximate concurrency effect

If one full scan takes 500 ms:

```text
10 concurrent requests
≈ up to 5 seconds of serialized blocking
```

If the audit takes 3 seconds:

```text
10 concurrent audits
≈ up to 30 seconds of event-loop occupancy
```

These are illustrative values; actual duration must be measured on the production filesystem. The important point is that throughput is bounded by one event loop and synchronous file operations.

## 5.3 Repeated parsing and allocation

Each request recreates:

- filename arrays;
- JSON object graphs;
- derived counters;
- temporary strings;
- error objects.

This increases young-generation garbage collection and can trigger major GC pauses when large object graphs survive across callbacks or are copied into response objects.

## 5.4 Required remediation

### Short term

- convert route handlers to `fs.promises`;
- limit concurrent file reads with a semaphore;
- cache catalog summaries;
- invalidate cache on ingestion/update;
- cache parsed objects using mtime or content hash;
- avoid `existsSync()` followed by another operation;
- return stale cache while a refresh runs;
- prevent multiple simultaneous refreshes with a single-flight promise.

Example:

```js
let summaryPromise = null;
let summaryCache = null;

async function getSummary() {
  if (summaryCache && Date.now() - summaryCache.ts < 30_000) {
    return summaryCache.data;
  }

  if (!summaryPromise) {
    summaryPromise = rebuildSummary()
      .then(data => {
        summaryCache = { ts: Date.now(), data };
        return data;
      })
      .finally(() => {
        summaryPromise = null;
      });
  }

  return summaryPromise;
}
```

### Medium term

Move the catalog to a database or indexed manifest:

```text
helmet_id
brand
type
price
has_affiliate_links
has_asin
has_price
updated_at
content_hash
```

Then summary queries become indexed database aggregations rather than filesystem scans.

### Long term

Use an ingestion/indexing pipeline:

1. file changes are detected;
2. changed records are parsed once;
3. a manifest or database is updated;
4. API requests read the precomputed result.

The API should never need to inspect thousands of files to answer a dashboard summary request.

---

# 6. Additional lifecycle and correctness issues

## 6.1 Missing child `error` handlers

Every spawned child should handle:

```js
child.on('error', err => {
  // spawn failure, executable missing, permission failure
});
```

Without this, spawn failures can produce unhandled error behavior or leave the API reporting a job as started when it never launched.

## 6.2 No graceful shutdown ownership

On `SIGTERM`/`SIGINT`, the server should:

1. stop accepting new HTTP connections;
2. stop WebSocket broadcasts;
3. terminate tracked process groups;
4. wait for children with a deadline;
5. close WebSockets;
6. close the HTTP server;
7. remove only verified owned state files;
8. exit.

A process registry should contain every spawned job:

```js
const jobs = new Map();
```

The current in-memory variables do not cover all swarm, audit, Metro, tail, and translation subprocesses.

## 6.3 Shell interpolation

Commands such as:

```js
safeExec(`python3 ${METAL_BOT_SCRIPT} --stop`, ...)
```

and shell-constructed SSH commands are fragile. Even fixed constants should be executed with argument arrays. Any future user-controlled path, ID, language, model, or query can become command injection if interpolated into a shell string.

Use:

```js
spawn('python3', [METAL_BOT_SCRIPT, '--stop'], options);
```

and validate all user inputs with strict allowlists.

## 6.4 Exposed secrets

`/api/vault` returns:

- SSH credentials;
- Cloudflare token;
- Amazon Creator API secret;
- infrastructure details.

This is a critical vulnerability independent of the concurrency audit. Immediate actions:

1. rotate every exposed secret;
2. remove them from source and responses;
3. audit access logs and repository history;
4. store secrets in environment variables or a secret manager;
5. require authenticated, authorized access;
6. never return raw secret values to the browser.

---

# Priority remediation plan

## P0 — immediate

- Rotate all credentials exposed through `/api/vault`.
- Remove the endpoint or require strong authorization.
- Eliminate synchronous SSH/curl/Python execution from HTTP handlers.
- Prevent arbitrary PID termination by verifying process identity.
- Add process-group ownership and cleanup.
- Bound `translationLogsBuffer`.
- Add WebSocket authentication and deny-by-default channel subscriptions.

## P1 — next implementation cycle

- Introduce a centralized subprocess supervisor.
- Add job IDs, process groups, cancellation, timeouts, and persisted state.
- Replace shell strings with `spawn`/`execFile` argument arrays.
- Add WebSocket backpressure and slow-client termination.
- Batch log frames and add sequence numbers.
- Add a fixed-size catalog cache and single-flight refresh.

## P2 — architectural

- Move translation and swarm jobs to systemd, containers, or a job queue.
- Replace filesystem-as-database catalog queries with an indexed manifest/database.
- Add metrics:
  - event-loop delay;
  - command duration;
  - child count;
  - process-tree size;
  - WebSocket `bufferedAmount`;
  - dropped log count;
  - catalog scan duration;
  - heap and RSS;
  - active job count.
- Load-test with concurrent dashboard polling and sustained 1,000-line/second subprocess output.

## Final risk rating

| Area | Rating |
|---|---|
| Synchronous event-loop blocking | Critical |
| Process-tree containment | High/Critical |
| PID-file safety | Critical |
| WebSocket backpressure | High |
| WebSocket channel isolation | High/Critical if unauthenticated |
| Translation log memory safety | High unless explicitly capped |
| Filesystem catalog scans | High under concurrent load |
| Credential exposure | Critical |

The application can appear functional in low-concurrency manual testing, but its current execution model is not safe for sustained dashboard traffic, long-running subprocesses, or production-scale streaming.

---

# PART III: FRONTEND DOM AUDIT & DEFINITIVE PRODUCTION-GRADE REFACTORED CODEBASE

## Phase 3 findings

A complete, safe drop-in `server.js` cannot be produced from the supplied material because:

- `index.html` is truncated.
- `app.js` is truncated and the omitted portion contains endpoint contracts and renderers.
- Existing server routes, database schema, process commands, authentication flow, and WebSocket protocol are not provided.
- Replacing `server.js` without those contracts would either break the application or silently remove functionality.

The following findings and refactoring requirements are definitive for the supplied code. A complete replacement server requires the full current `server.js`, complete `app.js`, and the SQLite schema.

---

# 1. DOM/XSS audit

## 1.1 `addTerminalLine`

Current code:

```js
const html = `<div class="tl ${cls}" data-text="${escapeHtml(String(text).toLowerCase())}">
  ${escapeHtml(text)}
</div>`;

el.innerHTML += html;
```

### Assessment

`escapeHtml()` does encode HTML-significant characters, including:

- `&`
- `<`
- `>`
- `"`
- `'`

Therefore, quotes in `text` do not independently break the `data-text` attribute.

ANSI/control sequences also do not become executable HTML. They remain text.

However, this is still an unsafe and unnecessary HTML sink because:

1. `innerHTML +=` reparses the entire existing terminal.
2. The implementation depends on every future caller remembering to call `escapeHtml`.
3. The `cls` value is interpolated into a class attribute.
4. Terminal output may contain browser-sensitive Unicode or control characters.
5. Repeated concatenation is inefficient and can cause large DOM reparsing.
6. Any future change that inserts an unescaped field into the template becomes a direct stored/reflected XSS vulnerability.

Use DOM construction instead:

```js
function addTerminalLine(text, type = 'output', channel = 'all') {
  const value = String(text ?? '');
  const className =
    type === 'error' || type === 'stderr' ? 'tl-err' :
    type === 'system' ? 'tl-sys' :
    type === 'success' ? 'tl-ok' :
    'tl-out';

  const append = (container) => {
    if (!container) return;

    const row = document.createElement('div');
    row.className = `tl ${className}`;
    row.dataset.text = value.toLocaleLowerCase();
    row.textContent = value;

    container.appendChild(row);

    // Prevent unbounded client-side memory growth.
    while (container.childElementCount > 2000) {
      container.firstElementChild?.remove();
    }

    container.scrollTop = container.scrollHeight;
  };

  append(document.getElementById('terminal'));

  if (channel === 'translation' || channel === 'all' || !channel) {
    const translationTerminal = document.getElementById('terminal-trans');
    append(translationTerminal);

    const autoScroll = document.getElementById('trans-auto-scroll');
    if (translationTerminal && (!autoScroll || autoScroll.checked)) {
      translationTerminal.scrollTop = translationTerminal.scrollHeight;
    }
  }
}
```

This removes the `innerHTML` sink entirely.

---

## 1.2 Direct `innerHTML` assignments

The following supplied renderers use untrusted or partially trusted values inside HTML templates:

### High-risk instances

#### `renderDashGoogle`

Unsafe values include:

```js
countries
anomalies.anomalies[].description
anomalies.anomalies[].metric
ga.active_users
ga.sessions
ga.page_views
ga.avg_session_duration
gsc.impressions
gsc.position
gsc.clicks
gsc.ctr
queries[].query
queries[].clicks
queries[].impressions
queries[].position
sitemaps[].path
sitemaps[].last_downloaded
pages[].path
pages[].clicks
pages[].impressions
appearance[].appearance
appearance[].clicks
appearance[].impressions
appearance[].position
```

Examples:

```js
<strong>${q.query}</strong>
```

```js
<li>${a.description || a.metric}</li>
```

```js
<code>${p.path}</code>
```

These are direct XSS sinks if the API, Google response, cache, or compromised upstream data contains HTML.

The `style` value is also interpolated:

```js
style="width:${desktopShare}%"
```

If `share` is not guaranteed numeric, this can become CSS injection or malformed markup. Validate and clamp it before use.

---

#### `renderDashStats`

These values are inserted without escaping:

```js
s.web.branch
s.web.gitStatus
s.mobile.dbSizeMB
s.mobile.dbModified
s.server.ip
s.server.domain
```

For example:

```js
<div class="stat-num">${s.server.ip}</div>
```

These must be inserted with `textContent`.

---

#### `renderDashHealth`

The `label` and `color` arguments are currently internal constants, but `pct` is inserted into a style attribute:

```js
style="width:${pct}%"
```

Validate:

```js
function safePercent(value) {
  const number = Number(value);
  if (!Number.isFinite(number)) return 0;
  return Math.max(0, Math.min(100, number));
}
```

---

#### `renderEntityTabs`

Current code:

```js
el.innerHTML += `
  <button ... onclick="setEntity('${key}')">
    ${cfg.icon} ${cfg.label}
  </button>`;
```

Problems:

- Inline event handler.
- `key` is placed inside JavaScript source.
- Repeated `innerHTML +=`.
- Future configuration changes could produce JavaScript injection.
- `catalogSummary[key]` is assumed numeric.

Use:

```js
function renderEntityTabs() {
  const container = document.getElementById('entity-tabs');
  if (!container) return;

  container.replaceChildren();

  for (const [key, cfg] of Object.entries(ENTITY_CONFIG)) {
    const count = Number(catalogSummary[key]) || 0;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = `entity-tab${key === currentEntity ? ' active' : ''}`;
    button.append(
      document.createTextNode(`${cfg.icon} ${cfg.label} `)
    );

    const countNode = document.createElement('span');
    countNode.className = 'text-muted';
    countNode.textContent = `(${count.toLocaleString()})`;

    button.appendChild(countNode);
    button.addEventListener('click', () => setEntity(key));
    container.appendChild(button);
  }
}
```

---

#### `renderCatalogTable`

Current code:

```js
<tr onclick="inspectItem('${data.entity}', '${r.id}')">
```

This is a critical vulnerability.

An attacker-controlled ID containing a quote can break out of the JavaScript string and execute arbitrary code. Even if IDs are currently numeric, this must not be trusted at the DOM boundary.

Other issues:

```js
<td>${col4Display}</td>
```

`col4Display` is not escaped when `r.price` is a string.

```js
<span class="badge ${auditBadge}">
```

`auditBadge` is inserted into a class attribute without allowlisting.

```js
${scoreStr}
```

`audit_score` is not validated.

Safe implementation:

```js
const ALLOWED_BADGES = new Set([
  'badge-gray',
  'badge-blue',
  'badge-green',
  'badge-amber',
  'badge-red',
  'badge-purple'
]);

function safeBadgeClass(value) {
  return ALLOWED_BADGES.has(value) ? value : 'badge-gray';
}

function formatPrice(price) {
  if (typeof price === 'string') return price.slice(0, 100);

  if (price && typeof price === 'object') {
    if (Number.isFinite(Number(price.usd))) {
      return `$${Number(price.usd).toLocaleString()}`;
    }

    if (Number.isFinite(Number(price.inr))) {
      return `₹${Number(price.inr).toLocaleString()}`;
    }
  }

  return '—';
}

function appendTextCell(row, value, className = '') {
  const cell = document.createElement('td');
  if (className) cell.className = className;
  cell.textContent = String(value ?? '—');
  row.appendChild(cell);
  return cell;
}

function appendBadgeCell(row, value, badgeClass = 'badge-gray', title = '') {
  const cell = document.createElement('td');
  cell.className = 'text-center';

  const badge = document.createElement('span');
  badge.className = `badge ${safeBadgeClass(badgeClass)}`;
  badge.textContent = String(value ?? 'Unchecked');

  if (title) badge.title = String(title).slice(0, 500);

  cell.appendChild(badge);
  row.appendChild(cell);
}

function renderCatalogTable(data) {
  const thead = document.getElementById('catalog-thead');
  const tbody = document.getElementById('catalog-tbody');
  if (!thead || !tbody) return;

  const headers =
    ENTITY_TABLE_HEADERS[data.entity] ||
    ENTITY_TABLE_HEADERS.helmets;

  thead.replaceChildren();

  const headerRow = document.createElement('tr');
  headers.forEach((header, index) => {
    const th = document.createElement('th');
    th.textContent = header;

    if (index === 3) th.className = 'text-right';
    if (index === 4) th.className = 'text-center';

    headerRow.appendChild(th);
  });

  thead.appendChild(headerRow);
  tbody.replaceChildren();

  const results = Array.isArray(data.results) ? data.results : [];

  if (!results.length) {
    const row = document.createElement('tr');
    const cell = document.createElement('td');
    cell.colSpan = 6;
    cell.className = 'text-center text-muted';
    cell.style.padding = '40px';
    cell.textContent = 'No results found';
    row.appendChild(cell);
    tbody.appendChild(row);
    return;
  }

  for (const item of results) {
    const row = document.createElement('tr');
    row.tabIndex = 0;
    row.style.cursor = 'pointer';

    const entity = String(data.entity);
    const id = String(item.id);

    const inspect = () => inspectItem(entity, id);
    row.addEventListener('click', inspect);
    row.addEventListener('keydown', event => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        inspect();
      }
    });

    const titleCell = document.createElement('td');
    const title = document.createElement('b');
    title.textContent = String(item.title ?? '—');
    titleCell.appendChild(title);
    row.appendChild(titleCell);

    appendTextCell(row, item.brand || '—');

    const typeCell = document.createElement('td');
    const typeBadge = document.createElement('span');
    typeBadge.className = 'badge badge-blue';
    typeBadge.textContent = String(item.type || item.category || '—');
    typeCell.appendChild(typeBadge);
    row.appendChild(typeCell);

    appendTextCell(row, formatPrice(item.price), 'text-right text-mono');

    const score = Number(item.audit_score);
    const scoreText = Number.isFinite(score)
      ? ` (${Math.max(0, Math.min(100, score))}%)`
      : '';

    appendBadgeCell(
      row,
      `${item.audit_status || 'Unchecked'}${scoreText}`,
      item.audit_badge,
      item.audit_detail
    );

    const dateCell = document.createElement('td');
    dateCell.className = 'text-sm text-muted';

    const date = item.updated_at ? new Date(item.updated_at) : null;
    dateCell.textContent =
      date && !Number.isNaN(date.valueOf())
        ? date.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric'
          })
        : '—';

    row.appendChild(dateCell);
    tbody.appendChild(row);
  }
}
```

---

## 1.3 `renderInspector`

The supplied portion mostly uses `escapeHtml`, but it still has important problems:

- Dynamic badge classes such as `a.badge` are not allowlisted.
- Numeric fields are not validated.
- Dates should be generated by JavaScript, not interpolated into HTML.
- The omitted portion must be audited separately.
- Any URLs, image sources, links, or attributes in the omitted portion require protocol allowlisting.

Never use:

```js
<a href="${item.url}">
<img src="${item.image}">
```

without URL validation. Only permit `https:` and, if required, same-origin relative URLs.

---

## 1.4 Query console output

The omitted source must be audited, but SQL result rendering is a high-risk area.

Unsafe:

```js
results.innerHTML = rows.map(row => `
  <tr><td>${row.name}</td></tr>
`).join('');
```

Safe approach:

```js
function renderSqlResults(container, rows) {
  container.replaceChildren();

  if (!Array.isArray(rows) || rows.length === 0) {
    const empty = document.createElement('p');
    empty.className = 'text-muted text-sm';
    empty.textContent = 'No rows returned.';
    container.appendChild(empty);
    return;
  }

  const table = document.createElement('table');
  const header = document.createElement('thead');
  const headerRow = document.createElement('tr');

  const columns = Object.keys(rows[0]);
  for (const column of columns) {
    const th = document.createElement('th');
    th.textContent = column;
    headerRow.appendChild(th);
  }

  header.appendChild(headerRow);
  table.appendChild(header);

  const body = document.createElement('tbody');

  for (const rowData of rows) {
    const row = document.createElement('tr');

    for (const column of columns) {
      const cell = document.createElement('td');
      const value = rowData[column];

      cell.textContent =
        value === null || value === undefined
          ? ''
          : typeof value === 'object'
            ? JSON.stringify(value)
            : String(value);

      row.appendChild(cell);
    }

    body.appendChild(row);
  }

  table.appendChild(body);
  container.appendChild(table);
}
```

Never render SQL result values as HTML.

---

## 1.5 AI comparison table

The omitted renderer must not use template interpolation for:

- Product names
- Brand names
- AI-generated descriptions
- Pros and cons
- Scores
- Recommendation text
- URLs
- Markdown or HTML returned by an LLM

AI output must be treated as hostile input. Render it as plain text unless a separately reviewed Markdown renderer is used with strict sanitization and no unsafe URL schemes.

Do not use `innerHTML` for AI output.

---

# 2. Inline event handlers

The HTML contains many handlers such as:

```html
onclick="switchTab('translation')"
onclick="runBidirectionalityAudit()"
onclick="fetchGoogleIntelligence(true)"
onclick="deployWeb()"
```

Inline handlers should be removed. They:

- Expand the JavaScript injection surface.
- Complicate CSP deployment.
- Prevent a strict `script-src` policy without `'unsafe-inline'`.
- Make event authorization and validation harder.

Use delegated event handling:

```js
document.addEventListener('click', event => {
  const button = event.target.closest('[data-action]');
  if (!button) return;

  switch (button.dataset.action) {
    case 'switch-tab':
      if (ALLOWED_TABS.has(button.dataset.tab)) {
        switchTab(button.dataset.tab);
      }
      break;

    case 'run-seo-audit':
      runSeoAudit();
      break;
  }
});
```

Example HTML:

```html
<button
  type="button"
  class="btn btn-outline"
  data-action="switch-tab"
  data-tab="translation">
  🌍 Translation & Metal Bot
</button>
```

---

# 3. WebSocket security and reconnect behavior

Current code:

```js
ws = new WebSocket(`ws://${location.host}/ws`);

ws.onclose = () => setTimeout(connectWS, 2000);
```

## Problems

1. `ws://` allows an active network attacker to intercept terminal data when the page is served over HTTPS.
2. Native browser `WebSocket` does not allow arbitrary `Authorization` headers.
3. A bearer token placed in the WebSocket URL leaks through:
   - Browser history
   - Reverse-proxy logs
   - Access logs
   - Monitoring systems
   - Referrer-like operational telemetry
4. Fixed reconnect timing can cause a reconnect storm across many clients.
5. There is no maximum retry delay.
6. There is no connection generation guard.
7. There is no backpressure handling.
8. There is no authentication failure handling.
9. There is no visibility/network awareness.

## Correct browser authentication design

For a browser dashboard, use:

- An authenticated HTTPS login or reverse proxy.
- A `Secure`, `HttpOnly`, `SameSite=Strict` session cookie.
- The WebSocket upgrade authenticates using that cookie.
- The WebSocket endpoint validates the `Origin`.
- Do not expose a long-lived bearer token to JavaScript.

A browser cannot securely implement the requested:

```js
new WebSocket(url, {
  headers: {
    Authorization: `Bearer ${token}`
  }
});
```

That API does not exist.

A subprotocol token is technically possible:

```js
new WebSocket(url, [`bearer.${token}`]);
```

but is not recommended because it can still be logged by intermediaries and exposes the bearer credential to JavaScript.

## Hardened client reconnect implementation

This assumes authentication is provided by an HttpOnly cookie:

```js
let ws = null;
let wsRetryTimer = null;
let wsRetryDelay = 1000;
let wsGeneration = 0;
let wsClosedByUser = false;

const WS_MAX_RETRY_DELAY = 30_000;

function websocketUrl() {
  const protocol = location.protocol === 'https:' ? 'wss:' : 'ws:';
  return `${protocol}//${location.host}/ws`;
}

function scheduleWebSocketReconnect() {
  if (wsClosedByUser || wsRetryTimer) return;

  const jitter = Math.floor(Math.random() * 500);
  const delay = Math.min(WS_MAX_RETRY_DELAY, wsRetryDelay) + jitter;

  wsRetryTimer = setTimeout(() => {
    wsRetryTimer = null;
    connectWS();
  }, delay);

  wsRetryDelay = Math.min(WS_MAX_RETRY_DELAY, wsRetryDelay * 2);
}

function connectWS() {
  const generation = ++wsGeneration;

  if (ws) {
    try {
      ws.close(1000, 'reconnecting');
    } catch {}
  }

  wsClosedByUser = false;

  const socket = new WebSocket(websocketUrl());
  ws = socket;

  socket.onopen = () => {
    if (generation !== wsGeneration) {
      socket.close(1000, 'stale');
      return;
    }

    wsRetryDelay = 1000;
    addTerminalLine('Connected to live terminal.', 'system');
  };

  socket.onmessage = event => {
    if (generation !== wsGeneration) return;

    if (typeof event.data !== 'string') return;
    if (event.data.length > 256 * 1024) return;

    try {
      const message = JSON.parse(event.data);

      if (!message || typeof message !== 'object') return;

      const text =
        typeof message.text === 'string'
          ? message.text.slice(0, 16_384)
          : '';

      const type =
        typeof message.type === 'string'
          ? message.type
          : 'output';

      const channel =
        typeof message.channel === 'string'
          ? message.channel
          : 'all';

      addTerminalLine(text, type, channel);
    } catch {
      addTerminalLine('Received invalid terminal message.', 'error');
    }
  };

  socket.onerror = () => {
    // Avoid exposing low-level connection details to the terminal.
    addTerminalLine('Live terminal connection error.', 'error');
  };

  socket.onclose = event => {
    if (generation !== wsGeneration) return;

    if (event.code === 1008 || event.code === 1003) {
      addTerminalLine('Live terminal authorization failed.', 'error');
      wsClosedByUser = true;
      return;
    }

    scheduleWebSocketReconnect();
  };
}

function disconnectWS() {
  wsClosedByUser = true;
  wsGeneration++;

  if (wsRetryTimer) {
    clearTimeout(wsRetryTimer);
    wsRetryTimer = null;
  }

  try {
    ws?.close(1000, 'client shutdown');
  } catch {}

  ws = null;
}

document.addEventListener('visibilitychange', () => {
  if (document.visibilityState === 'visible' && !ws) {
    connectWS();
  }
});
```

---

# 4. Client API helper

The client must not place a static bearer token in JavaScript. Use the authenticated session cookie:

```js
async function api(path, method = 'GET', body, options = {}) {
  const controller = new AbortController();
  const timeout = setTimeout(
    () => controller.abort(),
    options.timeoutMs || 30_000
  );

  try {
    const response = await fetch(path, {
      method,
      credentials: 'same-origin',
      headers: {
        Accept: 'application/json',
        ...(body === undefined
          ? {}
          : { 'Content-Type': 'application/json' })
      },
      body: body === undefined ? undefined : JSON.stringify(body),
      signal: controller.signal,
      cache: 'no-store'
    });

    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json')
      ? await response.json()
      : await response.text();

    if (!response.ok) {
      const message =
        payload && typeof payload === 'object' && payload.error
          ? payload.error
          : `Request failed with HTTP ${response.status}`;

      throw new Error(message);
    }

    return payload;
  } finally {
    clearTimeout(timeout);
  }
}
```

If bearer authentication is absolutely required for ordinary HTTP API requests, use a short-lived access token acquired through a secure login flow. Do not hard-code it into `index.html`, `app.js`, local storage, or session storage.

---

# 5. Required server-side controls

The replacement server must implement all of the following.

## Authentication

- Authenticate every `/api/*` request.
- Reject missing or malformed `Authorization`.
- Use a fixed expected token from `process.env`.
- Compare tokens with `crypto.timingSafeEqual`.
- Normalize lengths before comparison.
- Never reveal whether the token, route, or account was invalid.
- Do not log authorization headers.
- Prefer HttpOnly session cookies for browser WebSockets.

Example timing-safe comparison:

```js
import crypto from 'node:crypto';

function safeTokenEquals(received, expected) {
  if (typeof received !== 'string' || typeof expected !== 'string') {
    return false;
  }

  const receivedBuffer = Buffer.from(received, 'utf8');
  const expectedBuffer = Buffer.from(expected, 'utf8');

  if (receivedBuffer.length !== expectedBuffer.length) {
    return false;
  }

  return crypto.timingSafeEqual(receivedBuffer, expectedBuffer);
}
```

## Binding

```js
server.listen(PORT, '127.0.0.1');
```

Do not bind to:

```js
0.0.0.0
::
```

unless an authenticated, hardened reverse proxy is intentionally deployed in front of it.

## CORS

For a same-origin dashboard, disable CORS entirely where possible.

If CORS is needed:

```js
const ALLOWED_ORIGINS = new Set([
  'http://localhost:3005',
  'https://localhost:3005'
]);

function validateOrigin(origin) {
  return typeof origin === 'string' &&
    ALLOWED_ORIGINS.has(origin);
}
```

Never use:

```js
Access-Control-Allow-Origin: *
Access-Control-Allow-Credentials: true
```

together.

## Process execution

Never use:

```js
exec(`ssh ${host} ${command}`)
exec(commandString)
spawn(commandString, { shell: true })
```

Use explicit executable and argument arrays:

```js
import { execFile } from 'node:child_process';
import { promisify } from 'node:util';

const execFileAsync = promisify(execFile);

async function safeExecFile(file, args, options = {}) {
  if (!Array.isArray(args) || args.some(arg => typeof arg !== 'string')) {
    throw new TypeError('Invalid process arguments');
  }

  return execFileAsync(file, args, {
    shell: false,
    windowsHide: true,
    timeout: 120_000,
    maxBuffer: 2 * 1024 * 1024,
    ...options
  });
}
```

Example:

```js
await safeExecFile('ssh', [
  '-o', 'BatchMode=yes',
  '-o', 'StrictHostKeyChecking=yes',
  '-o', 'ConnectTimeout=10',
  'deploy@trusted-host',
  '--',
  '/usr/local/bin/deploy',
  '--mode',
  deployMode
]);
```

The host, username, executable path, and permitted arguments must come from strict allowlists. Password-based SSH deployment should be removed; use a restricted SSH key or deployment agent.

## Entity and ID validation

```js
const ENTITY_ALLOWLIST = new Set([
  'helmets',
  'motorcycles',
  'accessories',
  'brands',
  'dealers',
  'distributors',
  'safety-standards',
  'helmet-types'
]);

function validateEntity(value) {
  if (typeof value !== 'string' || !ENTITY_ALLOWLIST.has(value)) {
    throw new Error('Invalid catalog entity');
  }

  return value;
}

function validateId(value) {
  const id = String(value);

  if (!/^[1-9][0-9]{0,18}$/.test(id)) {
    throw new Error('Invalid catalog ID');
  }

  return id;
}
```

Do not construct file paths from raw URL parameters. If filesystem access is required:

```js
import path from 'node:path';

function safePathWithin(root, relativePath) {
  const rootPath = path.resolve(root);
  const targetPath = path.resolve(rootPath, relativePath);

  if (
    targetPath !== rootPath &&
    !targetPath.startsWith(`${rootPath}${path.sep}`)
  ) {
    throw new Error('Path traversal rejected');
  }

  return targetPath;
}
```

## SQLite

A production endpoint must not accept arbitrary SQL from the browser. The custom SQL console is a privileged remote-code/data-exfiltration interface.

Recommended policy:

- Remove custom SQL entirely.
- Expose named, parameterized queries only.
- Permit a read-only SQLite connection.
- Reject all write and schema statements.
- Enforce result and execution limits.
- Do not rely only on a regex.

At minimum, reject:

```text
ATTACH
DETACH
PRAGMA
VACUUM
REINDEX
CREATE
ALTER
DROP
INSERT
UPDATE
DELETE
REPLACE
TRIGGER
.load
.read
```

A safer design is:

```js
const QUERY_PRESETS = Object.freeze({
  tables: {
    sql: `
      SELECT name
      FROM sqlite_master
      WHERE type = ?
      ORDER BY name
      LIMIT ?
    `,
    params: ['table', 500]
  },

  helmetCount: {
    sql: `SELECT COUNT(*) AS count FROM helmets`,
    params: []
  }
});
```

The browser sends only:

```json
{ "preset": "helmetCount" }
```

not SQL text.

## Vault

Remove all plaintext vault routes.

Replace with non-sensitive configuration status:

```json
{
  "ok": true,
  "configured": {
    "amazon": true,
    "cloudflare": true,
    "google": true,
    "ssh": true
  }
}
```

Never return:

- Passwords
- API keys
- Bearer tokens
- Private keys
- SSH credentials
- Environment variable values
- Secret lengths or partial prefixes

Use:

```js
function secretConfigured(name) {
  return typeof process.env[name] === 'string' &&
    process.env[name].length > 0;
}
```

## Process-tree termination

Child processes must be tracked by PID and terminated as process groups.

On Unix:

```js
function terminateProcessTree(child, signal = 'SIGTERM') {
  if (!child?.pid) return;

  try {
    process.kill(-child.pid, signal);
  } catch (error) {
    if (error.code !== 'ESRCH') throw error;
  }
}
```

Processes must be spawned detached:

```js
const child = spawn(file, args, {
  shell: false,
  detached: true,
  stdio: ['ignore', 'pipe', 'pipe']
});
```

Implement escalation:

1. `SIGTERM`
2. Wait for a bounded grace period
3. `SIGKILL`

This is required for Metro, translation bots, Python workers, SSH jobs, and remote ingest processes.

---

# 6. Security headers

Add a strict policy suitable for a same-origin application:

```http
Content-Security-Policy:
  default-src 'self';
  script-src 'self';
  style-src 'self' 'unsafe-inline';
  img-src 'self' data:;
  connect-src 'self' ws: wss:;
  font-src 'self';
  object-src 'none';
  base-uri 'none';
  frame-ancestors 'none';
  form-action 'self';
  upgrade-insecure-requests;

X-Content-Type-Options: nosniff
Referrer-Policy: no-referrer
X-Frame-Options: DENY
Permissions-Policy: camera=(), microphone=(), geolocation=()
Cache-Control: no-store
```

Move inline styles to CSS when practical so that `style-src 'unsafe-inline'` can also be removed.

---

# 7. `index.html` changes

At minimum:

1. Remove all inline `onclick` and `onchange` attributes.
2. Add explicit button types:

```html
<button type="button" ...>
```

3. Add a CSP meta tag only as a temporary fallback. Prefer HTTP response headers:

```html
<meta
  http-equiv="Content-Security-Policy"
  content="default-src 'self'; script-src 'self'; object-src 'none'; base-uri 'none'; frame-ancestors 'none'">
```

4. Do not place bearer tokens, API keys, passwords, or configuration secrets in HTML.
5. Add `autocomplete="off"` or appropriate password-manager semantics to operational secret fields. Better: remove password collection from the browser and use an SSH key/deployment agent.

---

# 8. Final severity summary

| Area | Severity |
|---|---:|
| Inline `onclick` catalog ID interpolation | Critical |
| Google dashboard unescaped API values | High |
| AI-generated content rendering | High |
| SQL result rendering, depending on omitted code | High |
| Dynamic CSS/class interpolation | Medium/High |
| `innerHTML +=` terminal rendering | Medium |
| Plaintext vault disclosure | Critical |
| Shell-string process execution | Critical |
| Missing API authentication | Critical |
| WebSocket unauthenticated upgrade | Critical |
| Fixed reconnect loop | Medium |
| `ws://` on secure pages | High |
| Arbitrary SQL execution | Critical |
| Broad filesystem/entity parameters | High |

The most important architectural correction is that browser JavaScript must not hold a reusable bearer secret. Use an authenticated, same-origin, `HttpOnly; Secure; SameSite=Strict` session cookie for both HTTP and the WebSocket upgrade, then perform all DOM rendering with `textContent`, DOM node construction, and strict allowlists.

