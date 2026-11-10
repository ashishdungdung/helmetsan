import fs from 'fs';
import path from 'path';

function loadConfig() {
  const paths = [
    '/var/www/helmetsan.com/secrets/ai_translation_config.json',
    path.resolve(process.cwd(), 'HelmetsanWeb/secrets/ai_translation_config.json'),
    path.resolve(process.cwd(), 'secrets/ai_translation_config.json'),
    path.resolve(process.cwd(), '../secrets/ai_translation_config.json')
  ];

  for (const p of paths) {
    if (fs.existsSync(p)) {
      return JSON.parse(fs.readFileSync(p, 'utf-8'));
    }
  }
  return { providers: {} };
}

let config = loadConfig();

/**
 * Robust Self-Healing JSON Extractor
 * Automatically handles unescaped quotes, trailing commas, raw newlines, and truncated responses.
 */
export function extractJson(text) {
  if (typeof text === 'object' && text !== null) return text;
  let str = String(text || '').trim();
  if (str.startsWith('```json')) str = str.replace(/^```json\s*/, '').replace(/\s*```$/, '');
  else if (str.startsWith('```')) str = str.replace(/^```\s*/, '').replace(/\s*```$/, '');

  const start = str.indexOf('{');
  let end = str.lastIndexOf('}');
  
  if (start !== -1) {
    if (end === -1 || end < start) {
      str = str.substring(start) + '"}';
      end = str.lastIndexOf('}');
    }
    const candidate = str.substring(start, end + 1);
    try {
      return JSON.parse(candidate);
    } catch (e1) {
      // 1. Try normalizing unescaped newlines and trailing commas
      let sanitized = candidate.replace(/\r?\n|\r/g, '\\n')
                               .replace(/,\s*([}\]])/g, '$1');
      try {
        return JSON.parse(sanitized);
      } catch (e2) {
        // 2. Clean control characters
        let cleanControl = candidate.replace(/[\x00-\x1F\x7F-\x9F]/g, ' ')
                                    .replace(/,\s*([}\]])/g, '$1');
        try {
          return JSON.parse(cleanControl);
        } catch (e3) {
          // 3. Fallback: regex extraction for key fields
          const titleMatch = candidate.match(/"title"\s*:\s*"((?:[^"\\]|\\.)*)"/i);
          const excerptMatch = candidate.match(/"excerpt"\s*:\s*"((?:[^"\\]|\\.)*)"/i);
          const contentMatch = candidate.match(/"content"\s*:\s*"([\s\S]*?)(?:"\s*,\s*"|"\s*})/i);
          if (titleMatch || contentMatch) {
            return {
              title: titleMatch ? JSON.parse(`"${titleMatch[1]}"`) : '',
              content: contentMatch ? contentMatch[1].replace(/\\"/g, '"').replace(/\\n/g, "\n") : '',
              excerpt: excerptMatch ? JSON.parse(`"${excerptMatch[1]}"`) : ''
            };
          }
        }
      }
    }
  }
  return JSON.parse(str);
}

export async function callMercury(messages, jsonFormat = true) {
  const p = config.providers.mercury || config.providers.inception_mercury;
  if (!p || !p.api_key) throw new Error('Mercury provider not configured');

  const body = {
    model: p.default_model || p.model || 'mercury-3',
    messages,
    temperature: 0.5,
    max_completion_tokens: 16000
  };
  if (jsonFormat) body.response_format = { type: 'json_object' };

  const res = await fetch(p.base_url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${p.api_key}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(body)
  });

  const data = await res.json();
  if (!res.ok) throw new Error(`Mercury HTTP ${res.status}: ${JSON.stringify(data)}`);
  return extractJson(data.choices[0].message.content);
}

export async function callGemini(systemPrompt, userPrompt) {
  const p = config.providers.gemini;
  if (!p || !p.api_key) throw new Error('Gemini provider not configured');

  const model = p.default_model || p.model || 'gemini-3.1-flash-lite';
  const url = `${p.base_url}/${model}:generateContent?key=${p.api_key}`;

  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      contents: [{ role: 'user', parts: [{ text: `${systemPrompt}\n\n${userPrompt}` }] }],
      generationConfig: {
        responseMimeType: 'application/json',
        temperature: 0.1,
        maxOutputTokens: 8192
      }
    })
  });

  const data = await res.json();
  if (!res.ok) throw new Error(`Gemini HTTP ${res.status}: ${JSON.stringify(data)}`);
  return extractJson(data.candidates[0].content.parts[0].text);
}

export async function callLuna(messages, jsonFormat = true) {
  const p = config.providers.luna || config.providers.experiential_luna;
  if (!p || !p.api_key) throw new Error('GPT-6-Luna provider not configured');

  const body = {
    model: p.default_model || p.model || 'gpt-6-luna',
    messages,
    temperature: 0.2,
    max_tokens: 8192
  };
  if (jsonFormat) body.response_format = { type: 'json_object' };

  const res = await fetch(p.base_url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${p.api_key}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(body)
  });

  const data = await res.json();
  if (!res.ok) throw new Error(`GPT Luna HTTP ${res.status}: ${JSON.stringify(data)}`);
  return extractJson(data.choices[0].message.content);
}

export async function callCerebras(messages) {
  const p = config.providers.cerebras;
  if (!p || !p.api_key) throw new Error('Cerebras provider not configured');

  const res = await fetch(p.base_url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${p.api_key}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({
      model: p.default_model || p.model || 'gpt-oss-120b',
      messages,
      temperature: 0.1,
      max_tokens: 8192
    })
  });

  const data = await res.json();
  if (!res.ok) throw new Error(`Cerebras HTTP ${res.status}: ${JSON.stringify(data)}`);
  return extractJson(data.choices[0].message.content);
}

export async function callGroq(messages, jsonFormat = true) {
  const p = config.providers.groq;
  if (!p || !p.api_key) throw new Error('Groq provider not configured');

  const body = {
    model: p.default_model || p.model || 'qwen/qwen3.8-27b',
    messages,
    temperature: 0.1,
    max_tokens: 8192
  };
  if (jsonFormat) body.response_format = { type: 'json_object' };

  const res = await fetch(p.base_url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${p.api_key}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(body)
  });

  const data = await res.json();
  if (!res.ok) throw new Error(`Groq HTTP ${res.status}: ${JSON.stringify(data)}`);
  return extractJson(data.choices[0].message.content);
}

export async function callMistral(messages, jsonFormat = true) {
  const p = config.providers.mistral;
  if (!p || !p.api_key) throw new Error('Mistral provider not configured');

  const body = {
    model: p.default_model || p.model || 'ministral-8b-latest',
    messages,
    temperature: 0.1,
    max_tokens: 8192
  };
  if (jsonFormat) body.response_format = { type: 'json_object' };

  const res = await fetch(p.base_url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${p.api_key}`,
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(body)
  });

  const data = await res.json();
  if (!res.ok) throw new Error(`Mistral HTTP ${res.status}: ${JSON.stringify(data)}`);
  return extractJson(data.choices[0].message.content);
}

export async function translateWithFleet(systemPrompt, payload, targetLang, preferredOrder = ['mercury', 'cerebras', 'groq', 'gemini', 'mistral', 'luna']) {
  const userPrompt = `Target Language: '${targetLang}'. Translate the following JSON content accurately into ${targetLang}. Return ONLY valid JSON matching this schema:\n${JSON.stringify(payload)}`;
  const messages = [
    { role: 'system', content: systemPrompt },
    { role: 'user', content: userPrompt }
  ];

  let lastError = null;

  for (const prov of preferredOrder) {
    try {
      const start = Date.now();
      let result = null;
      if (prov === 'mercury') result = await callMercury(messages);
      else if (prov === 'cerebras') result = await callCerebras(messages);
      else if (prov === 'groq') result = await callGroq(messages);
      else if (prov === 'gemini') result = await callGemini(systemPrompt, userPrompt);
      else if (prov === 'mistral') result = await callMistral(messages);
      else if (prov === 'luna') result = await callLuna(messages);

      const latency = Date.now() - start;
      return { success: true, provider: prov, latency_ms: latency, data: result };
    } catch (err) {
      lastError = err;
      continue;
    }
  }

  throw new Error(`All fleet providers failed for ${targetLang}. Last error: ${lastError?.message}`);
}
