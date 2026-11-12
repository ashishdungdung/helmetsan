import { secrets } from './config.mjs';

const p = secrets.providers;

function extractJson(text) {
  if (typeof text === 'object' && text !== null) return text;
  let str = String(text || '').trim();
  if (str.startsWith('```json')) str = str.replace(/^```json\s*/, '').replace(/\s*```$/, '');
  else if (str.startsWith('```')) str = str.replace(/^```\s*/, '').replace(/\s*```$/, '');

  const start = str.indexOf('{');
  const end = str.lastIndexOf('}');
  if (start !== -1 && end !== -1 && end > start) {
    const candidate = str.substring(start, end + 1);
    try {
      return JSON.parse(candidate);
    } catch {
      // Sanitize unescaped raw newlines in string properties
      const sanitized = candidate.replace(/[\r\n]+/g, " ");
      return JSON.parse(sanitized);
    }
  }
  return JSON.parse(str);
}

async function sleep(ms) {
  return new Promise(resolve => setTimeout(resolve, ms));
}

export async function callGroq(messages, jsonFormat = true) {
  const provider = p.groq;
  const body = {
    model: 'qwen/qwen3.8-27b',
    messages,
    temperature: 0.1,
    max_tokens: 4000
  };
  if (jsonFormat) body.response_format = { type: "json_object" };

  const res = await fetch(provider.base_url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${provider.api_key}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(body)
  });

  const data = await res.json();
  if (!res.ok) {
    throw new Error(`Groq HTTP ${res.status}: ${JSON.stringify(data)}`);
  }
  return extractJson(data.choices[0].message.content);
}

export async function callCerebras(messages) {
  const provider = p.cerebras;
  const res = await fetch(provider.base_url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${provider.api_key}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      model: 'gpt-oss-120b',
      messages,
      temperature: 0.1,
      max_tokens: 4000
    })
  });

  const data = await res.json();
  if (!res.ok) {
    throw new Error(`Cerebras HTTP ${res.status}: ${JSON.stringify(data)}`);
  }
  return extractJson(data.choices[0].message.content);
}

export async function callMistral(messages, jsonFormat = true) {
  const provider = p.mistral;
  const body = {
    model: 'ministral-8b-latest',
    messages,
    temperature: 0.1,
    max_tokens: 4000
  };
  if (jsonFormat) body.response_format = { type: "json_object" };

  const res = await fetch(provider.base_url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${provider.api_key}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(body)
  });

  const data = await res.json();
  if (!res.ok) {
    throw new Error(`Mistral HTTP ${res.status}: ${JSON.stringify(data)}`);
  }
  return extractJson(data.choices[0].message.content);
}

export async function callCloudflare(messages) {
  const provider = p.cloudflare;
  const url = `https://api.cloudflare.com/client/v4/accounts/${provider.account_id}/ai/run/${provider.default_model}`;
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${provider.api_token}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      messages,
      max_tokens: 2500
    })
  });

  const data = await res.json();
  if (!res.ok || !data.success) {
    throw new Error(`Cloudflare HTTP ${res.status}: ${JSON.stringify(data)}`);
  }
  const rawText = data.result?.response || data.result?.choices?.[0]?.message?.content;
  return extractJson(rawText);
}

export async function callGemini(systemPrompt, userPrompt) {
  const provider = p.gemini;
  const model = 'gemini-3.1-flash-lite';
  const url = `${provider.base_url}/${model}:generateContent?key=${provider.api_key}`;

  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      contents: [
        {
          role: 'user',
          parts: [{ text: `${systemPrompt}\n\n${userPrompt}` }]
        }
      ],
      generationConfig: {
        responseMimeType: "application/json",
        temperature: 0.1,
        maxOutputTokens: 6000
      }
    })
  });

  const data = await res.json();
  if (!res.ok) {
    throw new Error(`Gemini HTTP ${res.status}: ${JSON.stringify(data)}`);
  }
  const rawText = data.candidates[0].content.parts[0].text;
  return extractJson(rawText);
}

export async function callLuna(messages, jsonFormat = true) {
  const provider = p.experiential_luna;
  if (!provider || !provider.api_key) {
    throw new Error('Experiential Luna provider not configured');
  }
  const body = {
    model: 'gpt-6-luna',
    messages,
    temperature: 0.2,
    max_tokens: 4000
  };
  if (jsonFormat) body.response_format = { type: "json_object" };

  const res = await fetch(provider.base_url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${provider.api_key}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(body)
  });

  const data = await res.json();
  if (!res.ok) {
    throw new Error(`GPT Luna HTTP ${res.status}: ${JSON.stringify(data)}`);
  }
  return extractJson(data.choices[0].message.content);
}

export async function callDeepseek(messages, jsonFormat = true) {
  const provider = p.experiential_deepseek || p.experiential_luna;
  if (!provider || !provider.api_key) {
    throw new Error('Experiential DeepSeek provider not configured');
  }
  const body = {
    model: 'deepseek-v4.1-flash',
    messages,
    temperature: 0.2,
    max_tokens: 4000
  };
  if (jsonFormat) body.response_format = { type: "json_object" };

  const res = await fetch(provider.base_url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${provider.api_key}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(body)
  });

  const data = await res.json();
  if (!res.ok) {
    throw new Error(`DeepSeek HTTP ${res.status}: ${JSON.stringify(data)}`);
  }
  const content = data.choices[0]?.message?.content || data.choices[0]?.message?.reasoning_content;
  return extractJson(content);
}

export async function callMercury(messages, jsonFormat = true) {
  const provider = p.inception_mercury;
  if (!provider || !provider.api_key) {
    throw new Error('Inception Mercury provider not configured');
  }
  const body = {
    model: provider.default_model || 'mercury-3',
    messages,
    temperature: 0.5,
    max_completion_tokens: 3000
  };
  if (jsonFormat) body.response_format = { type: "json_object" };

  const res = await fetch(provider.base_url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${provider.api_key}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(body)
  });

  const data = await res.json();
  if (!res.ok) {
    throw new Error(`Mercury HTTP ${res.status}: ${JSON.stringify(data)}`);
  }
  return extractJson(data.choices[0].message.content);
}

export async function translateWithFleet(systemPrompt, payload, targetLang, preferredOrder = ['groq', 'cerebras', 'mistral', 'cloudflare']) {
  const userPrompt = `Target Language: '${targetLang}'. Translate the following JSON content accurately into ${targetLang}. Return ONLY the translated JSON matching schema:\n${JSON.stringify(payload)}`;
  const messages = [
    { role: 'system', content: systemPrompt },
    { role: 'user', content: userPrompt }
  ];

  let lastError = null;

  for (const prov of preferredOrder) {
    for (let attempt = 1; attempt <= 2; attempt++) {
      try {
        const start = Date.now();
        let result = null;
        if (prov === 'groq') result = await callGroq(messages);
        else if (prov === 'cerebras') result = await callCerebras(messages);
        else if (prov === 'mistral') result = await callMistral(messages);
        else if (prov === 'cloudflare') result = await callCloudflare(messages);
        else if (prov === 'gemini') result = await callGemini(systemPrompt, userPrompt);
        else if (prov === 'luna') result = await callLuna(messages);
        else if (prov === 'deepseek') result = await callDeepseek(messages);
        else if (prov === 'mercury') result = await callMercury(messages);

        const latency = Date.now() - start;
        return { success: true, provider: prov, latency_ms: latency, data: result };
      } catch (err) {
        lastError = err;
        const msg = err.message || '';
        // If rate limit or temporary server error, back off briefly
        if (msg.includes('429') || msg.includes('503') || msg.includes('timeout')) {
          await sleep(1500 * attempt);
        } else {
          // If non-recoverable error for this provider, move to next provider
          break;
        }
      }
    }
  }

  throw new Error(`All fleet providers failed for ${targetLang}. Last error: ${lastError?.message}`);
}
