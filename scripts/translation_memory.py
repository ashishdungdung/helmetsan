#!/usr/bin/env python3
"""
=============================================================================
         HELMETSAN TRANSLATION MEMORY (TM) & TERMINOLOGY ENGINE
=============================================================================
Provides zero-token deterministic translation for recurring motorcycle gear
phrases, features, and standardized specifications.
"""

import os
import json
import re

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
DATA_DIR = os.path.join(os.path.dirname(SCRIPT_DIR), "data")
TM_FILE = os.path.join(DATA_DIR, "translation_memory.json")

class TranslationMemory:
    def __init__(self, tm_path=TM_FILE):
        self.tm_path = tm_path
        self.memory = []
        self.lookup = {} # normalized_en -> dict of lang translations
        self.stats = {
            "tm_hits": 0,
            "tokens_saved": 0
        }
        self.load()

    def _normalize(self, text):
        if not text:
            return ""
        # Lowercase, strip punctuation and whitespace
        s = text.lower().strip()
        s = re.sub(r"[^\w\s]", "", s)
        s = re.sub(r"\s+", " ", s)
        return s

    def load(self):
        if not os.path.exists(self.tm_path):
            return
        try:
            with open(self.tm_path, "r", encoding="utf-8") as f:
                data = json.load(f)
                self.memory = data.get("memory", [])
                for item in self.memory:
                    en = item.get("en", "")
                    if en:
                        norm = self._normalize(en)
                        self.lookup[norm] = item
        except Exception as e:
            print(f"⚠️ Failed to load Translation Memory from {self.tm_path}: {e}")

    def get_exact_match(self, en_text, lang):
        norm = self._normalize(en_text)
        if norm in self.lookup:
            trans = self.lookup[norm].get(lang)
            if trans:
                self.stats["tm_hits"] += 1
                # Standard motorcycle bullet is ~15-20 input tokens + ~15 output tokens
                self.stats["tokens_saved"] += 35
                return trans
        return None

    def partition_features(self, features, lang):
        """
        Partition feature list into TM pre-translated items and novel items
        that must be sent to the LLM.
        Returns:
            (resolved_map, novel_indices, novel_features)
            - resolved_map: dict of idx -> translated_text (from TM)
            - novel_indices: list of original indices for items needing LLM
            - novel_features: list of English texts needing LLM
        """
        if not isinstance(features, list) or not features:
            return {}, [], []

        resolved_map = {}
        novel_indices = []
        novel_features = []

        for idx, feat in enumerate(features):
            if not isinstance(feat, str) or not feat.strip():
                continue
            matched = self.get_exact_match(feat, lang)
            if matched:
                resolved_map[idx] = matched
            else:
                resolved_map[idx] = None
                novel_indices.append(idx)
                novel_features.append(feat)

        return resolved_map, novel_indices, novel_features

    def merge_features(self, resolved_map, novel_indices, novel_translations, original_features=None):
        """
        Merge TM-translated features with LLM-translated novel features,
        preserving original sequence order. Falls back to original feature text
        if the LLM returned fewer items than expected (prevents silent feature drop).
        """
        merged = []
        novel_iter = iter(novel_translations) if novel_translations else iter([])

        for idx in range(len(resolved_map)):
            if resolved_map.get(idx) is not None:
                merged.append(resolved_map[idx])
            else:
                try:
                    merged.append(next(novel_iter))
                except StopIteration:
                    fallback = ""
                    if original_features and idx < len(original_features):
                        fallback = str(original_features[idx])
                    if fallback:
                        merged.append(fallback)
                        print(f"⚠️ TM warning: LLM dropped feature at index {idx}. Fell back to original text.")

        return merged

# Global Singleton instance
_tm_instance = None
def get_translation_memory():
    global _tm_instance
    if _tm_instance is None:
        _tm_instance = TranslationMemory()
    return _tm_instance
