Below is a modular ES-module implementation suitable for an Express-based Mission Control V2 service. The examples assume Node.js 20+, Express, and `ws`.

## 1. Catalog Pipeline

### `server/catalog/CatalogIndex.js`

```js
import fs from 'node:fs/promises';
import path from 'node:path';

const DEFAULT_TTL_MS = 5 * 60 * 1000;

export class CatalogIndex {
  #catalogRoot;
  #ttlMs;
  #snapshot = null;
  #refreshPromise = null;

  constructor({ catalogRoot, ttlMs = DEFAULT_TTL_MS }) {
    this.#catalogRoot = path.resolve(catalogRoot);
    this.#ttlMs = ttlMs;
  }

  async start() {
    await this.refresh();
  }

  async refresh() {
    if (this.#refreshPromise) return this.#refreshPromise;

    this.#refreshPromise = this.#buildSnapshot()
      .then((snapshot) => {
        this.#snapshot = snapshot;
        return snapshot;
      })
      .finally(() => {
        this.#refreshPromise = null;
      });

    return this.#refreshPromise;
  }

  async #buildSnapshot() {
    const [motorcycles, accessories] = await Promise.all([
      this.#readEntityDirectory('motorcycles'),
      this.#readEntityDirectory('accessories')
    ]);

    return {
      createdAt: Date.now(),
      expiresAt: Date.now() + this.#ttlMs,
      motorcycles,
      accessories,
      byEntity: {
        motorcycles: new Map(motorcycles.map((item) => [item.id, item])),
        accessories: new Map(accessories.map((item) => [item.id, item]))
      },
      links: this.#buildCrossLinks(motorcycles, accessories)
    };
  }

  async #readEntityDirectory(entity) {
    const directory = path.join(this.#catalogRoot, entity);
    const entries = await fs.readdir(directory, { withFileTypes: true });

    // Deterministic ordering must occur before slicing or pagination.
    const files = entries
      .filter((entry) => entry.isFile() && entry.name.endsWith('.json'))
      .map((entry) => entry.name)
      .sort((a, b) => a.localeCompare(b));

    const records = [];

    for (const filename of files) {
      const fullPath = path.join(directory, filename);
      const raw = await fs.readFile(fullPath, 'utf8');
      const value = JSON.parse(raw);

      const id = String(value.id ?? path.basename(filename, '.json'));

      records.push({
        ...value,
        id,
        entity
      });
    }

    return records;
  }

  #buildCrossLinks(motorcycles, accessories) {
    const motorcycleToAccessories = new Map();
    const accessoryToMotorcycles = new Map();

    for (const motorcycle of motorcycles) {
      const accessoryIds = new Set([
        ...(motorcycle.accessoryIds ?? []),
        ...(motorcycle.accessories ?? []).map((x) =>
          typeof x === 'string' ? x : x.id
        )
      ]);

      motorcycleToAccessories.set(
        motorcycle.id,
        [...accessoryIds]
          .map((id) => accessories.find((accessory) => accessory.id === id))
          .filter(Boolean)
      );
    }

    for (const accessory of accessories) {
      const motorcycleIds = new Set([
        ...(accessory.motorcycleIds ?? []),
        ...(accessory.motorcycles ?? []).map((x) =>
          typeof x === 'string' ? x : x.id
        )
      ]);

      accessoryToMotorcycles.set(
        accessory.id,
        [...motorcycleIds]
          .map((id) => motorcycles.find((motorcycle) => motorcycle.id === id))
          .filter(Boolean)
      );
    }

    return { motorcycleToAccessories, accessoryToMotorcycles };
  }

  #assertFresh() {
    if (!this.#snapshot || Date.now() >= this.#snapshot.expiresAt) {
      throw new Error('Catalog index is unavailable or expired');
    }
  }

  getPage(entity, { page = 1, pageSize = 50 } = {}) {
    this.#assertFresh();

    if (!['motorcycles', 'accessories'].includes(entity)) {
      throw new Error('Invalid catalog entity');
    }

    const safePage = Math.max(1, Number.parseInt(page, 10) || 1);
    const safePageSize = Math.min(
      100,
      Math.max(1, Number.parseInt(pageSize, 10) || 50)
    );

    const items = this.#snapshot[entity];
    const start = (safePage - 1) * safePageSize;

    return {
      page: safePage,
      pageSize: safePageSize,
      total: items.length,
      items: items.slice(start, start + safePageSize)
    };
  }

  getById(entity, id) {
    this.#assertFresh();

    if (!['motorcycles', 'accessories'].includes(entity)) {
      throw new Error('Invalid catalog entity');
    }

    return this.#snapshot.byEntity[entity].get(String(id)) ?? null;
  }

  getLinks(entity, id) {
    this.#assertFresh();

    if (entity === 'motorcycles') {
      return this.#snapshot.links.motorcycleToAccessories.get(String(id)) ?? [];
    }

    if (entity === 'accessories') {
      return this.#snapshot.links.accessoryToMotorcycles.get(String(id)) ?? [];
    }

    throw new Error('Invalid catalog entity');
  }

  status() {
    return {
      loaded: Boolean(this.#snapshot),
      createdAt: this.#snapshot?.createdAt ?? null,
      expiresAt: this.#snapshot?.expiresAt ?? null,
      motorcycleCount: this.#snapshot?.motorcycles.length ?? 0,
      accessoryCount: this.#snapshot?.accessories.length ?? 0
    };
  }
}
```

### Catalog routes

```js
// server/routes/catalog.js
import express from 'express';

export function createCatalogRouter({ catalogIndex }) {
  const router = express.Router();

  router.get('/:entity', (req, res) => {
    try {
      res.json(
        catalogIndex.getPage(req.params.entity, {
          page: req.query.page,
          pageSize: req.query.pageSize
        })
      );
    } catch (error) {
      res.status(400).json({ error: error.message });
    }
  });

  router.get('/:entity/:id', (req, res) => {
    try {
      const item = catalogIndex.getById(
        req.params.entity,
        req.params.id
      );

      if (!item) {
        return res.status(404).json({ error: 'Catalog item not found' });
      }

      res.json({
        item,
        related: catalogIndex.getLinks(
          req.params.entity,
          req.params.id
        )
      });
    } catch (error) {
      res.status(400).json({ error: error.message });
    }
  });

  return router;
}
```

This removes request-time directory traversal and the synchronous 80-file scan. Refresh can be run during startup and periodically:

```js
setInterval(() => {
  catalogIndex.refresh().catch((error) => {
    console.error('Catalog refresh failed:', error);
  });
}, 5 * 60 * 1000).unref();
```

---

## 2. Structured Global Job Manager

### `server/jobs/JobManager.js`

```js
import { EventEmitter } from 'node:events';
import crypto from 'node:crypto';

const TERMINAL_STATES = new Set(['succeeded', 'failed']);

export class JobManager extends EventEmitter {
  #jobs = new Map();

  create({ type, name, pid = null }) {
    const id = crypto.randomUUID();

    const job = {
      id,
      type,
      name,
      status: 'running',
      startedAt: new Date().toISOString(),
      endedAt: null,
      exitCode: null,
      error: null,
      pid
    };

    this.#jobs.set(id, job);
    this.emit('changed', this.publicJob(job));
    return this.publicJob(job);
  }

  get(id) {
    const job = this.#jobs.get(id);
    return job ? this.publicJob(job) : null;
  }

  list() {
    return [...this.#jobs.values()]
      .map((job) => this.publicJob(job))
      .sort((a, b) => b.startedAt.localeCompare(a.startedAt));
  }

  update(id, patch) {
    const job = this.#jobs.get(id);
    if (!job || TERMINAL_STATES.has(job.status)) return null;

    Object.assign(job, patch);

    if (TERMINAL_STATES.has(job.status)) {
      job.endedAt ??= new Date().toISOString();
    }

    this.emit('changed', this.publicJob(job));
    return this.publicJob(job);
  }

  succeed(id, exitCode = 0) {
    return this.update(id, {
      status: 'succeeded',
      exitCode,
      error: null
    });
  }

  fail(id, error, exitCode = 1) {
    return this.update(id, {
      status: 'failed',
      exitCode,
      error: error instanceof Error ? error.message : String(error)
    });
  }

  cancel(id) {
    const job = this.#jobs.get(id);
    if (!job || TERMINAL_STATES.has(job.status)) return false;

    if (job.pid) {
      try {
        process.kill(job.pid, 'SIGTERM');
      } catch (error) {
        if (error.code !== 'ESRCH') throw error;
      }
    }

    this.fail(id, 'Cancelled by operator', null);
    return true;
  }

  publicJob(job) {
    return { ...job };
  }
}
```

### Job routes

```js
// server/routes/jobs.js
import express from 'express';

export function createJobsRouter({ jobManager }) {
  const router = express.Router();

  router.get('/', (_req, res) => {
    res.json({ jobs: jobManager.list() });
  });

  router.post('/:id/cancel', (req, res) => {
    const job = jobManager.get(req.params.id);

    if (!job) {
      return res.status(404).json({ error: 'Job not found' });
    }

    if (job.status !== 'running') {
      return res.status(409).json({ error: 'Job is not running' });
    }

    jobManager.cancel(req.params.id);
    res.status(202).json({ job: jobManager.get(req.params.id) });
  });

  return router;
}
```

---

## 3. Translation Validation, Health, and Circuit Breaker

### `server/translation/validation.js`

```js
const ALLOWED_MODELS = new Set([
  'gemini-1.5-flash',
  'gemini-1.5-pro',
  'gpt-4o-mini',
  'gpt-4o'
]);

export function validateTranslationStart(body) {
  const count = Number(body.count);
  const batchSize = Number(body.batch_size);
  const workers = Number(body.workers);
  const model = String(body.model ?? '');

  if (!Number.isInteger(count) || count < 1 || count > 5415) {
    throw new Error('count must be an integer between 1 and 5415');
  }

  if (!Number.isInteger(batchSize) || batchSize < 1 || batchSize > 50) {
    throw new Error('batch_size must be an integer between 1 and 50');
  }

  if (!Number.isInteger(workers) || workers < 1 || workers > 8) {
    throw new Error('workers must be an integer between 1 and 8');
  }

  if (!ALLOWED_MODELS.has(model)) {
    throw new Error('Unsupported translation model');
  }

  return { count, batchSize, workers, model };
}
```

### `server/translation/NodeCircuitBreaker.js`

```js
export class NodeCircuitBreaker {
  #failureThreshold;
  #cooldownMs;
  #failures = 0;
  #openedAt = 0;

  constructor({ failureThreshold = 3, cooldownMs = 30_000 } = {}) {
    this.#failureThreshold = failureThreshold;
    this.#cooldownMs = cooldownMs;
  }

  get state() {
    if (this.#openedAt === 0) return 'closed';

    if (Date.now() - this.#openedAt >= this.#cooldownMs) {
      return 'half-open';
    }

    return 'open';
  }

  async execute(operation) {
    if (this.state === 'open') {
      throw new Error('Node B circuit is open');
    }

    try {
      const result = await operation();
      this.#failures = 0;
      this.#openedAt = 0;
      return result;
    } catch (error) {
      this.#failures += 1;

      if (this.#failures >= this.#failureThreshold) {
        this.#openedAt = Date.now();
      }

      throw error;
    }
  }
}

export async function fetchWithTimeout(url, options = {}, timeoutMs = 2_000) {
  const controller = new AbortController();
  const timeout = setTimeout(() => controller.abort(), timeoutMs);

  try {
    return await fetch(url, {
      ...options,
      signal: controller.signal
    });
  } finally {
    clearTimeout(timeout);
  }
}
```

### Translation swarm service

```js
// server/translation/TranslationSwarm.js
import { fetchWithTimeout } from './NodeCircuitBreaker.js';

export class TranslationSwarm {
  #nodeA;
  #nodeB;
  #breaker;

  constructor({ nodeA, nodeB, breaker }) {
    this.#nodeA = nodeA;
    this.#nodeB = nodeB;
    this.#breaker = breaker;
  }

  async pingNodeB() {
    try {
      const response = await fetchWithTimeout(
        `${this.#nodeB}/health`,
        { method: 'GET' },
        2_000
      );

      if (!response.ok) {
        throw new Error(`Node B returned HTTP ${response.status}`);
      }

      return true;
    } catch {
      return false;
    }
  }

  async allocateWorkers(requestedWorkers) {
    const nodeBHealthy = await this.pingNodeB();

    if (!nodeBHealthy || this.#breaker.state === 'open') {
      return {
        nodeA: requestedWorkers,
        nodeB: 0,
        nodeBHealthy: false
      };
    }

    const nodeBWorkers = Math.floor(requestedWorkers / 2);

    return {
      nodeA: requestedWorkers - nodeBWorkers,
      nodeB: nodeBWorkers,
      nodeBHealthy: true
    };
  }

  async dispatch({ count, batchSize, workers, model, onHeartbeat }) {
    const allocation = await this.allocateWorkers(workers);
    const tasks = [];

    for (let i = 0; i < allocation.nodeA; i += 1) {
      tasks.push(
        this.#dispatchWorker({
          node: this.#nodeA,
          count,
          batchSize,
          model,
          workerIndex: i,
          onHeartbeat
        })
      );
    }

    for (let i = 0; i < allocation.nodeB; i += 1) {
      tasks.push(
        this.#dispatchNodeB({
          count,
          batchSize,
          model,
          workerIndex: allocation.nodeA + i,
          onHeartbeat
        })
      );
    }

    return {
      allocation,
      result: await Promise.all(tasks)
    };
  }

  async #dispatchNodeB(args) {
    try {
      return await this.#breaker.execute(() =>
        this.#dispatchWorker({
          node: this.#nodeB,
          ...args
        })
      );
    } catch {
      // Node B failure does not fail the entire batch.
      return this.#dispatchWorker({
        node: this.#nodeA,
        ...args
      });
    }
  }

  async #dispatchWorker({
    node,
    count,
    batchSize,
    model,
    workerIndex,
    onHeartbeat
  }) {
    const response = await fetch(`${node}/translation/dispatch`, {
      method: 'POST',
      headers: { 'content-type': 'application/json' },
      body: JSON.stringify({
        count,
        batch_size: batchSize,
        model,
        worker_index: workerIndex
      })
    });

    if (!response.ok) {
      throw new Error(`Worker dispatch failed with HTTP ${response.status}`);
    }

    const result = await response.json();

    onHeartbeat?.({
      workerIndex,
      node,
      at: new Date().toISOString(),
      healthy: true
    });

    return result;
  }
}
```

### Translation route with heartbeat state

```js
// server/routes/translation.js
import express from 'express';
import { validateTranslationStart } from '../translation/validation.js';

export function createTranslationRouter({
  swarm,
  jobManager,
  heartbeatRegistry
}) {
  const router = express.Router();

  router.post('/bot/start', async (req, res) => {
    let input;

    try {
      input = validateTranslationStart(req.body);
    } catch (error) {
      return res.status(400).json({ error: error.message });
    }

    const job = jobManager.create({
      type: 'translation',
      name: `Translation batch: ${input.count}`
    });

    void (async () => {
      try {
        const result = await swarm.dispatch({
          ...input,
          onHeartbeat: (heartbeat) => {
            heartbeatRegistry.set(heartbeat.workerIndex, heartbeat);
          }
        });

        jobManager.succeed(job.id, 0);
        return result;
      } catch (error) {
        jobManager.fail(job.id, error);
      }
    })();

    res.status(202).json({
      job: jobManager.get(job.id),
      health: heartbeatRegistry.snapshot()
    });
  });

  router.get('/bot/health', (_req, res) => {
    res.json({ workers: heartbeatRegistry.snapshot() });
  });

  return router;
}
```

### Heartbeat registry

```js
// server/translation/HeartbeatRegistry.js
export class HeartbeatRegistry {
  #workers = new Map();
  #staleAfterMs;

  constructor({ staleAfterMs = 15_000 } = {}) {
    this.#staleAfterMs = staleAfterMs;
  }

  set(workerId, heartbeat) {
    this.#workers.set(String(workerId), {
      ...heartbeat,
      receivedAt: Date.now()
    });
  }

  snapshot() {
    const now = Date.now();

    return [...this.#workers.entries()].map(([workerId, heartbeat]) => ({
      workerId,
      ...heartbeat,
      status:
        now - heartbeat.receivedAt <= this.#staleAfterMs
          ? 'healthy'
          : 'stale'
    }));
  }
}
```

---

## 4. Google Intelligence HTML Sanitization

Do not interpolate query strings, URLs, or anomaly text directly into HTML.

### `server/security/html.js`

```js
export function escapeHtml(value) {
  return String(value)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

export function safeHttpUrl(value) {
  try {
    const url = new URL(String(value));

    if (!['http:', 'https:'].includes(url.protocol)) {
      return null;
    }

    return escapeHtml(url.toString());
  } catch {
    return null;
  }
}

export function renderGoogleResult(result) {
  const title = escapeHtml(result.title);
  const snippet = escapeHtml(result.snippet);
  const url = safeHttpUrl(result.url);

  return `
    <article class="google-result">
      <h3>
        ${
          url
            ? `<a href="${url}" target="_blank" rel="noopener noreferrer">${title}</a>`
            : title
        }
      </h3>
      <p>${snippet}</p>
    </article>
  `;
}

export function renderAnomaly(anomaly) {
  return `
    <li class="anomaly">
      <span class="anomaly-code">${escapeHtml(anomaly.code)}</span>
      <span class="anomaly-text">${escapeHtml(anomaly.text)}</span>
    </li>
  `;
}
```

Prefer DOM APIs in browser code:

```js
const title = document.createElement('span');
title.textContent = result.title;
```

Never use `innerHTML` with unsanitized API data.

---

## 5. Server Bootstrap and WebSocket Sequencing

### `server.js`

```js
import express from 'express';
import http from 'node:http';
import { WebSocketServer } from 'ws';

import { CatalogIndex } from './server/catalog/CatalogIndex.js';
import { JobManager } from './server/jobs/JobManager.js';
import { HeartbeatRegistry } from './server/translation/HeartbeatRegistry.js';
import { NodeCircuitBreaker } from './server/translation/NodeCircuitBreaker.js';
import { TranslationSwarm } from './server/translation/TranslationSwarm.js';

import { createCatalogRouter } from './server/routes/catalog.js';
import { createJobsRouter } from './server/routes/jobs.js';
import { createTranslationRouter } from './server/routes/translation.js';

const app = express();
const server = http.createServer(app);

app.use(express.json({ limit: '1mb' }));

const jobManager = new JobManager();

const catalogIndex = new CatalogIndex({
  catalogRoot: process.env.CATALOG_ROOT ?? './catalog'
});

await catalogIndex.start();

const heartbeatRegistry = new HeartbeatRegistry();

const swarm = new TranslationSwarm({
  nodeA: process.env.NODE_A_URL,
  nodeB: process.env.NODE_B_URL,
  breaker: new NodeCircuitBreaker()
});

app.use('/api/catalog', createCatalogRouter({ catalogIndex }));
app.use('/api/jobs', createJobsRouter({ jobManager }));
app.use(
  '/api/translation',
  createTranslationRouter({
    swarm,
    jobManager,
    heartbeatRegistry
  })
);

const wss = new WebSocketServer({
  server,
  path: '/ws'
});

let sequence = 0;
const clients = new Set();

wss.on('connection', (socket) => {
  clients.add(socket);

  socket.on('close', () => clients.delete(socket));
  socket.on('error', () => clients.delete(socket));

  socket.send(
    JSON.stringify({
      type: 'connection.ready',
      seq: ++sequence,
      ts: new Date().toISOString()
    })
  );
});

function broadcast(type, payload = {}) {
  const message = JSON.stringify({
    type,
    seq: ++sequence,
    ts: new Date().toISOString(),
    payload
  });

  for (const client of clients) {
    if (client.readyState === client.OPEN) {
      client.send(message);
    }
  }
}

jobManager.on('changed', (job) => {
  broadcast('job.changed', { job });
});

setInterval(() => {
  broadcast('system.heartbeat', {
    catalog: catalogIndex.status(),
    workers: heartbeatRegistry.snapshot()
  });
}, 5_000).unref();

const port = Number(process.env.PORT ?? 3000);

server.listen(port, () => {
  console.log(`Mission Control V2 listening on port ${port}`);
});
```

Every broadcast has:

```json
{
  "type": "job.changed",
  "seq": 42,
  "ts": "2025-01-01T12:00:00.000Z",
  "payload": {}
}
```

The client can detect missed messages by checking whether `seq` is exactly one greater than the last received sequence.

---

## 6. Channel-Isolated Terminal

### `client/terminal/TerminalStore.js`

```js
export class TerminalStore {
  #channels = new Map([
    ['web-ops', []],
    ['translation', []],
    ['deployment', []]
  ]);

  append(channel, entry) {
    if (!this.#channels.has(channel)) {
      this.#channels.set(channel, []);
    }

    this.#channels.get(channel).push({
      ...entry,
      timestamp: entry.timestamp ?? new Date().toISOString()
    });
  }

  clear(channel) {
    if (!this.#channels.has(channel)) {
      this.#channels.set(channel, []);
    }

    this.#channels.set(channel, []);
  }

  get(channel) {
    return [...(this.#channels.get(channel) ?? [])];
  }
}
```

### Smart scrolling

```js
// client/terminal/TerminalView.js
export class TerminalView {
  #element;
  #autoScroll = true;

  constructor(element) {
    this.#element = element;

    element.addEventListener('scroll', () => {
      this.#autoScroll = this.isAtBottom();
    });
  }

  isAtBottom() {
    const { scrollTop, clientHeight, scrollHeight } = this.#element;

    return scrollTop + clientHeight >= scrollHeight - 30;
  }

  render(entries) {
    const wasAtBottom = this.#autoScroll;

    const fragment = document.createDocumentFragment();

    for (const entry of entries) {
      const line = document.createElement('div');
      line.className = `terminal-line terminal-${entry.level ?? 'info'}`;

      // textContent prevents terminal log injection.
      line.textContent = `[${entry.timestamp}] ${entry.message}`;

      fragment.appendChild(line);
    }

    this.#element.replaceChildren(fragment);

    if (wasAtBottom) {
      this.#element.scrollTop = this.#element.scrollHeight;
    }
  }
}
```

### Channel-aware controller

```js
const terminalStore = new TerminalStore();

const views = {
  'web-ops': new TerminalView(document.querySelector('#terminal-web-ops')),
  translation: new TerminalView(
    document.querySelector('#terminal-translation')
  ),
  deployment: new TerminalView(
    document.querySelector('#terminal-deployment')
  )
};

export function appendTerminal(channel, entry) {
  terminalStore.append(channel, entry);
  views[channel]?.render(terminalStore.get(channel));
}

export function clearTerminal(channel) {
  terminalStore.clear(channel);
  views[channel]?.render([]);
}
```

`clearTerminal('web-ops')` now affects only the Web Ops channel.

---

## 7. Client-Side Action Confirmation

### `client/actions/confirmAction.js`

```js
export function confirmAction({
  title,
  message,
  confirmLabel = 'Confirm',
  danger = true
}) {
  return new Promise((resolve) => {
    const dialog = document.createElement('dialog');

    dialog.innerHTML = `
      <form method="dialog" class="confirm-dialog">
        <h2></h2>
        <p></p>
        <div class="confirm-actions">
          <button value="cancel" type="submit">Cancel</button>
          <button value="confirm" type="submit"></button>
        </div>
      </form>
    `;

    dialog.querySelector('h2').textContent = title;
    dialog.querySelector('p').textContent = message;

    const confirmButton = dialog.querySelector(
      'button[value="confirm"]'
    );

    confirmButton.textContent = confirmLabel;
    confirmButton.classList.toggle('danger', danger);

    dialog.addEventListener(
      'close',
      () => {
        const confirmed = dialog.returnValue === 'confirm';
        dialog.remove();
        resolve(confirmed);
      },
      { once: true }
    );

    document.body.appendChild(dialog);
    dialog.showModal();
  });
}
```

### Protected operations

```js
import { confirmAction } from './confirmAction.js';

async function postJson(url, body = undefined) {
  const response = await fetch(url, {
    method: 'POST',
    headers: body
      ? { 'content-type': 'application/json' }
      : undefined,
    body: body ? JSON.stringify(body) : undefined
  });

  if (!response.ok) {
    throw new Error(`Request failed with HTTP ${response.status}`);
  }

  return response.json();
}

export async function deploy() {
  const confirmed = await confirmAction({
    title: 'Deploy release?',
    message: 'This will deploy the selected release to production.',
    confirmLabel: 'Deploy'
  });

  if (!confirmed) return;

  return postJson('/api/deploy');
}

export async function purgeCloudflare() {
  const confirmed = await confirmAction({
    title: 'Purge Cloudflare cache?',
    message: 'All configured Cloudflare cache entries will be purged.',
    confirmLabel: 'Purge cache'
  });

  if (!confirmed) return;

  return postJson('/api/cloudflare/purge');
}

export async function recompileDatabase() {
  const confirmed = await confirmAction({
    title: 'Recompile database?',
    message:
      'This may lock database resources and can affect active operations.',
    confirmLabel: 'Recompile database'
  });

  if (!confirmed) return;

  return postJson('/api/database/recompile');
}
```

For production use, the three destructive endpoints should also enforce server-side authorization, CSRF protection where applicable, audit logging, and idempotency keys. Client-side confirmation is an operator safeguard, not an authorization boundary.