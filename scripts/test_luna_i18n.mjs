import fs from 'fs';
import os from 'os';
import path from 'path';

function loadVaultKey(keyName) {
  if (process.env[keyName]) return process.env[keyName].trim();
  const vaultPath = path.join(os.homedir(), '.config', 'antigravity', 'ai_mesh.env');
  if (fs.existsSync(vaultPath)) {
    const lines = fs.readFileSync(vaultPath, 'utf8').split('\n');
    for (const line of lines) {
      const trimmed = line.trim();
      if (trimmed.startsWith(`${keyName}=`)) {
        return trimmed.slice(`${keyName}=`.length).trim();
      }
    }
  }
  return '';
}

const EXPERIENTIAL_URL = "https://api.experientiallabs.ai/v1/chat/completions";
const EXPERIENTIAL_KEY = loadVaultKey("EXPLABS_API_KEY");

async function testLuna() {
  const sample = [
    "Accessories Tracked",
    "Add to Comparison",
    "Airflow performance rating",
    "Anti-fog visor insert",
    "Any Riding Style"
  ];

  const prompt = `You are a professional localization engine for Helmetsan, the global motorcycle helmet & gear intelligence platform.
Translate the following English UI strings into 9 languages:
- de: German
- es: Spanish (Spain/LatAm standard)
- fr: French
- it: Italian
- ja: Japanese
- nl: Dutch
- pl: Polish
- pt: Portuguese (Brazil/Portugal neutral)
- zh: Simplified Chinese

Input strings:
${JSON.stringify(sample, null, 2)}

Respond ONLY with valid raw JSON mapping each language code to an object mapping the original English string to its translation. Format:
{
  "de": { "string": "translation", ... },
  "es": { ... },
  ...
}`;

  console.log("Calling Luna for test batch...");
  const res = await fetch(EXPERIENTIAL_URL, {
    method: "POST",
    headers: {
      "Content-Type": "application/json",
      "Authorization": `Bearer ${EXPERIENTIAL_KEY}`
    },
    body: JSON.stringify({
      model: "gpt-6-luna",
      messages: [{ role: "user", content: prompt }],
      temperature: 0.1,
      response_format: { type: "json_object" }
    })
  });

  const data = await res.json();
  const raw = data.choices[0].message.content;
  console.log("Result:", raw);
}

testLuna().catch(console.error);
