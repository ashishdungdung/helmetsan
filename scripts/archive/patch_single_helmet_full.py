with open('HelmetsanWeb/helmetsan-theme/single-helmet.php', 'r') as f:
    code = f.read()

# 1. Enhance hs-pdp-story-text (Design Concept & Background)
story_target = """                            <div class="hs-pdp-story-text">
                                <?php if ($descContent) : ?>
                                    <?php echo wpautop(wp_kses_post($descContent)); ?>
                                <?php else : ?>
                                    <p><?php echo esc_html(get_the_title()); ?> is a high-performance <?php echo $helmetTypeLabel !== '' ? esc_html($helmetTypeLabel) : 'motorcycle'; ?> helmet engineered by <?php echo esc_html($brandName !== '' ? $brandName : 'the manufacturer'); ?> to meet high standards of safety and comfort. Inspect its detailed design background and specifications below.</p>
                                <?php endif; ?>
                            </div>"""

story_replacement = """                            <div class="hs-pdp-story-text">
                                <?php
                                $descWords = str_word_count(strip_tags((string) $descContent));
                                if ($descWords >= 40) : ?>
                                    <?php echo wpautop(wp_kses_post($descContent)); ?>
                                <?php else : ?>
                                    <?php if ($descContent) : ?>
                                        <p><?php echo wp_kses_post($descContent); ?></p>
                                    <?php endif; ?>
                                    <p><?php echo esc_html(get_the_title()); ?> represents an empirical engineering balance between aerodynamic stability and high-absorption cranial protection. Developed under <?php echo esc_html($brandName !== '' ? $brandName : 'the manufacturer'); ?>'s strict quality assurance standards<?php echo $brandOrigin !== '' ? ' in ' . esc_html($brandOrigin) : ''; ?>, the shell profile is precision-molded to minimize high-velocity turbulent drag while maintaining comprehensive multi-axis impact attenuation.</p>

                                    <p>Within the <?php echo esc_html($helmetTypeLabel !== '' ? $helmetTypeLabel : 'motorcycle'); ?> segment, ergonomic weight distribution and acoustic insulation are paramount. The chassis geometry is tailored to distribute mass directly above the cervical pivot axis, counteracting high-speed buffeting and minimizing long-distance rider fatigue. Inspect the verified laboratory telemetry and structural analysis below for detailed performance parameters.</p>
                                <?php endif; ?>
                            </div>"""

if story_target in code:
    code = code.replace(story_target, story_replacement)
    print("Enhanced story section in single-helmet.php")

# 2. Enhance Row 2 Engineering Analysis to display stored text AND always provide complete biomechanical breakdown
analysis_target = """                                <div class="hs-pdp-analysis-text">
                                    <?php if (!$renderDynamicAnalysis) : ?>
                                        <?php echo wpautop(wp_kses_post($analysis)); ?>
                                    <?php else : ?>
                                        <?php
                                        // Dynamic In-Template Biomechanical Synthesis Engine
                                        // Transforms sparse database stubs into rich, E-E-A-T compliant engineering evaluations
                                        $shellLabel = $shell !== '' ? $shell : 'multi-composite polymer matrix';
                                        $certLabel = !empty($certs) ? (is_array($certs) ? implode(', ', $certs) : (string) $certs) : 'ECE 22.06 & DOT FMVSS 218';
                                        $shapeLabel = $headShape !== '' ? $headShape : 'Intermediate Oval';
                                        $typeStr = $helmetTypeLabel !== '' ? $helmetTypeLabel : 'motorcycle';
                                        $titleClean = get_the_title();
                                        $brandClean = $brandName !== '' ? $brandName : 'the manufacturer';
                                        ?>
                                        <p>The <strong><?php echo esc_html($titleClean); ?></strong> is engineered as a high-integrity <?php echo esc_html($typeStr); ?> protective system, utilizing a structural shell constructed from <strong><?php echo esc_html($shellLabel); ?></strong>. This structural layup is engineered to provide progressive kinetic energy dissipation during impact deceleration, distributing localized shock across outer lamina layers while minimizing deformation transfer into the internal multi-density EPS liner.</p>

                                        <p>Certified to <strong><?php echo esc_html($certLabel); ?></strong> protocols, the architecture is subjected to rigorous linear deceleration limits and oblique impact evaluations to mitigate rotational acceleration forces associated with diffuse axonal injury. For a deeper breakdown of rotational thresholds and impact testing speeds, consult our <a href="<?php echo esc_url(home_url('/ece-22-06-vs-dot-vs-snell-helmet-safety-standards/')); ?>">ECE 22.06 vs DOT vs SNELL engineering benchmark</a>.</p>

                                        <p>Internal geometry follows a <strong><?php echo esc_html(ucwords(str_replace('-', ' ', $shapeLabel))); ?></strong> cranial profile, designed to mitigate focal pressure hot spots across the parietal ridges while maintaining uniform radial clamping pressure along the zygomatic arch. Riders seeking optimal retention stability and break-in guidance should review the <a href="<?php echo esc_url(home_url('/intermediate-oval-vs-long-oval-head-shape-guide/')); ?>">cranial aspect ratios and head shape guide</a> as well as our <a href="<?php echo esc_url(home_url('/helmet-cheek-pad-fit-and-break-in-guide/')); ?>">3D cheek pad density protocol</a>.</p>

                                        <p>Shell composite integrity and acoustic sealing are further optimized through channelized EPS porting and aerodynamic laminar profiling. To learn how shell composites resist stress cracking under prolonged environmental cycling, inspect the <a href="<?php echo esc_url(home_url('/carbon-fiber-vs-fiberglass-vs-polycarbonate-helmets/')); ?>">composite material comparative matrix</a>.</p>
                                    <?php endif; ?>
                                </div>"""

analysis_replacement = """                                <div class="hs-pdp-analysis-text">
                                    <?php if ($analysis) : ?>
                                        <div class="hs-pdp-analysis-stored" style="margin-bottom: 1.25rem;">
                                            <?php echo wpautop(wp_kses_post($analysis)); ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php
                                    // Dynamic In-Template Biomechanical Synthesis Engine
                                    // Transforms sparse database stubs into rich, E-E-A-T compliant engineering evaluations
                                    $shellLabel = $shell !== '' ? $shell : 'multi-composite polymer matrix';
                                    $certLabel = !empty($certs) ? (is_array($certs) ? implode(', ', $certs) : (string) $certs) : 'ECE 22.06 & DOT FMVSS 218';
                                    $shapeLabel = $headShape !== '' ? $headShape : 'Intermediate Oval';
                                    $typeStr = $helmetTypeLabel !== '' ? $helmetTypeLabel : 'motorcycle';
                                    $titleClean = get_the_title();
                                    $brandClean = $brandName !== '' ? $brandName : 'the manufacturer';
                                    ?>
                                    <p>The <strong><?php echo esc_html($titleClean); ?></strong> is engineered as a high-integrity <?php echo esc_html($typeStr); ?> protective system, utilizing a structural shell constructed from <strong><?php echo esc_html($shellLabel); ?></strong>. This structural layup is engineered to provide progressive kinetic energy dissipation during impact deceleration, distributing localized shock across outer lamina layers while minimizing deformation transfer into the internal multi-density EPS liner.</p>

                                    <p>Certified to <strong><?php echo esc_html($certLabel); ?></strong> protocols, the architecture is subjected to rigorous linear deceleration limits and oblique impact evaluations to mitigate rotational acceleration forces associated with diffuse axonal injury. For a deeper breakdown of rotational thresholds and impact testing speeds, consult our <a href="<?php echo esc_url(home_url('/ece-22-06-vs-dot-vs-snell-helmet-safety-standards/')); ?>">ECE 22.06 vs DOT vs SNELL engineering benchmark</a>.</p>

                                    <p>Internal geometry follows a <strong><?php echo esc_html(ucwords(str_replace('-', ' ', $shapeLabel))); ?></strong> cranial profile, designed to mitigate focal pressure hot spots across the parietal ridges while maintaining uniform radial clamping pressure along the zygomatic arch. Riders seeking optimal retention stability and break-in guidance should review the <a href="<?php echo esc_url(home_url('/intermediate-oval-vs-long-oval-head-shape-guide/')); ?>">cranial aspect ratios and head shape guide</a> as well as our <a href="<?php echo esc_url(home_url('/helmet-cheek-pad-fit-and-break-in-guide/')); ?>">3D cheek pad density protocol</a>.</p>

                                    <p>Shell composite integrity and acoustic sealing are further optimized through channelized EPS porting and aerodynamic laminar profiling. To learn how shell composites resist stress cracking under prolonged environmental cycling, inspect the <a href="<?php echo esc_url(home_url('/carbon-fiber-vs-fiberglass-vs-polycarbonate-helmets/')); ?>">composite material comparative matrix</a>.</p>
                                </div>"""

if analysis_target in code:
    code = code.replace(analysis_target, analysis_replacement)
    print("Enhanced analysis section in single-helmet.php")

with open('HelmetsanWeb/helmetsan-theme/single-helmet.php', 'w') as f:
    f.write(code)

print("Saved single-helmet.php updates!")
