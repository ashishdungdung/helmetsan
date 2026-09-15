# Experiential Gateway Deep Audit Report
**Auditor Model:** `gpt-5.6-luna`
**Gateway:** `https://api.experientiallabs.ai/v1`

## Token Usage & Billing
```json
{
  "prompt_tokens": 20743,
  "completion_tokens": 5500,
  "total_tokens": 26243,
  "prompt_tokens_details": {
    "cached_tokens": 0
  },
  "completion_tokens_details": {
    "reasoning_tokens": 516
  },
  "cost": 0.0,
  "is_byok": false
}
```

## Audit Verdict & Analysis

# Executive verdict

**Current status: not enterprise-ready.**

The integration has a reasonable initial provider abstraction and correctly constructs the basic non-streaming Chat Completions request for a gateway-compatible endpoint. However, the implementation currently has several **critical correctness and security weaknesses**:

1. **Streaming is not implemented**, despite forwarding `stream=true`.
2. **Tool-calling is only pass-through**, not fully supported or validated.
3. **Dynamic model routing is inconsistent** between `OpenAIProvider`, `ExperientialProvider`, and `ProviderRegistry`.
4. **Gateway-specific ignored-parameter behavior is not handled**, so requests may silently lose controls such as `temperature`.
5. **No real budget enforcement exists**; only a loosely parsed optional `usage.cost` is exposed.
6. **Credential handling is duplicated, overexposed, and can produce fatal exceptions in WordPress execution paths.**
7. **Arbitrary base URLs can cause bearer-token exfiltration** if configuration is compromised or an administrator supplies an unsafe URL.
8. **HTTP errors, 429s, timeouts, malformed responses, and partial failures are reduced to `null` with no structured diagnostics or retry policy.**
9. **The image analysis change routes a fixed vision request through any configured Experiential model without checking vision capability.**
10. The automated tests verify only object construction and payload assembly; they do not test actual wire behavior, failures, streaming, cost accounting, or WordPress integration.

The most important recommendation is to create a single, explicit **Experiential gateway transport/provider** with centralized credential resolution, endpoint validation, timeout/retry policy, request normalization, streaming support, usage/cost normalization, and structured exceptions. Do not make `OpenAIProvider` double as an Experiential provider.

---

# 1. Gateway wire protocol and OpenAI compatibility

## 1.1 Basic endpoint and headers

The normal endpoint is constructed as:

```text
https://api.experientiallabs.ai/v1/chat/completions
```

The request includes:

```http
Authorization: Bearer <key>
Content-Type: application/json
```

This is consistent with OpenAI-compatible Chat Completions semantics.

The `/models` request is also structurally reasonable:

```http
GET /v1/models
Authorization: Bearer <key>
Content-Type: application/json
```

However:

- `Content-Type` is unnecessary for a `GET`.
- `Accept: application/json` should be included.
- There is no explicit TLS verification configuration in the cURL path.
- The cURL branch does not capture or report transport errors.
- The response is assumed to be HTTP 200 with a JSON body.
- No pagination handling exists.
- No model capability or pricing metadata is retained.

The gateway may return an OpenAI-shaped model list, but the implementation assumes only:

```php
$data['data']
```

That is not enough to support safe model selection. A production catalog should normalize at least:

```php
[
    'id' => '...',
    'owned_by' => '...',
    'context_window' => null,
    'supports_vision' => false,
    'supports_tools' => false,
    'supports_streaming' => false,
    'input_price' => null,
    'output_price' => null,
    'status' => 'active',
]
```

### Conclusion

The basic URL and authorization shape are correct, but the catalog implementation is not sufficient for capability-aware dynamic routing or budgeting.

---

## 1.2 Payload construction

The primary payload is:

```php
[
    'model' => $this->model,
    'messages' => $messages,
    'max_tokens' => ...,
    'temperature' => ...,
]
```

This is broadly compatible with Chat Completions, but several issues are material.

### Duplicate token parameters

If `max_completion_tokens` is supplied, the implementation sends both:

```php
'max_tokens' => ...
'max_completion_tokens' => ...
```

That is unsafe.

Some OpenAI-compatible gateways reject the request when both are present. Others choose one silently. A gateway may also report one as ignored. The implementation should send exactly one token-control field based on the selected protocol mode:

```php
if (array_key_exists('max_completion_tokens', $options)) {
    $body['max_completion_tokens'] = $validatedValue;
} else {
    $body['max_tokens'] = $validatedValue;
}
```

Do not send both.

### No option validation

The following values are accepted without validation:

- `max_tokens`
- `max_completion_tokens`
- `temperature`
- `tools`
- `tool_choice`
- `response_format`
- `messages`
- `stream`

Potential problems include:

- negative token limits;
- zero token limits;
- excessively large limits;
- string values where integers are expected;
- malformed message structures;
- malformed tool schemas;
- unsupported `response_format` values;
- tool names exceeding gateway limits;
- invalid role/content combinations.

A model-switching layer must validate options against both:

1. protocol constraints; and
2. model capability constraints.

### Unsupported parameters are silently omitted

The provider does not forward common Chat Completions fields such as:

- `seed`
- `stop`
- `top_p`
- `frequency_penalty`
- `presence_penalty`
- `logprobs`
- `top_logprobs`
- `n`
- `user`
- `parallel_tool_calls`
- `service_tier`
- `store`
- gateway-specific routing or budget fields

It is acceptable to support a smaller subset, but the API should explicitly define that subset. Silently ignoring caller intent is dangerous, particularly for temperature, token limits, deterministic generation, and tool execution.

### `wp_json_encode()` dependency

`prepareRequest()` directly calls:

```php
wp_json_encode($payload)
```

This makes the provider depend on WordPress being loaded. Unlike `listModels()`, there is no non-WordPress fallback.

That creates failures in:

- PHPUnit tests without WordPress bootstrap;
- CLI scripts;
- queue workers;
- migration commands;
- isolated service tests.

Use a transport/request serializer abstraction or a guarded JSON encoder. At minimum:

```php
$json = function_exists('wp_json_encode')
    ? wp_json_encode($payload)
    : json_encode($payload, JSON_THROW_ON_ERROR);
```

Also detect encoding failure. Returning a request with a `false` body is not acceptable.

### Conclusion

The non-streaming payload is only partially protocol-correct. Duplicate token fields, unvalidated options, WordPress coupling, and silent parameter loss must be corrected before dynamic model switching is trusted.

---

## 1.3 `messages` behavior is inconsistent

`ExperientialProvider` supports caller-supplied messages:

```php
$options['messages']
```

`OpenAIProvider` does not. It always constructs:

```php
'messages' => [['role' => 'user', 'content' => $prompt]]
```

This means:

- conversation history works through `ExperientialProvider`;
- conversation history is discarded through `OpenAIProvider`;
- tool-result messages cannot be continued through `OpenAIProvider`;
- system messages are lost;
- multimodal messages passed through options are lost.

This is especially problematic because `OpenAIProvider` is being used as an alias for Experiential in some registry paths. The same logical model can behave differently depending on whether it was instantiated through `openai` or `experiential`.

The provider contract should define one canonical request path:

```php
buildMessages($prompt, $options)
```

or require the caller to provide a complete message list.

### Conclusion

Message handling is not behaviorally consistent across provider classes. This can break conversation state, tool loops, system instructions, and multimodal requests.

---

## 1.4 Streaming is not implemented

The code claims:

```php
// Preserve streaming and tool-calling
```

but merely forwards:

```php
$body['stream'] = (bool) $options['stream'];
```

`generateDetailed()` then calls:

```php
$data = $this->post(...);
```

The expected response for streaming is typically:

```text
data: {"id":"...","choices":[{"delta":{"content":"Hel"}}]}

data: {"id":"...","choices":[{"delta":{"content":"lo"}}]}

data: [DONE]
```

That is **Server-Sent Events**, not one JSON document.

Unless `BaseProvider::post()` has an undocumented SSE parser—which is unlikely given the return shape—this implementation will attempt to decode the streaming response as ordinary JSON and fail.

Even if `BaseProvider::post()` happens to return something, `generateDetailed()` expects:

```php
$data['choices'][0]['message']
```

Streaming chunks use:

```php
$data['choices'][0]['delta']
```

Consequences:

- `stream=true` likely produces `null`;
- partial content is lost;
- usage may appear only in the final chunk and be lost;
- tool-call deltas cannot be assembled;
- disconnect recovery is impossible;
- time-to-first-token is not exposed;
- consumers cannot cancel streams;
- no backpressure handling exists.

A correct implementation needs a separate streaming API, for example:

```php
public function stream(
    array $messages,
    array $options,
    callable $onEvent
): StreamResult
```

The parser must support:

- multiple `data:` lines;
- blank-line event boundaries;
- `[DONE]`;
- partial UTF-8 boundaries;
- content delta aggregation;
- tool-call argument fragments;
- multiple tool-call indexes;
- finish reasons;
- final usage events;
- gateway error events;
- connection termination;
- maximum stream duration.

For tool calls, fragments may arrive as:

```json
{
  "tool_calls": [
    {
      "index": 0,
      "id": "call_1",
      "function": {
        "name": "lookup_product",
        "arguments": "{\"sku\":\"HE"
      }
    }
  ]
}
```

The implementation must concatenate arguments by tool-call index and only decode JSON after completion.

### Conclusion

Streaming fidelity is currently a critical failure. Forwarding `stream=true` without an SSE transport and delta assembler is not support; it is an incompatibility hazard. Streaming should be implemented separately and tested with chunked fixtures.

---

## 1.5 Tool-calling fidelity

The implementation forwards:

```php
'tools' => $options['tools']
'tool_choice' => $options['tool_choice']
```

and extracts:

```php
$rawMessage['tool_calls'] ?? null
```

That is a useful start, but it does not constitute full tool-calling support.

Missing elements include:

- validation of tool schemas;
- preservation of `parallel_tool_calls`;
- support for the legacy `function_call` field;
- normalization of gateway-specific tool-call formats;
- handling assistant messages containing `tool_calls`;
- handling `tool` role messages with `tool_call_id`;
- streamed tool-call fragment assembly;
- validation that `arguments` is valid JSON;
- safe dispatch and authorization of tools;
- loop limits;
- duplicate-call detection;
- tool execution timeout and error messages;
- correlation IDs and audit logs.

There is also a semantic problem: `generate()` returns only text:

```php
return $result['content'] ?? null;
```

If a model returns a tool call with no textual content, `generate()` returns `null` and discards the actionable result. Callers using the simple API cannot distinguish:

- no response;
- failed response;
- tool call requested;
- empty but successful response.

The provider should expose a typed result object or at least a stable result shape with a status:

```php
[
    'kind' => 'tool_call',
    'content' => null,
    'tool_calls' => [...],
]
```

### Conclusion

Tool-call pass-through is incomplete. Non-streaming basic tool responses may work, but tool execution loops and streaming tool calls are not production-safe.

---

## 1.6 Response parsing

The response parser handles:

```php
choices[0].message.content
choices[0].message.tool_calls
choices[0].finish_reason
usage
```

This is broadly OpenAI-shaped.

Problems:

1. It only examines the first choice.
2. It does not validate that `choices` is an array.
3. It does not validate the message shape.
4. It does not distinguish an empty response from malformed response.
5. It does not preserve refusal fields.
6. It does not preserve annotations or structured content parts.
7. It may incorrectly join multimodal content parts with `"\n"`.
8. It normalizes model output before callers can access exact content.
9. It assumes gateway content parts use `type=text` and `text`.
10. It does not normalize provider-specific finish reasons.

The content extraction code:

```php
if (is_array($rawContent)) {
    foreach ($rawContent as $part) {
        if (($part['type'] ?? '') === 'text') {
            $textParts[] = $part['text'];
        }
    }
}
```

will discard:

- image output parts;
- refusal parts;
- citations;
- provider-specific content;
- unknown but valid content blocks.

That may be acceptable for a text-only API, but it should be explicit.

### Conclusion

The parser is adequate for a narrow text-only, non-streaming success response but not for full OpenAI-compatible semantics.

---

# 2. Credential safety and environment handling

## 2.1 Environment-key precedence

`ExperientialProvider` resolves:

```php
$envKey = getenv('EXPLABS_API_KEY');
$key = !empty($envKey) ? trim((string) $envKey) : trim($this->apiKey);
```

This gives the environment variable precedence over the constructor argument.

That is defensible for deployment security, but it creates operational surprises:

- administrators cannot override the environment key through settings;
- tests may become contaminated by process-level environment state;
- long-running PHP workers may retain old credentials;
- rotated keys may not be picked up predictably;
- the effective key source is not visible in diagnostics.

The registry does this inconsistently:

```php
$explabsKey = getenv('EXPLABS_API_KEY') ?: trim((string) ($cfg['api_key'] ?? ''));
```

and some paths require the environment key while others permit a configured key.

For example:

- `getExperiential()` requires `EXPLABS_API_KEY`;
- `getForModel()` requires `EXPLABS_API_KEY`;
- `ExperientialProvider` itself allows constructor fallback;
- the registry’s `experiential` branch allows settings fallback in principle;
- the `openai` special case requires the environment variable.

The implementation therefore does not have one credential policy.

Recommended policy:

```text
1. Explicit secret-manager injection
2. Environment variable
3. WordPress settings only if explicitly permitted
4. Never log the key
```

Make the policy centralized in a credential resolver.

### Conclusion

Credential precedence is inconsistent and difficult to reason about. Centralize resolution and document the source priority.

---

## 2.2 Missing-key exceptions during WordPress runtime

Several paths throw:

```php
throw new \RuntimeException(...)
```

This is dangerous in WordPress because provider discovery may happen during:

- admin page rendering;
- REST API initialization;
- AJAX requests;
- cron;
- front-end hooks;
- image processing;
- SEO hooks;
- bulk imports;
- plugin activation;
- health checks.

`ProviderRegistry::getAll()` catches `Throwable`, which prevents some fatal errors, but this has two drawbacks:

1. Configuration failures are silently swallowed.
2. The caller cannot tell whether the provider is disabled, unavailable, or misconfigured.

More importantly, direct calls such as:

```php
$this->registry->get('experiential')
```

in `ImageAnalysisService` can throw if the provider is enabled but the key is absent.

The service does not catch that exception.

A missing optional provider should not take down unrelated WordPress functionality. Use a result/status model:

```php
ProviderAvailability {
    configured: bool,
    reason: 'missing_key'|'disabled'|'invalid_url'|'ready',
}
```

or have the registry return `null` for optional discovery and emit a rate-limited diagnostic.

Exceptions are appropriate for an explicit operation such as “execute this request,” but not for broad provider enumeration.

### Conclusion

The current behavior can cause fatal runtime failures in direct registry consumers and hides failures in bulk discovery. Optional provider configuration must be non-fatal and diagnostically visible.

---

## 2.3 Secret exposure through public getters

The provider exposes:

```php
public function getApiKey(): string
```

This was added for image analysis. It increases the risk of accidental leakage through:

- debug dumps;
- REST responses;
- admin notices;
- exception context;
- object serialization;
- logging;
- PHPUnit failure output;
- telemetry.

The image service should not need the raw credential. Instead, the provider should expose a request method:

```php
$provider->complete($messages, $options);
```

or a protected transport abstraction.

If a getter is unavoidable, use a masked diagnostic accessor separately:

```php
getMaskedApiKey(): string
```

and keep the real secret private.

### Conclusion

The raw API-key getter violates least privilege. Remove it from the public provider contract.

---

## 2.4 Arbitrary base URL and bearer-token exfiltration

Both providers accept a configurable base URL:

```php
$resolvedBaseUrl = rtrim($this->baseUrl, '/');
```

No validation occurs.

An attacker who can modify the AI option or an administrator who enters a malicious endpoint could cause the plugin to send:

```http
Authorization: Bearer <Experiential key>
```

to an arbitrary host.

Examples include:

```text
https://attacker.example/v1
http://internal-service.local/v1
http://169.254.169.254/
```

This creates:

- credential exfiltration;
- SSRF;
- internal network access;
- metadata-service exposure;
- DNS rebinding risk;
- unexpected data disclosure.

For Experiential specifically, use an allowlist:

```php
https://api.experientiallabs.ai/v1
```

If custom gateway URLs are required, validate:

- scheme must be `https`;
- host must be explicitly allowlisted;
- no userinfo;
- no query or fragment;
- no nonstandard port unless approved;
- no IP literals;
- resolve and block private/link-local addresses;
- disable redirects or validate redirect destinations;
- do not forward bearer credentials across redirects.

Also distinguish OpenAI and Experiential URL handling. `OpenAIProvider` should not infer provider identity from whether a URL contains a substring.

### Conclusion

This is a high-severity security issue. Never send a bearer token to an arbitrary configured URL without strict endpoint validation.

---

## 2.5 Key leakage via model or API-key heuristics

`OpenAIProvider` decides provider identity using:

```php
str_starts_with($this->apiKey, 'xpl_')
```

This is fragile:

- key prefixes are not a security boundary;
- a custom Experiential key may not use `xpl_`;
- an OpenAI-compatible key could coincidentally use the prefix;
- key format changes will silently change routing;
- routing is now coupled to secret syntax.

Provider selection must be explicit, not inferred from credentials.

### Conclusion

Never infer provider identity from secret prefixes. Use explicit provider configuration and endpoint selection.

---

# 3. Dynamic model switching and budget resilience

## 3.1 Arbitrary model slugs

`getExperiential()` can construct a provider for any string:

```php
return $this->create('experiential', $key, $model, ...)
```

That technically permits arbitrary model slugs, but does not make them safe or valid.

The implementation does not:

- verify the model exists;
- verify it is active;
- verify it is enabled for the account;
- verify its capabilities;
- verify its context window;
- verify it supports tools;
- verify it supports vision;
- verify its pricing;
- verify it is allowed by business policy;
- prevent model strings containing unexpected control characters;
- normalize aliases;
- provide fallback if the model is unavailable.

A dynamic catalog should not be treated as a raw string list. It should be cached, validated, and policy-filtered.

Suggested flow:

```text
requested model
   ↓
canonicalize slug
   ↓
lookup cached catalog
   ↓
verify allowed + active + capability
   ↓
select budget policy
   ↓
construct request
   ↓
fallback only according to explicit policy
```

### Conclusion

Arbitrary routing is mechanically possible but operationally unsafe without catalog validation and capability metadata.

---

## 3.2 `getForModel()` has incomplete routing logic

The current logic routes to Experiential only if:

```php
$provider === 'experiential'
|| $model === 'gpt-5.6-luna'
|| str_starts_with($model, 'gpt-5.6-')
```

This means:

- `qwen3.8-27b` does not route automatically by model name;
- `claude-sonnet-4.5` does not route automatically;
- `deepseek-v4-flash` does not route automatically;
- any arbitrary catalog model requires the caller to pass `provider='experiential'`.

That is inconsistent with the stated goal of routing any of the 318+ catalog models.

The code comments say:

```php
Get or build a provider instance specifically for a given model ID.
```

but the method is not a general model resolver.

A correct method should either:

```php
getForModel(string $model, ?string $provider = null)
```

resolve all registered models from a model registry, or reject unknown models explicitly.

### Conclusion

Dynamic switching is only partially implemented. Model IDs outside the hard-coded `gpt-5.6-*` family do not route automatically.

---

## 3.3 Inconsistent OpenAI/Experiential model routing

There are three different ways to represent Experiential:

1. `ExperientialProvider`
2. `OpenAIProvider` pointed at Experiential
3. registry ID `openai` with special handling for `gpt-5.6-luna`

This causes identity and behavior problems.

For example:

```php
$provider->getId()
```

returns:

```text
openai
```

even when the actual endpoint is Experiential Labs.

This can break:

- billing attribution;
- provider quotas;
- default provider selection;
- logging;
- analytics;
- retry policies;
- model capability checks;
- admin UI labels;
- rate-limit buckets.

The registry cache key also uses:

```php
$id . ':' . $model
```

It does not include the base URL or credential identity. If the same provider/model is requested with a different custom base URL, the first instance wins.

The cache key should include a stable endpoint identity, but never the raw secret:

```text
provider:model:normalized-host:configuration-version
```

### Conclusion

The dual-provider design creates ambiguous provider identity and cache collisions. Experiential must be represented by one provider ID and one implementation.

---

## 3.4 Configuration enabled-state inconsistency

The default configuration sets:

```php
'experiential' => [
    'enabled' => false,
    ...
]
```

`ImageAnalysisService` calls:

```php
$this->registry->get('experiential')
```

If the setting is not explicitly enabled, it receives `null`, even if `EXPLABS_API_KEY` is present.

That may be intended, but it conflicts with the apparent goal of environment-driven activation. The service’s fallback logic is therefore dependent on a WordPress option, not merely on credential availability.

Likewise, `getForModel()` and `
