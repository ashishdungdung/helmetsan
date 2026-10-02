#!/usr/bin/env python3
"""
=============================================================================
         HELMETSAN DETERMINISTIC TOKEN & SAFETY GUARDRAIL ENGINE
=============================================================================
Protects helmet safety certifications (ECE 22.06, DOT FMVSS 218, Snell, FIM),
proprietary engineering technologies, and brand names from model hallucination
or translation drift via deterministic placeholder substitution.
"""

import re
from typing import Dict, List, Tuple

# Pre-compiled safety, standard, and technical token patterns
PROTECTED_PATTERNS = [
    # Safety Standards & Homologations
    r"\bECE\s*22\.06\b",
    r"\bECE\s*22\.05\b",
    r"\bDOT\s*FMVSS\s*(?:No\.?\s*)?218\b",
    r"\bDOT\s*certified\b",
    r"\bDOT\b",
    r"\bSnell\s*M2020[D|R]?\b",
    r"\bSnell\s*M2025\b",
    r"\bSnell\b",
    r"\bFIM\s*FRHPhe-0[12]\b",
    r"\bFIM\s*certified\b",
    r"\bSHARP\s*\d-Star\b",
    r"\bSHARP\b",
    r"\bAS/NZS\s*1698\b",
    r"\bJIS\s*T\s*8133\b",

    # Proprietary Safety Tech
    r"\bPinlock\s*(?:70|120)?(?:\s*MaxVision)?\b",
    r"\bMIPS(?:\s*safety\s*system)?\b",
    r"\bEmergency\s*Quick\s*Release\s*System\b",
    r"\bEQRS\b",
    r"\bDouble-?D(?:-?ring)?\b",
    r"\bMicrometric\s*(?:buckle|ratchet)?\b",
    r"\bAIM\+?\b",
    r"\bP\.I\.M\.\+?\b",
    r"\bSENA\b",
    r"\bCardo\b",
    r"\bBluetooth\s*5\.[0-4]\b",
    r"\bBluetooth\b",

    # Key Manufacturer Brands
    r"\bSHOEI\b",
    r"\bShoei\b",
    r"\bArai\b",
    r"\bAGV\b",
    r"\bHJC\b",
    r"\bSchuberth\b",
    r"\bShark\b",
    r"\bNolan\b",
    r"\bX-Lite\b",
    r"\bScorpion(?:EXO)?\b",
    r"\bBell\b",
    r"\bLS2\b",
    r"\bSuomy\b",
    r"\bKYT\b",
    r"\bNexx\b",
    r"\bCaberg\b",
    r"\bIcon\b",
    r"\bKlim\b",
    r"\bAlpinestars\b",
    r"\bFox\s*Racing\b",
    r"\bFly\s*Racing\b",
    r"\b6D\b",
    r"\bSMK\b",
    r"\bMT\s*Helmets\b",
    r"\bStudds\b",
    r"\bVega\b",
    r"\bSteelbird\b",
    r"\bAxor\b",
    r"\bRoyal\s*Enfield\b",
]

COMBINED_REGEX = re.compile("|".join(f"(?:{p})" for p in PROTECTED_PATTERNS), re.IGNORECASE)


class TokenMasker:
    """Deterministic token masking and reconstruction for multilingual translation."""

    @staticmethod
    def mask(text: str) -> Tuple[str, Dict[str, str]]:
        """
        Replaces all protected brand, standard, and technical terms with __TOKEN_XXX__
        Returns: (masked_text, token_map)
        """
        if not text or not isinstance(text, str):
            return text, {}

        token_map: Dict[str, str] = {}
        counter = 1

        def _replacer(match: re.Match) -> str:
            nonlocal counter
            original = match.group(0)
            token_key = f"__TOKEN_{counter:03d}__"
            token_map[token_key] = original
            counter += 1
            return token_key

        masked_text = COMBINED_REGEX.sub(_replacer, text)
        return masked_text, token_map

    @staticmethod
    def unmask(masked_text: str, token_map: Dict[str, str]) -> str:
        """Restores exact original tokens into the translated output."""
        if not masked_text or not token_map:
            return masked_text

        result = masked_text
        for token_key, original_val in token_map.items():
            result = result.replace(token_key, original_val)
            # Handle potential model lowercasing / spacing e.g. __token_001__ or __ TOKEN_001 __
            relaxed_token = token_key.lower()
            if relaxed_token in result:
                result = result.replace(relaxed_token, original_val)

        return result

    @staticmethod
    def validate_tokens_preserved(original_token_map: Dict[str, str], restored_text: str) -> Tuple[bool, List[str]]:
        """
        Verifies that every original protected term exists in the translated output.
        Returns: (is_valid, missing_tokens)
        """
        missing = []
        for _, orig_val in original_token_map.items():
            # Standard boundary match
            pattern = r"\b" + re.escape(orig_val) + r"\b"
            if not re.search(pattern, restored_text, re.IGNORECASE):
                missing.append(orig_val)

        return len(missing) == 0, missing


if __name__ == "__main__":
    # Test suite
    sample = "The Shoei X-SPR Pro features an AIM+ composite shell certified to ECE 22.06 and DOT FMVSS 218 with Pinlock 120 and Double-D ring."
    masked, t_map = TokenMasker.mask(sample)
    print("Original:", sample)
    print("Masked:  ", masked)
    print("Tokens:  ", t_map)

    # Simulated German translation with placeholders intact
    simulated_de = "Der __TOKEN_001__ __TOKEN_002__ verfügt über eine __TOKEN_003__ Verbundschale, zertifiziert nach __TOKEN_004__ und __TOKEN_005__ mit __TOKEN_006__ und __TOKEN_007__."
    restored = TokenMasker.unmask(simulated_de, t_map)
    print("Restored:", restored)

    valid, missing = TokenMasker.validate_tokens_preserved(t_map, restored)
    print(f"Validation: {'PASSED ✅' if valid else 'FAILED ❌'} (Missing: {missing})")
