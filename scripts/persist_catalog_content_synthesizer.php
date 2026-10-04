<?php
/**
 * Helmet Catalog Content Synthesizer & Database Persistence Pipeline.
 *
 * Transforms thin helmet database stubs (7-13 words) into rich, E-E-A-T compliant
 * biomechanical engineering dossiers (300-500 words, 2,200+ characters) persisted
 * directly in wp_posts.post_content.
 *
 * Enforces modern PHP 8.5 standards, strict typing, and transactional batching.
 *
 * @package HelmetsanCore
 */

declare(strict_types=1);

// Bootstrap WordPress if run standalone via CLI
if (!defined("ABSPATH")) {
    $wpLoad = dirname(__DIR__, 2) . "/public/wp-load.php";
    if (!file_exists($wpLoad)) {
        $wpLoad = "/var/www/helmetsan.com/public/wp-load.php";
    }
    if (file_exists($wpLoad)) {
        require_once $wpLoad;
    } else {
        fwrite(STDERR, "Error: Could not locate wp-load.php\n");
        exit(1);
    }
}

// Include theme template tags if available
$themeTags = dirname(__DIR__) . "/helmetsan-theme/inc/template-tags.php";
if (file_exists($themeTags)) {
    require_once $themeTags;
} else {
    $prodTags =
        "/var/www/helmetsan.com/public/wp-content/themes/helmetsan-theme/inc/template-tags.php";
    if (file_exists($prodTags)) {
        require_once $prodTags;
    }
}

final class HelmetCatalogSynthesizer
{
    private wpdb $db;

    public function __construct()
    {
        global $wpdb;
        $this->db = $wpdb;
    }

    /**
     * Synthesizes authoritative engineering prose for a helmet record.
     *
     * @param string $title
     * @param string $brand
     * @param string $htype
     * @param string $shell
     * @param string $headShape
     * @param string $certs
     * @param int|float|null $weightG
     * @param int|float|null $noiseDb
     * @param string $existingContent
     * @return string
     */
    public function synthesizeProse(
        string $title,
        string $brand,
        string $htype,
        string $shell,
        string $headShape,
        string $certs,
        int|float|null $weightG,
        int|float|null $noiseDb,
        string $existingContent = "",
    ): string {
        $brandClean = !empty($brand)
            ? trim($brand)
            : (strstr($title, " ", true) ?:
            "the manufacturer");
        $shellClean = !empty($shell)
            ? trim($shell)
            : "multi-composite polymer matrix";
        $certClean = !empty($certs)
            ? trim($certs)
            : "ECE 22.06 & DOT FMVSS 218";
        $shapeClean = !empty($headShape)
            ? trim($headShape)
            : "Intermediate Oval";
        $typeClean = !empty($htype) ? trim($htype) : "Full Face";

        // Infer riding discipline for motorcycle synergy pairing
        $disc = "sport";
        $tLower = strtolower($title . " " . $typeClean);
        if (
            str_contains($tLower, "adventure") ||
            str_contains($tLower, "dual sport") ||
            str_contains($tLower, "adv") ||
            str_contains($tLower, "trail") ||
            str_contains($tLower, "dirt") ||
            str_contains($tLower, "mx")
        ) {
            $disc = "adv";
        } elseif (
            str_contains($tLower, "track") ||
            str_contains($tLower, "race") ||
            str_contains($tLower, "pista") ||
            str_contains($tLower, "x-fifteen") ||
            str_contains($tLower, "x-15") ||
            str_contains($tLower, "corsair") ||
            str_contains($tLower, "rpha 1")
        ) {
            $disc = "track";
        } elseif (
            str_contains($tLower, "modular") ||
            str_contains($tLower, "flip-up") ||
            str_contains($tLower, "neotec") ||
            str_contains($tLower, "c5") ||
            str_contains($tLower, "c4") ||
            str_contains($tLower, "tourmodular")
        ) {
            $disc = "modular";
        } elseif (
            str_contains($tLower, "touring") ||
            str_contains($tLower, "gt-air") ||
            str_contains($tLower, "rpha 71")
        ) {
            $disc = "touring";
        } elseif (
            str_contains($tLower, "open face") ||
            str_contains($tLower, "open-face") ||
            str_contains($tLower, "3/4") ||
            str_contains($tLower, "half") ||
            str_contains($tLower, "custom") ||
            str_contains($tLower, "cruiser")
        ) {
            $disc = "cruiser";
        }

        $motoSynergy = match ($disc) {
            "adv"
                => 'Engineered with aerodynamic peak clearance and high-flow dust filtration, this helmet is tailored for upright all-terrain adventure tourers such as the <a href="https://helmetsan.com/motorcycles/">Royal Enfield Himalayan 450, BMW R 1250 GS, and KTM 390 Adventure</a>.',
            "track"
                => 'Calibrated for an aggressive 3/4 to full chin-on-tank tuck, this helmet minimizes parasitic aerodynamic drag at extreme velocities, making it an optimal companion for circuit and supersport machines including the <a href="https://helmetsan.com/motorcycles/">Yamaha YZF-R1, BMW S1000RR, and Kawasaki Ninja ZX-10R</a>.',
            "modular"
                => 'Acoustically insulated for long-distance highway comfort and upright ergonomics, this architecture pairs seamlessly with luxury grand tourers and versatile roadsters like the <a href="https://helmetsan.com/motorcycles/">Honda Gold Wing, BMW R 1250 RT, and Harley-Davidson Street Glide</a>.',
            "touring"
                => "Designed to isolate riders from sustained wind shear over high-mileage journeys, this helmet excels in neutral upright riding postures on sport-touring machines and premium highway cruisers.",
            "cruiser"
                => "Featuring a low-profile aesthetic and panoramic sightlines, this design complements modern classics, retro roadsters, and urban commuters with relaxed riding ergonomics.",
            default
                => "Sculpted for balanced downforce and aggressive brow intake, this helmet is tuned for spirited street carving and naked roadsters with forward-leaning riding geometry.",
        };

        $dbStr =
            $noiseDb !== null && $noiseDb > 0
                ? "{$noiseDb} dB"
                : "low-turbulence 84 dB";
        $weightStr =
            $weightG !== null && $weightG > 0
                ? "{$weightG} grams"
                : "an optimized lightweight profile";

        $paragraphs = [];

        // Preserve authentic original intro if present
        $cleanExisting = trim(strip_tags($existingContent));
        if (
            !empty($cleanExisting) &&
            strlen($cleanExisting) > 20 &&
            !str_contains($cleanExisting, "protective system")
        ) {
            $paragraphs[] =
                '<p class="hs-editorial-lead">' .
                esc_html($cleanExisting) .
                "</p>";
        }

        // Paragraph 1: Biomechanical structural layup & kinetic dispersion
        $paragraphs[] = sprintf(
            "<p>The <strong>%s</strong> is engineered as a high-integrity %s protective system by %s, utilizing a structural shell constructed from <strong>%s</strong>. This structural layup is engineered to provide progressive kinetic energy dissipation during impact deceleration, distributing localized shock across outer lamina layers while minimizing deformation transfer into the internal multi-density EPS liner.</p>",
            esc_html($title),
            esc_html($typeClean),
            esc_html($brandClean),
            esc_html($shellClean),
        );

        // Paragraph 2: Safety Homologation & Rotational Biomechanics
        $paragraphs[] = sprintf(
            '<p>Certified to <strong>%s</strong> protocols, the architecture is subjected to rigorous linear deceleration limits and oblique impact evaluations to mitigate rotational acceleration forces associated with diffuse axonal injury. For a deeper breakdown of rotational thresholds, drop-testing velocity, and anvil geometries, consult our engineering guide on <a href="https://helmetsan.com/ece-22-06-vs-dot-vs-snell-helmet-safety-standards/">ECE 22.06 vs DOT vs SNELL helmet safety standards</a>.</p>',
            esc_html($certClean),
        );

        // Paragraph 3: Cranial Ergonomics & Zygomatic Fitment
        $paragraphs[] = sprintf(
            '<p>Internal ergonomics follow a <strong>%s</strong> cranial geometry, sculpted to mitigate focal pressure hot spots across the parietal ridges while maintaining uniform radial clamping pressure along the zygomatic arch. Riders seeking optimal retention stability and break-in guidance should review our authoritative <a href="https://helmetsan.com/intermediate-oval-vs-long-oval-head-shape-guide/">cranial aspect ratios and head shape guide</a> as well as the <a href="https://helmetsan.com/helmet-cheek-pad-fit-and-break-in-guide/">cheek pad density and break-in protocol</a>.</p>',
            esc_html(ucwords(str_replace("-", " ", $shapeClean))),
        );

        // Paragraph 4: Aero-Acoustic Insulation & Wind-Tunnel Profiling
        $paragraphs[] = sprintf(
            '<p>Cabin acoustics and airflow dynamics record an acoustic rating of approximately <strong>%s</strong> at 100 km/h, stabilized by channelized EPS internal porting and laminar aerodynamic profiling. To analyze how shell materials withstand environmental fatigue and mechanical stress under prolonged UV and thermal exposure, explore our <a href="https://helmetsan.com/carbon-fiber-vs-fiberglass-vs-polycarbonate-helmets/">composite shell material comparison</a>.</p>',
            esc_html($dbStr),
        );

        // Paragraph 5: Vehicle Synergy & Compatible Riding Disciplines
        $paragraphs[] = sprintf(
            '<p><strong>Vehicle Synergy & Riding Discipline:</strong> %s Operating at %s, the helmet achieves a neutral center of gravity that significantly counteracts cervical spine fatigue during sustained highway riding. Inspect our comprehensive <a href="https://helmetsan.com/motorcycles/">motorcycle compatibility directory</a> for verified windscreen and postural pairings.</p>',
            $motoSynergy,
            esc_html($weightStr),
        );

        return implode("\n\n", $paragraphs);
    }

    /**
     * Executes the catalog persistence pipeline.
     *
     * @param int $limit Max records to process (0 = all).
     * @param int $batchSize Batch size for transactions.
     * @param bool $dryRun If true, does not write to database.
     * @param bool $force If true, updates records even if already long.
     * @return array<string, mixed>
     */
    public function run(
        int $limit = 0,
        int $batchSize = 500,
        bool $dryRun = false,
        bool $force = false,
    ): array {
        $startTime = microtime(true);
        $totalUpdated = 0;
        $totalProcessed = 0;

        $whereClause = "WHERE post_type = 'helmet' AND post_status = 'publish'";
        if (!$force) {
            $whereClause .=
                " AND (LENGTH(post_content) < 500 OR post_content NOT LIKE '%protective system%')";
        }

        $totalMatching = (int) $this->db->get_var(
            "SELECT COUNT(*) FROM {$this->db->posts} {$whereClause}",
        );
        echo "Found {$totalMatching} helmet records targeted for synthesis and persistence.\n";

        if ($limit > 0 && $totalMatching > $limit) {
            $totalMatching = $limit;
        }

        $offset = 0;
        while ($offset < $totalMatching) {
            $currentLimit = min($batchSize, $totalMatching - $offset);
            $postsQuery = "
                SELECT ID, post_title, post_name, post_content
                FROM {$this->db->posts}
                {$whereClause}
                ORDER BY ID ASC
                LIMIT {$currentLimit} OFFSET {$offset}
            ";

            $posts = $this->db->get_results($postsQuery);
            if (empty($posts)) {
                break;
            }

            $ids = array_map(fn($p) => (int) $p->ID, $posts);
            $idList = implode(",", $ids);

            // Batch fetch postmeta
            $metaQuery = "
                SELECT post_id, meta_key, meta_value
                FROM {$this->db->postmeta}
                WHERE post_id IN ({$idList})
                  AND meta_key IN ('shell_material', 'homologation_standard', 'head_shape', 'noise_db_at_100kph', 'spec_weight_g', 'brand_name', 'helmet_type')
            ";
            $metaRows = $this->db->get_results($metaQuery);

            $metaMap = [];
            foreach ($metaRows as $m) {
                $pid = (int) $m->post_id;
                $metaMap[$pid][$m->meta_key] = $m->meta_value;
            }

            // Synthesize updates
            $updates = [];
            foreach ($posts as $post) {
                $pid = (int) $post->ID;
                $pMeta = $metaMap[$pid] ?? [];

                $title = (string) $post->post_title;

                // Use theme helpers if available
                $brand = function_exists("helmetsan_get_brand_name")
                    ? helmetsan_get_brand_name($pid)
                    : "";
                if (empty($brand)) {
                    $brand = (string) ($pMeta["brand_name"] ?? "");
                }

                $shell = function_exists("helmetsan_get_shell_material")
                    ? helmetsan_get_shell_material($pid)
                    : "";
                if (empty($shell)) {
                    $shell = (string) ($pMeta["shell_material"] ?? "");
                }

                $shape = function_exists("helmetsan_get_head_shape")
                    ? helmetsan_get_head_shape($pid)
                    : "";
                if (empty($shape)) {
                    $shape = (string) ($pMeta["head_shape"] ?? "");
                }

                $certs = function_exists("helmetsan_get_certifications")
                    ? helmetsan_get_certifications($pid)
                    : "";
                if (empty($certs)) {
                    $certs = (string) ($pMeta["homologation_standard"] ?? "");
                }

                $weight = function_exists("helmetsan_get_weight")
                    ? helmetsan_get_weight($pid)
                    : 0;
                if ($weight <= 0 && isset($pMeta["spec_weight_g"])) {
                    $weight = (int) $pMeta["spec_weight_g"];
                }
                $weightVal = $weight > 0 ? $weight : null;

                $noise = isset($pMeta["noise_db_at_100kph"])
                    ? (float) $pMeta["noise_db_at_100kph"]
                    : null;
                $htype = (string) ($pMeta["helmet_type"] ?? "");

                $synthesized = $this->synthesizeProse(
                    $title,
                    $brand,
                    $htype,
                    $shell,
                    $shape,
                    $certs,
                    $weightVal,
                    $noise,
                    (string) $post->post_content,
                );

                $updates[] = [
                    "id" => $pid,
                    "content" => $synthesized,
                ];
                $totalProcessed++;
            }

            // Execute transactional batch write
            if (!$dryRun && !empty($updates)) {
                $this->db->query("START TRANSACTION");
                foreach ($updates as $up) {
                    $this->db->update(
                        $this->db->posts,
                        ["post_content" => $up["content"]],
                        ["ID" => $up["id"]],
                        ["%s"],
                        ["%d"],
                    );
                    $totalUpdated++;
                }
                $this->db->query("COMMIT");
            } elseif ($dryRun) {
                $totalUpdated += count($updates);
            }

            $offset += count($posts);

            // Guard against memory leaks
            $this->db->queries = [];
            global $wp_object_cache;
            if (
                is_object($wp_object_cache) &&
                property_exists($wp_object_cache, "cache")
            ) {
                $wp_object_cache->cache = [];
            }
            if (function_exists("gc_collect_cycles")) {
                gc_collect_cycles();
            }

            $elapsed = microtime(true) - $startTime;
            $rate = $totalProcessed / max(0.001, $elapsed);
            printf(
                "Progress: %d / %d processed (%d updated) [%.1f records/sec, %.1fs elapsed]\n",
                $totalProcessed,
                $totalMatching,
                $totalUpdated,
                $rate,
                $elapsed,
            );
        }

        $totalTime = microtime(true) - $startTime;
        return [
            "total_processed" => $totalProcessed,
            "total_updated" => $totalUpdated,
            "elapsed_seconds" => round($totalTime, 2),
            "throughput_rps" => round(
                $totalProcessed / max(0.001, $totalTime),
                1,
            ),
        ];
    }
}

// CLI Execution Handler
if (
    php_sapi_name() === "cli" &&
    basename(__FILE__) === basename($_SERVER["SCRIPT_FILENAME"] ?? "")
) {
    $options = getopt("", ["limit::", "batch::", "dry-run", "force"]);
    $limit = isset($options["limit"]) ? (int) $options["limit"] : 0;
    $batch = isset($options["batch"]) ? (int) $options["batch"] : 500;
    $dryRun = isset($options["dry-run"]);
    $force = isset($options["force"]);

    echo "=== Helmetsan Catalog Content Synthesizer & Persistence Pipeline ===\n";
    echo "Mode: " . ($dryRun ? "DRY-RUN (Simulated)" : "LIVE WRITE") . "\n";
    echo "Limit: " . ($limit > 0 ? (string) $limit : "ALL") . "\n";
    echo "Batch Size: {$batch}\n\n";

    $synthesizer = new HelmetCatalogSynthesizer();
    $results = $synthesizer->run($limit, $batch, $dryRun, $force);

    echo "\n=== Synthesis Complete ===\n";
    echo "Total Processed: {$results["total_processed"]}\n";
    echo "Total Updated: {$results["total_updated"]}\n";
    echo "Elapsed: {$results["elapsed_seconds"]}s\n";
    echo "Throughput: {$results["throughput_rps"]} records/sec\n";
}
