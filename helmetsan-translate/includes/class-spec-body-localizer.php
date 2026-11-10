<?php

declare(strict_types=1);

namespace HelmetsanTranslate;

defined('ABSPATH') || exit;

/**
 * High-performance Technical Spec Body Localizer for Helmetsan Catalog.
 *
 * Localizes paragraphs 2 through 6 of helmet posts across all 9 target languages:
 * German (de), French (fr), Spanish (es), Italian (it), Dutch (nl), Polish (pl),
 * Portuguese (pt), Japanese (ja), and Chinese (zh).
 *
 * Preserves the localized editorial lead (Paragraph 1) while replacing formulaic
 * engineering specifications with fluent native terminology and localized internal URLs.
 */
class SpecBodyLocalizer
{
    private const BASE_URL = 'https://helmetsan.com';

    private const HEAD_SHAPE_MAP = [
        'Intermediate Oval' => [
            'de' => 'Intermediär-Oval (Intermediate Oval)',
            'fr' => 'Ovale Intermédiaire (Intermediate Oval)',
            'es' => 'Oval Intermedio (Intermediate Oval)',
            'it' => 'Ovale Intermedio (Intermediate Oval)',
            'nl' => 'Intermediair Ovaal (Intermediate Oval)',
            'pl' => 'Średnio-Owalny (Intermediate Oval)',
            'pt' => 'Oval Intermediário (Intermediate Oval)',
            'ja' => 'インターミディエイト・オーバル（中間楕円型）',
            'zh' => '中椭圆型（Intermediate Oval）',
        ],
        'Long Oval' => [
            'de' => 'Lang-Oval (Long Oval)',
            'fr' => 'Ovale Allongé (Long Oval)',
            'es' => 'Oval Largo (Long Oval)',
            'it' => 'Ovale Lungo (Long Oval)',
            'nl' => 'Lang Ovaal (Long Oval)',
            'pl' => 'Długo-Owalny (Long Oval)',
            'pt' => 'Oval Longo (Long Oval)',
            'ja' => 'ロング・オーバル（長楕円型）',
            'zh' => '长椭圆型（Long Oval）',
        ],
        'Round Oval' => [
            'de' => 'Rund-Oval (Round Oval)',
            'fr' => 'Ovale Rond (Round Oval)',
            'es' => 'Ovale Rotondo (Round Oval)',
            'it' => 'Ovale Rotondo (Round Oval)',
            'nl' => 'Rond Ovaal (Round Oval)',
            'pl' => 'Okrągło-Owalny (Round Oval)',
            'pt' => 'Oval Redondo (Round Oval)',
            'ja' => 'ラウンド・オーバル（丸型楕円）',
            'zh' => '圆椭圆型（Round Oval）',
        ],
    ];

    private const HELMET_TYPE_MAP = [
        'full-face' => [
            'de' => 'Integralhelm',
            'fr' => 'casque intégral',
            'es' => 'casco integral',
            'it' => 'casco integrale',
            'nl' => 'integraalhelm',
            'pl' => 'kask integralny',
            'pt' => 'capacete integral',
            'ja' => 'フルフェイスヘルメット',
            'zh' => '全盔',
        ],
        'modular' => [
            'de' => 'Klapphelm',
            'fr' => 'casque modulable',
            'es' => 'casco modular',
            'it' => 'casco modulare',
            'nl' => 'systeemhelm',
            'pl' => 'kask szczękowy',
            'pt' => 'capacete modular',
            'ja' => 'システムヘルメット',
            'zh' => '揭面盔',
        ],
        'open-face' => [
            'de' => 'Jethelm',
            'fr' => 'casque jet',
            'es' => 'casco jet abierto',
            'it' => 'casco jet',
            'nl' => 'jethelm',
            'pl' => 'kask otwarty',
            'pt' => 'capacete aberto',
            'ja' => 'ジェットヘルメット',
            'zh' => '半盔/复古盔',
        ],
        'dual-sport' => [
            'de' => 'Dual-Sport- und Adventure-Helm',
            'fr' => 'casque trail et aventure',
            'es' => 'casco dual-sport y aventura',
            'it' => 'casco dual-sport e adventure',
            'nl' => 'dual-sport en adventure helm',
            'pl' => 'kask dual-sport i adventure',
            'pt' => 'capacete dual-sport e aventura',
            'ja' => 'アドベンチャー/デュアルスポーツヘルメット',
            'zh' => '拉力与探险多功能盔',
        ],
    ];

    private const SHELL_MATERIAL_MAP = [
        'carbon' => [
            'de' => 'Kohlefaser-Verbundschale (Carbon)',
            'fr' => 'coque composite en fibre de carbone',
            'es' => 'calota compuesta de fibra de carbono',
            'it' => 'calotta in fibra di carbonio composita',
            'nl' => 'koolstofvezel composietschaal',
            'pl' => 'skorupa kompozytowa z włókna węglowego',
            'pt' => 'casco composto de fibra de carbono',
            'ja' => 'カーボンファイバー複合シェル',
            'zh' => '碳纤维高强度复合盔体',
        ],
        'fiberglass' => [
            'de' => 'Glasfaser-Verbundschale (Fiberglass)',
            'fr' => 'coque composite en fibre de verre',
            'es' => 'calota compuesta de fibra de vidrio',
            'it' => 'calotta in fibra di vetro composita',
            'nl' => 'glasvezel composietschaal',
            'pl' => 'skorupa z włókna szklanego',
            'pt' => 'casco composto de fibra de vidro',
            'ja' => 'グラスファイバー複合シェル',
            'zh' => '玻璃纤维强化复合盔体',
        ],
        'polycarbonate' => [
            'de' => 'Stoßfeste Polycarbonat-Thermoplastschale',
            'fr' => 'coque en polycarbonate thermoplastique injecté',
            'es' => 'calota de policarbonato termoplástico inyectado',
            'it' => 'calotta in policarbonato termoplastico iniettato',
            'nl' => 'geïnjecteerde polycarbonaat schaal',
            'pl' => 'skorupa z formowanego termoplastycznego poliwęglanu',
            'pt' => 'casco em policarbonato termoplástico injetado',
            'ja' => '高耐衝撃ポリカーボネートシェル',
            'zh' => '高强度注塑聚碳酸酯盔体',
        ],
        'composite' => [
            'de' => 'Mehrkomposit-Faserschale mit EPS-Kern',
            'fr' => 'coque multi-composite haute absorption avec calotin EPS',
            'es' => 'calota multi-compuesta avanzada con forro EPS',
            'it' => 'calotta multi-composita avanzata con EPS',
            'nl' => 'multicomposiet schaalopbouw met EPS-kern',
            'pl' => 'wielokompozytowa skorupa strukturalna z warstwą EPS',
            'pt' => 'casco multi-compósito com revestimento em EPS',
            'ja' => '多重複合繊維シェル・多密度EPSライナー',
            'zh' => '多重复合纤维盔体结构与多密度缓冲层',
        ],
    ];

    private const ANCHOR_TEXTS = [
        'safety' => [
            'de' => 'Vergleich von ECE 22.06, DOT und SNELL Helm-Sicherheitsnormen',
            'fr' => 'normes de sécurité ECE 22.06, DOT et SNELL pour casques',
            'es' => 'estándares de seguridad de cascos ECE 22.06 vs DOT vs SNELL',
            'it' => 'standard di sicurezza per caschi ECE 22.06 vs DOT vs SNELL',
            'nl' => 'vergelijking van ECE 22.06, DOT en SNELL helmveiligheidsnormen',
            'pl' => 'normy bezpieczeństwa kasków ECE 22.06 vs DOT vs SNELL',
            'pt' => 'padrões de segurança de capacetes ECE 22.06 vs DOT vs SNELL',
            'ja' => 'ECE 22.06 vs DOT vs SNELL ヘルメット安全規格比較ガイド',
            'zh' => 'ECE 22.06 对比 DOT 与 SNELL 头盔安全标准指南',
        ],
        'head_shape' => [
            'de' => 'Leitfaden zu Kopfformen und Passform',
            'fr' => 'guide des morphologies crâniennes et de forme de tête',
            'es' => 'guía de formas de cabeza y anatomía craneal',
            'it' => 'guida alle forme craniche e calzata ideale',
            'nl' => 'gids voor hoofdvormen en schedelgeometrie',
            'pl' => 'przewodnik po kształtach głowy i dopasowaniu kasku',
            'pt' => 'guia de proporções anatômicas e formatos de cabeça',
            'ja' => '頭部形状プロファイルと頭骨フィッティングガイド',
            'zh' => '头型分类与颅骨几何贴合指南',
        ],
        'cheek_pads' => [
            'de' => 'Wangenpolster-Dichte und Einbettungsprotokoll',
            'fr' => 'protocole d\'ajustement et de rodage des mousses de joues',
            'es' => 'protocolo de ajuste y asentamiento de almohadillas',
            'it' => 'guida alla densità e rodaggio dei guanciali',
            'nl' => 'wangkussen-dichtheid en inloopprotocol',
            'pl' => 'procedura dopasowania i układania poduszek policzkowych',
            'pt' => 'protocolo de ajuste e assentamento das bochecheiras',
            'ja' => 'チークパッドの密度と馴染ませプロトコル',
            'zh' => '脸颊内衬密度与贴合磨合规范',
        ],
        'materials' => [
            'de' => 'Vergleich von Carbon-, Glasfaser- und Polycarbonat-Helmschalen',
            'fr' => 'comparatif carbone, fibre de verre et polycarbonate',
            'es' => 'comparativa de materiales: fibra de carbono, fibra de vidrio y policarbonato',
            'it' => 'confronto strutturale tra fibra di carbonio, fibra di vetro e policarbonato',
            'nl' => 'vergelijking van composiet schaalmaterialen',
            'pl' => 'porównanie materiałów skorup: włókno węglowe, szklane i poliwęglan',
            'pt' => 'comparativo de materiais: fibra de carbono, fibra de vidro e policarbonato',
            'ja' => 'カーボン vs グラスファイバー vs ポリカーボネート複合シェル比較',
            'zh' => '碳纤维、玻璃纤维与聚碳酸酯复合盔体材质对比',
        ],
        'motorcycles' => [
            'de' => 'Motorrad-Kompatibilitätsverzeichnis',
            'fr' => 'répertoire officiel de compatibilité moto',
            'es' => 'directorio oficial de compatibilidad con motocicletas',
            'it' => 'repertorio completo di compatibilità moto',
            'nl' => 'motorfiets-compatibiliteitsgids',
            'pl' => 'katalog zgodności z modelami motocykli',
            'pt' => 'diretório de compatibilidade com motocicletas',
            'ja' => 'モーターサイクル適合モデル一覧',
            'zh' => '摩托车车型工程兼容性目录',
        ],
    ];

    /**
     * Localize the technical specification paragraphs of a helmet post.
     */
    public static function localizePost(int $postId): bool
    {
        $post = get_post($postId);
        if (! $post || $post->post_status !== 'publish' || $post->post_type !== 'helmet') {
            return false;
        }

        $lang = self::resolveLanguage($post);
        if ($lang === 'en') {
            return false; // Master English remains untouched
        }

        $content = $post->post_content;
        $editorialLead = '';
        if (preg_match('/<p class="hs-editorial-lead">([\s\S]*?)<\/p>/', $content, $leadMatch)) {
            $editorialLead = $leadMatch[0];
        }

        // Extract helmet parameters
        $specs = [
            'title'       => $post->post_title,
            'model'       => self::extractModelName($post->post_title),
            'brand'       => self::resolveBrandName($postId),
            'type_slug'   => self::resolveTaxonomySlug($postId, 'helmet_type', 'full-face'),
            'shell'       => (string) (get_post_meta($postId, 'spec_shell_material', true) ?: 'Composite Fiber / Multi-Density EPS'),
            'certs'       => (string) (get_post_meta($postId, 'homologation_standard', true) ?: 'ECE 22.06 / DOT'),
            'head_shape'  => (string) (get_post_meta($postId, 'head_shape', true) ?: 'Intermediate Oval'),
            'noise_db'    => (string) (get_post_meta($postId, 'noise_db_at_100kph', true) ?: '84.0'),
            'weight_g'    => (string) (get_post_meta($postId, 'spec_weight_g', true) ?: '1450'),
        ];

        $localizedBody = self::buildLocalizedBody($lang, $specs);
        $newContent = $editorialLead !== '' ? ($editorialLead . "\n\n" . $localizedBody) : $localizedBody;

        if ($newContent === $content) {
            return false;
        }

        global $wpdb;
        $updated = $wpdb->update(
            $wpdb->posts,
            ['post_content' => $newContent],
            ['ID' => $postId],
            ['%s'],
            ['%d']
        );

        clean_post_cache($postId);
        return $updated !== false;
    }

    /**
     * Build the 5 localized engineering paragraphs for the target language.
     *
     * @param string $lang
     * @param array<string, string> $specs
     * @return string
     */
    public static function buildLocalizedBody(string $lang, array $specs): string
    {
        $prefix = self::BASE_URL . "/{$lang}/";

        $guideSafetyUrl = "{$prefix}ece-22-06-vs-dot-vs-snell-helmet-safety-standards-2/";
        $guideShapeUrl  = "{$prefix}intermediate-oval-vs-long-oval-head-shape-guide-2/";
        $guidePadsUrl   = "{$prefix}helmet-cheek-pad-fit-and-break-in-guide-2/";
        $guideMatUrl    = "{$prefix}carbon-fiber-vs-fiberglass-vs-polycarbonate-helmets-2/";
        $guideMotoUrl   = "{$prefix}motorcycles/";

        $txtSafety = self::ANCHOR_TEXTS['safety'][$lang] ?? self::ANCHOR_TEXTS['safety']['de'];
        $txtShape  = self::ANCHOR_TEXTS['head_shape'][$lang] ?? self::ANCHOR_TEXTS['head_shape']['de'];
        $txtPads   = self::ANCHOR_TEXTS['cheek_pads'][$lang] ?? self::ANCHOR_TEXTS['cheek_pads']['de'];
        $txtMat    = self::ANCHOR_TEXTS['materials'][$lang] ?? self::ANCHOR_TEXTS['materials']['de'];
        $txtMoto   = self::ANCHOR_TEXTS['motorcycles'][$lang] ?? self::ANCHOR_TEXTS['motorcycles']['de'];

        $rawShape = strtolower($specs['head_shape']);
        $shapeKey = 'Intermediate Oval';
        if (str_contains($rawShape, 'long')) {
            $shapeKey = 'Long Oval';
        } elseif (str_contains($rawShape, 'round')) {
            $shapeKey = 'Round Oval';
        }
        $headShapeLabel = self::HEAD_SHAPE_MAP[$shapeKey][$lang] ?? $specs['head_shape'];

        $cleanTypeSlug = (string) preg_replace('/-[a-z]{2}$/', '', $specs['type_slug']);
        $typeLabel = self::HELMET_TYPE_MAP[$cleanTypeSlug][$lang] ?? (self::HELMET_TYPE_MAP[$specs['type_slug']][$lang] ?? 'Helm');

        $model = esc_html($specs['model']);
        $brand = esc_html($specs['brand']);

        $rawShell = strtolower($specs['shell']);
        $shellKey = 'composite';
        if (str_contains($rawShell, 'carbon')) {
            $shellKey = 'carbon';
        } elseif (str_contains($rawShell, 'fiberglass') || str_contains($rawShell, 'glas')) {
            $shellKey = 'fiberglass';
        } elseif (str_contains($rawShell, 'polycarbonate') || str_contains($rawShell, 'thermoplastic')) {
            $shellKey = 'polycarbonate';
        }
        $shell = self::SHELL_MATERIAL_MAP[$shellKey][$lang] ?? esc_html($specs['shell']);

        $certs = esc_html($specs['certs']);
        $noise = esc_html($specs['noise_db']);
        $weight = esc_html($specs['weight_g']);

        switch ($lang) {
            case 'de':
                return <<<HTML
<p>Der <strong>{$model}</strong> ist von {$brand} als hochintegres {$typeLabel}-Schutzsystem konstruiert und nutzt eine Strukturschale aus <strong>{$shell}</strong>. Dieser Schichtaufbau ermöglicht eine progressive Dissipation kinetischer Aufprallenergie bei Stoßverzögerungen, verteilt lokale Belastungsspitzen gleichmäßig über die äußeren Lamellenschichten und minimiert die Verformungsübertragung in den mehrteiligen EPS-Dämpfungskern.</p>

<p>Zertifiziert nach <strong>{$certs}</strong>-Protokollen, unterliegt die Architektur strengen linearen Verzögerungsgrenzwerten und Schrägaufprallprüfungen, um rotatorische Beschleunigungskräfte wirksam abzufangen. Für detaillierte Einblicke in Rotationsschwellenwerte, Fallprüfungsgeschwindigkeiten und Ambossgeometrien konsultieren Sie unseren Fachleitfaden zu <a href="{$guideSafetyUrl}">{$txtSafety}</a>.</p>

<p>Die innere Ergonomie folgt einer <strong>{$headShapeLabel}</strong>-Schädelgeometrie, entwickelt zur Vermeidung punktueller Druckspitzen an den Scheitelbeinen bei konstantem radialem Anpressdruck entlang des Jochbogens. Fahrer, die maximale Passformstabilität suchen, finden fundierte Hinweise in unserem <a href="{$guideShapeUrl}">{$txtShape}</a> sowie im <a href="{$guidePadsUrl}">{$txtPads}</a>.</p>

<p>Die Kabinenakustik und Strömungsdynamik erzielen einen Pegel von ca. <strong>{$noise} dB</strong> bei 100 km/h, stabilisiert durch kanalisierte EPS-Innenbelüftung und laminare Außenprofile. Erfahren Sie in unserem <a href="{$guideMatUrl}">{$txtMat}</a>, wie Schalenmaterialien unter UV- und Temperaturbelastung mechanischer Materialermüdung standhalten.</p>

<p><strong>Fahrzeug-Synergie &amp; Fahrdisziplin:</strong> Ausgelegt für ausgewogenen Abtrieb und direkten Stirnlufteinlass ist dieser Helm auf anspruchsvolle Fahrprofile abgestimmt. Mit einem Gesamtgewicht von {$weight} Gramm sorgt er für einen neutralen Schwerpunkt und verringert spürbar Ermüdungen der Halswirbelsäule auf langen Fahrten. Konsultieren Sie unser vollständiges <a href="{$guideMotoUrl}">{$txtMoto}</a> für kompatible Windschild- und Haltungskombinationen.</p>
HTML;

            case 'fr':
                return <<<HTML
<p>Le <strong>{$model}</strong> est conçu par {$brand} comme un système de protection haute intégrité de type {$typeLabel}, utilisant une coque structurelle en <strong>{$shell}</strong>. Cette stratification assure une dissipation progressive de l'énergie cinétique lors des impacts, répartissant les ondes de choc sur les couches externes tout en limitant le transfert de déformation vers le calotin interne en EPS multi-densité.</p>

<p>Homologué selon les normes <strong>{$certs}</strong>, le casque satisfait aux exigences rigoureuses de décélération linéaire et aux tests d'impact oblique pour atténuer les accélérations rotationnelles. Pour approfondir les seuils biomécaniques et protocoles d'enclume, consultez notre guide technique sur les <a href="{$guideSafetyUrl}">{$txtSafety}</a>.</p>

<p>L'ergonomie interne repose sur une géométrie crânienne <strong>{$headShapeLabel}</strong>, sculptée pour éliminer les points de pression focaux au niveau des crêtes pariétales tout en garantissant un maintien uniforme sur l'arcade zygomatique. Les motards à la recherche d'un ajustement optimal trouveront des recommandations clés dans notre <a href="{$guideShapeUrl}">{$txtShape}</a> et notre <a href="{$guidePadsUrl}">{$txtPads}</a>.</p>

<p>L'acoustique en cabine et l'aérodynamique affichent un indice sonore d'environ <strong>{$noise} dB</strong> à 100 km/h, canalisé par des cannelures EPS internes et un profilage laminaire. Pour comprendre la résistance aux contraintes thermiques et UV des composites, explorez notre <a href="{$guideMatUrl}">{$txtMat}</a>.</p>

<p><strong>Synergie véhicule &amp; discipline de conduite :</strong> Équilibré pour un appui aérodynamique stable et une ventilation frontale réactive, ce casque s'adapte parfaitement aux trajectoires sportives et routières. Avec une masse de {$weight} grammes, il abaisse le centre de gravité et soulage durablement les vertèbres cervicales. Consultez notre <a href="{$guideMotoUrl}">{$txtMoto}</a> pour vérifier la compatibilité avec votre bulle et position de conduite.</p>
HTML;

            case 'es':
                return <<<HTML
<p>El <strong>{$model}</strong> ha sido diseñado por {$brand} como un sistema integral de protección de tipo {$typeLabel}, incorporando una calota estructural en <strong>{$shell}</strong>. Este laminado progresivo disipa eficazmente la energía cinética del impacto, distribuyendo las fuerzas sobre las capas exteriores para minimizar la deformación transmitida al forro interno EPS multidensidad.</p>

<p>Certificado bajo los exigentes protocolos <strong>{$certs}</strong>, supera rigurosos límites de desaceleración lineal e impacto oblicuo para reducir la aceleración rotacional sobre la cavidad craneal. Para conocer al detalle los valores umbral y geometrías de ensayo, consulte nuestra guía técnica sobre <a href="{$guideSafetyUrl}">{$txtSafety}</a>.</p>

<p>La ergonomía interior adopta una morfología <strong>{$headShapeLabel}</strong>, perfilada para disipar puntos de presión en la zona parietal garantizando un ajuste uniforme a lo largo del arco cigomático. Para lograr la máxima estabilidad en carretera, revise nuestra <a href="{$guideShapeUrl}">{$txtShape}</a> y el <a href="{$guidePadsUrl}">{$txtPads}</a>.</p>

<p>La insonorización del habitáculo registra un índice acústico cercano a <strong>{$noise} dB</strong> a 100 km/h, optimizado mediante canales de ventilación EPS y un perfil exterior aerodinámico. Para evaluar la durabilidad mecánica de las fibras frente a la degradación UV y térmica, examine nuestra <a href="{$guideMatUrl}">{$txtMat}</a>.</p>

<p><strong>Sinergia con el vehículo y disciplina de conducción:</strong> Diseñado para ofrecer sustentación neutra y una ventilación frontal óptima, este casco responde con precisión en conducción ágil y viajes prolongados. Con un peso de {$weight} gramos, optimiza el centro de masas para mitigar la fatiga en la columna cervical. Visite nuestro <a href="{$guideMotoUrl}">{$txtMoto}</a> para configuraciones posturales y cúpulas compatibles.</p>
HTML;

            case 'it':
                return <<<HTML
<p>Il <strong>{$model}</strong> è progettato da {$brand} come un sistema protettivo ad alta integrità di tipo {$typeLabel}, equipaggiato con una calotta strutturale in <strong>{$shell}</strong>. Questa stratificazione garantisce una dissipazione progressiva dell'energia cinetica in decelerazione da impatto, distribuendo lo shock sulle lamine esterne e riducendo al minimo la trasmissione delle sollecitazioni all'EPS interno multidensità.</p>

<p>Omologato in conformità agli standard <strong>{$certs}</strong>, supera severi collaudi di decelerazione lineare e impatto obliquo per contenere le accelerazioni rotazionali. Per un'analisi tecnica approfondita sui parametri di decelerazione e geometrie di prova, consultate la nostra guida sugli <a href="{$guideSafetyUrl}">{$txtSafety}</a>.</p>

<p>L'ergonomia interna si conforma alla conformazione <strong>{$headShapeLabel}</strong>, sagomata per prevenire picchi di compressione sulle creste parietali mantenendo una tenuta uniforme lungo l'arcata zigomatica. I motociclisti che cercano un fitting impeccabile possono fare riferimento alla <a href="{$guideShapeUrl}">{$txtShape}</a> e al <a href="{$guidePadsUrl}">{$txtPads}</a>.</p>

<p>L'acustica interna e i flussi d'aria registrano una silenziosità di circa <strong>{$noise} dB</strong> a 100 km/h, favorita dai condotti interni EPS e da profili aerodinamici laminari. Per comprendere la resistenza dei compositi all'invecchiamento termico e ai raggi UV, scoprite il nostro <a href="{$guideMatUrl}">{$txtMat}</a>.</p>

<p><strong>Sinergia con il veicolo e stile di guida:</strong> Bilanciato per assicurare deportanza neutra e ventilazione costante, il casco si adatta a una guida dinamica e turistica. Con un peso contenuto di {$weight} grammi, centra il baricentro riducendo l'affaticamento del tratto cervicale su percorrenze autostradali. Consultate il nostro <a href="{$guideMotoUrl}">{$txtMoto}</a> per verificare cupolini e posizioni di guida compatibili.</p>
HTML;

            case 'nl':
                return <<<HTML
<p>De <strong>{$model}</strong> is door {$brand} ontwikkeld als een hoogwaardig {$typeLabel}-veiligheidssysteem met een structurele buitenschaal van <strong>{$shell}</strong>. Deze composietopbouw zorgt voor een progressieve absorptie van kinetische botsingsenergie, waarbij piekbelastingen over de buitenlagen worden verdeeld om vervorming van de interne EPS-kern met meervoudige dichtheid te minimaliseren.</p>

<p>Gecertificeerd volgens <strong>{$certs}</strong>-protocollen, voldoet het ontwerp aan strikte lineaire vertragingslimieten en schuine impacttests ter beperking van rotatiekrachten. Voor gedetailleerde gegevens over botsproeven en valtests kunt u onze gids over <a href="{$guideSafetyUrl}">{$txtSafety}</a> raadplegen.</p>

<p>De binnenpasvorm sluit aan op een <strong>{$headShapeLabel}</strong> schedelprofiel, gevormd om drukpunten op de pariëtale zones te voorkomen en een gelijkmatige klemkracht op de jukbeenderen te bewaren. Rijders die optimale stabiliteit wensen, vinden waardevolle instructies in onze <a href="{$guideShapeUrl}">{$txtShape}</a> en het <a href="{$guidePadsUrl}">{$txtPads}</a>.</p>

<p>De cabineakoestiek en aerodynamica noteren een geluidsniveau van circa <strong>{$noise} dB</strong> bij 100 km/u, mede dankzij gekanaliseerde ventilatiepoorten in het EPS en een gestroomlijnd schaalontwerp. Bekijk in onze <a href="{$guideMatUrl}">{$txtMat}</a> hoe schaalmaterialen presteren onder mechanische en thermische belasting.</p>

<p><strong>Voertuigsenergie &amp; rijdiscipline:</strong> Uitgebalanceerd voor een stabiele neerwaartse druk en actieve luchtinlaat, sluit deze helm aan bij sportief en toeristisch gebruik. Met een totaalgewicht van {$weight} gram zorgt hij voor een neutraal zwaartepunt en vermindert hij belasting van de nekwervels op lange snelwegritten. Bekijk onze <a href="{$guideMotoUrl}">{$txtMoto}</a> voor geschikte windschermen en zitposities.</p>
HTML;

            case 'pl':
                return <<<HTML
<p>Kask <strong>{$model}</strong> został opracowany przez markę {$brand} jako zaawansowany system ochrony typu {$typeLabel}, wykorzystujący skorupę strukturalną wykonaną z <strong>{$shell}</strong>. Taki układ warstw gwarantuje progresywne rozpraszanie kinetycznej energii uderzenia, równomiernie absorbując obciążenia na zewnętrznych powłokach i chroniąc wewnętrzną warstwę EPS o zmiennej gęstości.</p>

<p>Certyfikowany zgodnie z protokołami <strong>{$certs}</strong>, kask spełnia rygorystyczne normy opóźnień liniowych oraz testów uderzeń skośnych pod kątem redukcji sił rotacyjnych. Szczegółowe omówienie progów deceleracji i metodologii testów kowadełkowych znajduje się w przewodniku po <a href="{$guideSafetyUrl}">{$txtSafety}</a>.</p>

<p>Ergonomia wnętrza opiera się na geometrii czaszkowej <strong>{$headShapeLabel}</strong>, wyprofilowanej tak, aby eliminować punkty nacisku na kościach ciemieniowych przy jednoczesnym równomiernym docisku wzdłuż łuku jarzmowego. Aby idealnie dobrać kask, zapoznaj się z naszym <a href="{$guideShapeUrl}">{$txtShape}</a> oraz <a href="{$guidePadsUrl}">{$txtPads}</a>.</p>

<p>Wyciszenie wnętrza i aerodynamika gwarantują poziom hałasu rzędu ok. <strong>{$noise} dB</strong> przy 100 km/h, co osiągnięto dzięki kanałowej wentylacji EPS i laminarnemu profilowi skorupy. Wpływ warunków termicznych i promieniowania UV na kompozyty wyjaśnia nasze <a href="{$guideMatUrl}">{$txtMat}</a>.</p>

<p><strong>Synergia z pojazdem i dyscyplina jazdy:</strong> Wyważony pod kątem stabilnego docisku i wydajnego nawiewu czołowego, kask ten sprawdzi się idealnie w jeździe sportowo-turystycznej. Masa {$weight} gramów zapewnia optymalny środek ciężkości, ograniczając zmęczenie odcinka szyjnego kręgosłupa. Sprawdź nasz <a href="{$guideMotoUrl}">{$txtMoto}</a>, aby dobrać kompatybilną szybę i ergonomię motocykla.</p>
HTML;

            case 'pt':
                return <<<HTML
<p>O <strong>{$model}</strong> foi desenvolvido pela {$brand} como um sistema de proteção de alta integridade do tipo {$typeLabel}, utilizando um casco estrutural fabricado em <strong>{$shell}</strong>. Essa estratificação de compósitos garante uma dissipação progressiva da energia cinética de impacto, distribuindo os picos de pressão nas camadas exteriores e limitando a deformação sobre o EPS interno multidensidade.</p>

<p>Homologado pelos padrões <strong>{$certs}</strong>, o capacete atende a rigorosos limites de desaceleração linear e impactos oblíquos para mitigar acelerações rotacionais. Para detalhes sobre ensaios mecânicos e limiares de segurança, consulte nosso guia sobre os <a href="{$guideSafetyUrl}">{$txtSafety}</a>.</p>

<p>A ergonomia interna baseia-se no formato craniano <strong>{$headShapeLabel}</strong>, desenhado para eliminar pontos de pressão nas cristas parietais mantendo contato uniforme ao longo do arco zigomático. Para orientações sobre estabilidade e amaciamento, confira nosso <a href="{$guideShapeUrl}">{$txtShape}</a> e o <a href="{$guidePadsUrl}">{$txtPads}</a>.</p>

<p>A acústica de cabine e os fluxos aerodinâmicos registram um nível de ruído em torno de <strong>{$noise} dB</strong> a 100 km/h, estabilizado por condutos internos no EPS e perfil externo laminar. Conheça a resistência mecânica das fibras perante intempéries e raios UV em nosso <a href="{$guideMatUrl}">{$txtMat}</a>.</p>

<p><strong>Sinergia com o veículo e modalidade de pilotagem:</strong> Afinado para sustentação neutra e captação eficiente de ar frontal, este capacete é ideal para pilotagem esportiva e viagens prolongadas. Com peso de {$weight} gramas, proporciona centro de gravidade equilibrado, reduzindo a fadiga cervical. Consulte nosso <a href="{$guideMotoUrl}">{$txtMoto}</a> para combinações ideais de postura e para-brisa.</p>
HTML;

            case 'ja':
                return <<<HTML
<p><strong>{$model}</strong> は、{$brand} により高剛性 {$typeLabel} 保護システムとして設計され、<strong>{$shell}</strong> 製の構造シェルを採用しています。この先進的な積層構造は、衝撃減速時に運動エネルギーを段階的に分散・吸収し、外層ラミネート全体に応力を分散させることで、多密度内部EPSライナーへの局所的な変形伝達を最小限に抑えます。</p>

<p><strong>{$certs}</strong> 規格に適合し、厳格な線形減速度基準および斜め衝撃試験をクリアすることで、回転加速度による脳損傷リスクを低減する構造となっています。衝撃吸収性能やアンビル試験の詳細については、当社の <a href="{$guideSafetyUrl}">{$txtSafety}</a> をご覧ください。</p>

<p>内装エルゴノミクスは <strong>{$headShapeLabel}</strong> 頭骨プロファイルに沿って成形されており、頭頂部および側頭部への過度な圧迫点を防ぎつつ、頬骨弓に沿った均一なホールド感を提供します。最適な装着安定性と馴染ませ方については、<a href="{$guideShapeUrl}">{$txtShape}</a> および <a href="{$guidePadsUrl}">{$txtPads}</a> をご確認ください。</p>

<p>走行時のキャビン静粛性と整流性能は、時速100kmにおいて約 <strong>{$noise} dB</strong> を記録。内部EPSのベンチレーションダクトと層流エアロダイナミクスにより風切り音を抑制しています。熱疲労や紫外線に対するシェル素材の耐久性については、<a href="{$guideMatUrl}">{$txtMat}</a> で詳しく解説しています。</p>

<p><strong>車両との親和性・推奨ライディングスタイル：</strong> 安定したダウンフォースと額部吸気口を備え、ワインディングから高速ツーリングまで幅広いライディングに対応します。総重量 {$weight} g のバランス設計により重心位置が最適化され、長時間の巡航時でも頸椎への負担を大幅に軽減します。車種ごとのウインドスクリーン適合については、当社の <a href="{$guideMotoUrl}">{$txtMoto}</a> をご参照ください。</p>
HTML;

            case 'zh':
            default:
                return <<<HTML
<p><strong>{$model}</strong> 是 {$brand} 打造的高结构强度 {$typeLabel} 安全防护装备，其外壳采用 <strong>{$shell}</strong> 制造。多重复合层叠结构能够在发生撞击时实现阶梯式动能吸附与扩散，有效将冲击载荷分散到外层壳体表面，最大程度减缓冲击能量向内部多重密度 EPS 缓冲层的传导。</p>

<p>通过严苛的 <strong>{$certs}</strong> 安全认证标准，该盔体经过严格的直线减速与倾斜角冲击测试，有效削减引发弥漫性轴索损伤的旋转加速度。关于跌落测试初速度与撞击砧板几何规范的深度解读，请查阅我们的工程指南：<a href="{$guideSafetyUrl}">{$txtSafety}</a>。</p>

<p>内衬工效学基于 <strong>{$headShapeLabel}</strong> 颅骨结构进行三维塑形，消除顶骨边缘的局部压痛点，同时保持颧骨弓部位的均匀贴合压感。如需了解佩戴稳定性与内衬磨合指导，请参考我们的 <a href="{$guideShapeUrl}">{$txtShape}</a> 与 <a href="{$guidePadsUrl}">{$txtPads}</a>。</p>

<p>在时速 100 公里/小时巡航工况下，舱内风噪值实测约为 <strong>{$noise} dB</strong>，这得益于内嵌 EPS 风道导流与层流外形设计。若需进一步分析不同壳体材质在长期紫外线及热疲劳下的力学耐久表现，请参考我们的 <a href="{$guideMatUrl}">{$txtMat}</a>。</p>

<p><strong>车辆协同效应与骑行场景：</strong> 兼顾下压力平衡与前额高负压进气，针对运动街车与公路巡航骑行姿态进行了针对性调校。整盔重量约为 {$weight} 克，拥有居中的质量重心分布，显著减轻长时间高速骑行造成的颈椎肌群疲劳。欢迎查阅我们的 <a href="{$guideMotoUrl}">{$txtMoto}</a>，获取匹配风挡与坐姿协同建议。</p>
HTML;
        }
    }

    /**
     * Localize all non-English helmets in high-throughput batches.
     *
     * @param int $limit
     * @param int $batchSize
     * @param string|null $targetLang
     * @param callable(int, int): void|null $progressCallback
     * @return array{total_scanned: int, total_updated: int, duration_sec: float}
     */
    public static function localizeCatalog(int $limit = 0, int $batchSize = 1000, ?string $targetLang = null, ?callable $progressCallback = null): array
    {
        global $wpdb;
        $start = microtime(true);

        $langClause = "";
        if ($targetLang !== null && $targetLang !== '') {
            $langClause = "AND post_name LIKE '%-" . esc_sql($targetLang) . "'";
        } else {
            // All 9 non-English languages
            $suffixes = ['-de', '-fr', '-es', '-it', '-nl', '-pl', '-pt', '-ja', '-zh'];
            $likeClauses = [];
            foreach ($suffixes as $s) {
                $likeClauses[] = "post_name LIKE '%" . esc_sql($s) . "'";
            }
            $langClause = "AND (" . implode(' OR ', $likeClauses) . ")";
        }

        $limitSql = $limit > 0 ? "LIMIT {$limit}" : "";
        $postIds = $wpdb->get_col("SELECT ID FROM {$wpdb->posts} WHERE post_type = 'helmet' AND post_status = 'publish' {$langClause} ORDER BY ID ASC {$limitSql}");

        $scanned = 0;
        $updated = 0;
        $total = count($postIds);

        foreach (array_chunk($postIds, $batchSize) as $chunk) {
            foreach ($chunk as $postId) {
                $scanned++;
                if (self::localizePost((int) $postId)) {
                    $updated++;
                }
            }
            if ($progressCallback !== null) {
                $progressCallback($scanned, $total);
            }
        }

        return [
            'total_scanned' => $scanned,
            'total_updated' => $updated,
            'duration_sec'  => round(microtime(true) - $start, 2),
        ];
    }

    private static function resolveLanguage(\WP_Post $post): string
    {
        if (preg_match('/-([a-z]{2})$/', $post->post_name, $m)) {
            return $m[1];
        }

        if (function_exists('pll_get_post_language')) {
            $lang = (string) pll_get_post_language($post->ID);
            if ($lang !== '') {
                return $lang;
            }
        }

        return 'en';
    }

    private static function extractModelName(string $title): string
    {
        // Strip out repetitive words like "Helmet", "Casque", etc.
        $clean = preg_replace('/\b(Helmet|Casque|Casco|Helme|Kask|Capacete|ヘルメット|头盔)\b/iu', '', $title);
        return trim((string) $clean) ?: $title;
    }

    private static function resolveBrandName(int $postId): string
    {
        $terms = wp_get_post_terms($postId, 'brand', ['fields' => 'names']);
        if (! empty($terms) && ! is_wp_error($terms)) {
            return (string) $terms[0];
        }

        $rel = get_post_meta($postId, 'rel_brand', true);
        if ($rel) {
            $brandPost = get_post((int) $rel);
            if ($brandPost) {
                return $brandPost->post_title;
            }
        }

        return 'Helmetsan';
    }

    private static function resolveTaxonomySlug(int $postId, string $taxonomy, string $default = ''): string
    {
        $terms = wp_get_post_terms($postId, $taxonomy, ['fields' => 'slugs']);
        if (! empty($terms) && ! is_wp_error($terms)) {
            return (string) $terms[0];
        }

        return $default;
    }
}
