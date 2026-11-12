import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Find secrets file in secrets/ai_translation_config.json
const secretsPath = path.resolve(__dirname, '../../secrets/ai_translation_config.json');
if (!fs.existsSync(secretsPath)) {
  throw new Error(`AI translation secrets file not found at ${secretsPath}`);
}

export const secrets = JSON.parse(fs.readFileSync(secretsPath, 'utf8'));

export const TARGET_LANGUAGES = [
  { code: 'de', name: 'German', native: 'Deutsch' },
  { code: 'fr', name: 'French', native: 'Français' },
  { code: 'es', name: 'Spanish', native: 'Español' },
  { code: 'it', name: 'Italian', native: 'Italiano' },
  { code: 'nl', name: 'Dutch', native: 'Nederlands' },
  { code: 'pl', name: 'Polish', native: 'Polski' },
  { code: 'pt', name: 'Portuguese', native: 'Português' },
  { code: 'ja', name: 'Japanese', native: '日本語' },
  { code: 'zh', name: 'Simplified Chinese', native: '简体中文' }
];

export const ACCESSORY_LANGUAGES = [
  { code: 'fr', name: 'French', native: 'Français' },
  { code: 'es', name: 'Spanish', native: 'Español' },
  { code: 'it', name: 'Italian', native: 'Italiano' },
  { code: 'nl', name: 'Dutch', native: 'Nederlands' },
  { code: 'pl', name: 'Polish', native: 'Polski' },
  { code: 'pt', name: 'Portuguese', native: 'Português' },
  { code: 'ja', name: 'Japanese', native: '日本語' }
];

export const SPECIALIZED_PAGE_LANGUAGES = [
  { code: 'fr', name: 'French', native: 'Français' },
  { code: 'es', name: 'Spanish', native: 'Español' },
  { code: 'it', name: 'Italian', native: 'Italiano' },
  { code: 'nl', name: 'Dutch', native: 'Nederlands' },
  { code: 'pl', name: 'Polish', native: 'Polski' },
  { code: 'pt', name: 'Portuguese', native: 'Português' },
  { code: 'ja', name: 'Japanese', native: '日本語' }
];

export const RATE_LIMITS = {
  groq: { rpm: 25, delay_ms: 2400 },
  cerebras: { rpm: 25, delay_ms: 2400 },
  mistral: { rpm: 40, delay_ms: 1500 },
  cloudflare: { rpm: 40, delay_ms: 1500 },
  gemini: { rpm: 12, delay_ms: 5000 },
  experiential_luna: { rpm: 30, delay_ms: 2000 },
  experiential_deepseek: { rpm: 40, delay_ms: 1500 },
  inception_mercury: { rpm: 60, delay_ms: 1000 }
};
