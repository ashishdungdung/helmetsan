with open('HelmetsanWeb/scripts/metal_translation_bot.py', 'r') as f:
    code = f.read()

# 1. Add health cache and smart endpoint selection
target_call = """def call_metal_model(model, system_prompt, user_payload, max_tokens=1500, timeout=180):
    \"\"\"Execute inference via Apple Silicon Metal with multi-node support (Node A / Node B).\"\"\"
    req_body = {
        \"model\": model,
        \"messages\": [
            {\"role\": \"system\", \"content\": system_prompt},
            {\"role\": \"user\", \"content\": json.dumps(user_payload, ensure_ascii=False)}
        ],
        \"temperature\": 0.1,
        \"max_tokens\": max_tokens
    }

    candidate_endpoints = [NODE_A_URL]
    if NODE_B_URL and NODE_B_URL != NODE_A_URL:
        candidate_endpoints.append(NODE_B_URL)

    headers = {\"Content-Type\": \"application/json\"}
    payload_bytes = json.dumps(req_body).encode(\"utf-8\")

    for node_url in candidate_endpoints:
        url = f\"{node_url}/chat/completions\"
        req = urllib.request.Request(url, data=payload_bytes, headers=headers)
        t0 = time.time()
        try:
            with urllib.request.urlopen(req, timeout=timeout) as response:
                result = json.loads(response.read().decode(\"utf-8\"))
                elapsed = time.time() - t0
                msg = result[\"choices\"][0][\"message\"]
                content = msg.get(\"content\", \"\").strip()
                if not content and \"reasoning_content\" in msg:
                    content = msg[\"reasoning_content\"].strip()
                return content, elapsed
        except Exception as e:
            log(f\"⚠️ Node ({node_url}) inference failed: {e}. Trying next available node...\")

    log(\"❌ All Apple Silicon inference nodes exhausted for this candidate.\")
    return None, 0"""

replacement_call = """_NODE_HEALTH_CACHE = {}

def is_node_alive(node_url, probe_timeout=1.5):
    \"\"\"Fast non-blocking node reachability probe with 30s cache to avoid timeout hangs.\"\"\"
    now = time.time()
    if node_url in _NODE_HEALTH_CACHE:
        alive, cached_time = _NODE_HEALTH_CACHE[node_url]
        if now - cached_time < 30:
            return alive
    try:
        req = urllib.request.Request(f\"{node_url}/models\")
        with urllib.request.urlopen(req, timeout=probe_timeout) as resp:
            alive = (resp.status == 200)
            _NODE_HEALTH_CACHE[node_url] = (alive, now)
            return alive
    except Exception:
        _NODE_HEALTH_CACHE[node_url] = (False, now)
        return False

def call_metal_model(model, system_prompt, user_payload, max_tokens=1500, compute_node="swarm", timeout=180):
    \"\"\"Execute inference via Apple Silicon Metal with intelligent multi-node routing & Node B failover.\"\"\"
    req_body = {
        \"model\": model,
        \"messages\": [
            {\"role\": \"system\", \"content\": system_prompt},
            {\"role\": \"user\", \"content\": json.dumps(user_payload, ensure_ascii=False)}
        ],
        \"temperature\": 0.1,
        \"max_tokens\": max_tokens
    }

    candidate_endpoints = []
    if compute_node == \"node_a\":
        candidate_endpoints = [NODE_A_URL]
    elif compute_node == \"node_b\":
        if is_node_alive(NODE_B_URL):
            candidate_endpoints = [NODE_B_URL]
        else:
            log(f\"⚠️ Node B ({NODE_B_URL}) is currently offline/standby. Automatic failover to Node A ({NODE_A_URL}).\")
            candidate_endpoints = [NODE_A_URL]
    else:  # swarm mode
        candidate_endpoints = [NODE_A_URL]
        if NODE_B_URL and NODE_B_URL != NODE_A_URL:
            if is_node_alive(NODE_B_URL):
                candidate_endpoints.append(NODE_B_URL)

    headers = {\"Content-Type\": \"application/json\"}
    payload_bytes = json.dumps(req_body).encode(\"utf-8\")

    for node_url in candidate_endpoints:
        url = f\"{node_url}/chat/completions\"
        req = urllib.request.Request(url, data=payload_bytes, headers=headers)
        t0 = time.time()
        try:
            with urllib.request.urlopen(req, timeout=timeout) as response:
                result = json.loads(response.read().decode(\"utf-8\"))
                elapsed = time.time() - t0
                msg = result[\"choices\"][0][\"message\"]
                content = msg.get(\"content\", \"\").strip()
                if not content and \"reasoning_content\" in msg:
                    content = msg[\"reasoning_content\"].strip()
                return content, elapsed
        except Exception as e:
            log(f\"⚠️ Node ({node_url}) inference failed: {e}. Trying next available node...\")

    log(\"❌ All Apple Silicon inference nodes exhausted for this candidate.\")
    return None, 0"""

if target_call in code:
    code = code.replace(target_call, replacement_call)
    print("Updated call_metal_model in metal_translation_bot.py")

# 2. Update translate_helmet_metal signature and call
target_trans = "def translate_helmet_metal(helmet, lang, model=DEFAULT_MODEL):"
repl_trans = "def translate_helmet_metal(helmet, lang, model=DEFAULT_MODEL, compute_node=\"swarm\", timeout=180):"
if target_trans in code:
    code = code.replace(target_trans, repl_trans)
    code = code.replace("raw_text, elapsed = call_metal_model(model, system_prompt, source_payload)",
                        "raw_text, elapsed = call_metal_model(model, system_prompt, source_payload, compute_node=compute_node, timeout=timeout)")
    print("Updated translate_helmet_metal in metal_translation_bot.py")

# 3. Update TranslationBot __init__
target_bot_init = "class TranslationBot:\n    def __init__(self, target_lang=\"de\", model=DEFAULT_MODEL, batch_size=10, all_langs=False, max_count=None, workers=2, post_id=None):"
repl_bot_init = "class TranslationBot:\n    def __init__(self, target_lang=\"de\", model=DEFAULT_MODEL, batch_size=10, all_langs=False, max_count=None, workers=2, post_id=None, compute_node=\"swarm\", timeout=180):\n        self.compute_node = compute_node\n        self.timeout = timeout"
if target_bot_init in code:
    code = code.replace(target_bot_init, repl_bot_init)
    code = code.replace("parsed, elapsed = translate_helmet_metal(helmet, lang, self.model)",
                        "parsed, elapsed = translate_helmet_metal(helmet, lang, self.model, compute_node=self.compute_node, timeout=self.timeout)")
    print("Updated TranslationBot __init__ in metal_translation_bot.py")

# 4. Add CLI arguments for --compute-node and --timeout
target_args = '    parser.add_argument("--post-id", type=int, default=None, help="Translate a specific helmet ID on-demand")'
repl_args = """    parser.add_argument("--post-id", type=int, default=None, help="Translate a specific helmet ID on-demand")
    parser.add_argument("--compute-node", default="swarm", choices=["swarm", "node_a", "node_b"], help="Target compute node: swarm, node_a, or node_b")
    parser.add_argument("--timeout", type=int, default=180, help="Per-candidate model inference timeout in seconds (default: 180)")"""

if target_args in code:
    code = code.replace(target_args, repl_args)
    print("Added --compute-node and --timeout arguments to metal_translation_bot.py")

# Also pass to start_daemon and TranslationBot instantiate in main()
target_bot_inst = "bot = TranslationBot(target_lang=args.lang, model=args.model, batch_size=args.batch_size, all_langs=args.all_langs, max_count=args.count, workers=args.workers, post_id=args.post_id)"
repl_bot_inst = "bot = TranslationBot(target_lang=args.lang, model=args.model, batch_size=args.batch_size, all_langs=args.all_langs, max_count=args.count, workers=args.workers, post_id=args.post_id, compute_node=args.compute_node, timeout=args.timeout)"
if target_bot_inst in code:
    code = code.replace(target_bot_inst, repl_bot_inst)
    print("Updated TranslationBot instantiation in main()")

with open('HelmetsanWeb/scripts/metal_translation_bot.py', 'w') as f:
    f.write(code)

print("Saved metal_translation_bot.py updates!")
