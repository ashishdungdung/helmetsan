<?php
/**
 * local_config.example.php — Copy this to local_config.php and fill in your values.
 *
 * IMPORTANT: local_config.php is gitignored (contains Node B credentials).
 * This example file IS committed and serves as the setup template.
 *
 * Setup:
 *   cp scripts/local_config.example.php scripts/local_config.php
 *   # Then edit local_config.php with your actual values
 *
 * See: .agents/rules/02-local-ai-protocol.md
 */
return [
    'cluster_name' => 'SiliconComputeGrid',

    // -----------------------------------------------------------------------
    // Node A — Primary enrichment node (this machine)
    // -----------------------------------------------------------------------
    'node_a' => [
        'name'        => 'Node A - M4 Pro (Master)',
        'base_url'    => 'http://127.0.0.1:1234/v1',     // LM Studio default port

        // Deep semantic extraction: certs, safety, specs, market analysis.
        // gemma-4-12b-qat uses ~280 reasoning tokens internally.
        // max_tokens MUST be >= 500 or output will be empty (finish_reason: length).
        'deep_model'  => 'google/gemma-4-12b-qat',
        'max_tokens'  => 1500,
        'concurrency' => 2,  // M4 Pro safe limit

        // Fast classification tasks (tags, short meta, category sorting).
        'fast_model'  => 'google/gemma-3-4b',
    ],

    // -----------------------------------------------------------------------
    // Node B — Secondary worker node (optional, another machine on LAN)
    // Fill in if you have a second machine running LM Studio.
    // -----------------------------------------------------------------------
    'node_b' => [
        'name'     => 'Node B - Worker',
        'base_url' => 'http://192.168.x.x:1235/v1',   // Change to your LAN IP
        'api_key'  => 'YOUR_NODE_B_API_KEY_HERE',
        'model'    => 'YOUR_MODEL_HERE',
    ],

    // -----------------------------------------------------------------------
    // Legacy compatibility — used by older scripts that read lm_studio_model.
    // Keep this pointing to the deep model (not fast) to prevent bulk runs
    // accidentally using a model too small for certification reasoning.
    // -----------------------------------------------------------------------
    'lm_studio_base_url' => 'http://127.0.0.1:1234/v1',
    'lm_studio_model'    => 'google/gemma-4-12b-qat',
    'max_tokens'         => 1500,
    'concurrency'        => 2,
];
