/**
 * Helmetsan Deterministic Quality Sentinel & Linguistic Linter
 * Implements Section 9 of the GPT-5.6-Luna Strategy.
 * Inspects all editorial copy for grammar defects, ergonomic contradictions,
 * synthetic weight assertions, and anti-formulaic distinctiveness.
 */

export class QualitySentinel {
  constructor(options = {}) {
    this.options = {
      minLength: 60,
      maxLength: 2500,
      strictErgonomics: true,
      prohibitSyntheticWeights: true,
      ...options
    };

    // Glitch patterns identified in audit
    this.grammarGlitchRegex = /\bis\s+(delivers|combines|offers|features|provides|uses|boasts|pumps|channels)\b/i;
    this.duplicateVerbRegex = /\b(is|are|was|were)\s+\1\b/i;
    this.tautologyOpenerRegex = /^engineered for riders.*is engineered for/i;

    // Superbike ergonomic contradictions
    this.superbikeRelaxedErgonomicsRegex = /\b(fatigue[- ]free|relaxed ergonomics|ideal for city commuting|effortless city commuting|comfortable all-day commuting)\b/i;

    // Cliché phrases across catalog
    this.prohibitedSuperlatives = /\b(best-in-class|perfect for every rider|flawless machine|unbeatable in every way)\b/i;
  }

  /**
   * Inspects an item candidate and returns a comprehensive diagnostic report.
   */
  inspect(item, category = "unknown") {
    const issues = [];
    const overview = item.editorial_overview || item.description || "";

    // 1. Completeness & Length
    if (!overview || typeof overview !== "string" || overview.trim().length === 0) {
      issues.push({
        code: "MISSING_EDITORIAL_OVERVIEW",
        severity: "critical",
        message: "Item is missing an editorial_overview field."
      });
      return { passed: false, issues, grounding_score: 0, distinctiveness_score: 0 };
    }

    const cleanText = overview.replace(/<[^>]*>/g, "").trim();

    if (cleanText.length < this.options.minLength) {
      issues.push({
        code: "EDITORIAL_OVERVIEW_TOO_SHORT",
        severity: "critical",
        message: `Overview is too short (${cleanText.length} chars, expected >= ${this.options.minLength}).`
      });
    }

    if (cleanText.length > this.options.maxLength) {
      issues.push({
        code: "EDITORIAL_OVERVIEW_TOO_LONG",
        severity: "warning",
        message: `Overview exceeds maximum recommended length (${cleanText.length} chars).`
      });
    }

    // 2. Deterministic Grammar & Glitch Gates
    const grammarMatch = cleanText.match(this.grammarGlitchRegex);
    if (grammarMatch) {
      issues.push({
        code: "GRAMMAR_VERB_GLITCH",
        severity: "critical",
        match: grammarMatch[0],
        message: `Prohibited construct found: "${grammarMatch[0]}" (interpolated 3rd-person verb after 'is').`
      });
    }

    const dupMatch = cleanText.match(this.duplicateVerbRegex);
    if (dupMatch) {
      issues.push({
        code: "DUPLICATE_AUXILIARY_VERB",
        severity: "critical",
        match: dupMatch[0],
        message: `Duplicate auxiliary verb found: "${dupMatch[0]}".`
      });
    }

    if (this.tautologyOpenerRegex.test(cleanText)) {
      issues.push({
        code: "TAUTOLOGICAL_OPENER",
        severity: "high",
        message: "Tautological opener detected ('Engineered for... is engineered for...')."
      });
    }

    // 3. Ergonomic Sanity Gate (Motorcycles)
    if (category === "motorcycles" || category === "motorcycle") {
      const catStr = (item.category || item.type || "").toLowerCase();
      const ridingPos = (item.riding_position || "").toLowerCase();
      const isSuperbike = catStr.includes("superbike") || catStr.includes("supersport") || 
                          ridingPos.includes("aggressive forward tuck") || ridingPos.includes("forward tuck");

      if (isSuperbike && this.superbikeRelaxedErgonomicsRegex.test(cleanText)) {
        const match = cleanText.match(this.superbikeRelaxedErgonomicsRegex)[0];
        issues.push({
          code: "SUPERBIKE_ERGONOMIC_CONTRADICTION",
          severity: "critical",
          match,
          message: `Ergonomic contradiction: Superbike/forward-tuck machine claims "${match}".`
        });
      }

      // 4. Synthetic Weight Assertion Gate
      const cc = Number(item.displacement_cc || 0);
      const curbWeight = Number(item.curb_weight_kg || 0);
      if (this.options.prohibitSyntheticWeights && [108, 110].includes(curbWeight) && cc > 250) {
        // Check if text asserts this synthetic weight as verified
        const weightAssertRegex = new RegExp(`\\b${curbWeight}\\s?kg\\b`, "i");
        if (weightAssertRegex.test(cleanText)) {
          issues.push({
            code: "SYNTHETIC_WEIGHT_ASSERTION",
            severity: "high",
            message: `Asserts synthetic default weight (${curbWeight} kg) on ${cc}cc motorcycle in prose.`
          });
        }
      }
    }

    // 5. Tone & Superlative Restraint
    const superlativeMatch = cleanText.match(this.prohibitedSuperlatives);
    if (superlativeMatch) {
      issues.push({
        code: "UNSUPPORTED_SUPERLATIVE",
        severity: "medium",
        match: superlativeMatch[0],
        message: `Unsubstantiated marketing claim detected: "${superlativeMatch[0]}".`
      });
    }

    const criticalCount = issues.filter(i => i.severity === "critical").length;
    const passed = criticalCount === 0;

    return {
      passed,
      issues,
      criticalCount,
      warningCount: issues.filter(i => i.severity !== "critical").length
    };
  }

  /**
   * Fast corpus-wide N-gram repetition check (detects clichés across category)
   */
  static analyzeCorpusNgrams(items, n = 6) {
    const ngramCounts = new Map();

    for (const item of items) {
      const text = (item.editorial_overview || item.description || "")
        .toLowerCase()
        .replace(/[^a-z0-9\s]/g, " ")
        .replace(/\s+/g, " ")
        .trim();
      
      const words = text.split(" ");
      if (words.length < n) continue;

      const seenInItem = new Set();
      for (let i = 0; i <= words.length - n; i++) {
        const gram = words.slice(i, i + n).join(" ");
        if (!seenInItem.has(gram)) {
          seenInItem.add(gram);
          ngramCounts.set(gram, (ngramCounts.get(gram) || 0) + 1);
        }
      }
    }

    // Return ngrams appearing in > 2% of the corpus
    const threshold = Math.max(5, Math.floor(items.length * 0.02));
    const repetitiveNgrams = [];
    for (const [gram, count] of ngramCounts.entries()) {
      if (count > threshold) {
        repetitiveNgrams.push({ gram, count, percentage: ((count / items.length) * 100).toFixed(1) });
      }
    }

    return repetitiveNgrams.sort((a, b) => b.count - a.count);
  }
}
