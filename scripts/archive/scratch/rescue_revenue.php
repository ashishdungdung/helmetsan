<?php
$file = '/Users/anumac/Documents/ Projects/Helmetsan/helmetsan-core/includes/Revenue/RevenueService.php';
$content = file_get_contents($file);

// 1. Fix the mangled buildLegacyUrl and logClick start
$search1 = "        \$tag = \$this->getAmazonTagOverride(\$helmetId);\n"
         . "        if         return 'https://www.amazon.com/dp/' . rawurlencode(\$asin) . '?tag=' . rawurlencode(\$tag);\n"
         . "    }\n\n"
         . "    private function logClick(int \$helmetId, string \$source, string \$network, string \$destination, string \$marketplaceId = '', string \$intent = 'purchase'): void";

// Some variants of the mangled text based on what I saw
$mangled1 = "/        if         return 'https:\/\/www\.amazon\.com\/dp\/' \. rawurlencode\(\$asin\) \. '\?tag=' \. rawurlencode\(\$tag\);\n    }\n\n    private function logClick/";

// 2. Fix the non-UTF8 junk
$search2 = "/M-fM-^WM-^KM-^WM-^K      \['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'\]\n        \);\n    }/";

// Actually, I'll just rewrite the whole file since I have the full content in my history
// But let's try a tactical regex fix first to see if I can salvage it.

$fix1 = "        \$tag = \$this->getAmazonTagOverride(\$helmetId);\n"
      . "        if (\$tag === '') {\n"
      . "            \$settings = \$this->config->revenueConfig();\n"
      . "            \$tag = \$settings['amazon_tag'] ?? 'helmetsan-20';\n"
      . "        }\n\n"
      . "        return 'https://www.amazon.com/dp/' . rawurlencode(\$asin) . '?tag=' . rawurlencode(\$tag);\n"
      . "    }\n\n"
      . "    private function logClick(int \$helmetId, string \$source, string \$network, string \$destination, string \$marketplaceId = '', string \$intent = 'purchase'): void";

$content = preg_replace('/        \$tag = \$this->getAmazonTagOverride\(\$helmetId\);\s+if\s+return \'https:\/\/www\.amazon\.com\/dp\/\' \. rawurlencode\(\$asin\) \. \'\?tag=\' \. rawurlencode\(\$tag\);\s+}\s+private function logClick/', $fix1, $content);

// Remove the junk
$content = preg_replace('/[^\x00-\x7F]+      \[\'%s\', \'%d\', \'%s\', \'%s\', \'%s\', \'%s\', \'%s\', \'%s\', \'%s\'\]\s+\);\s+}/Us', '', $content);

file_put_contents($file, $content);
echo "Rescue complete.";
