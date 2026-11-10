import sys

with open('HelmetsanWeb/helmetsan-theme/single-helmet.php', 'r') as f:
    code = f.read()

# 1. Add $sharpStars and $featuresArr at the top right after $profile
top_marker = "$profile = helmetsan_get_technical_profile($helmetId);"
top_replacement = """$profile = helmetsan_get_technical_profile($helmetId);
        $sharpStars = (int) ($profile['sharp_rating'] ?? 0);
        $featuresArr = (is_string($featuresJson) && $featuresJson !== '') ? (json_decode($featuresJson, true) ?: []) : [];"""

if top_marker in code and "$featuresArr =" not in code[:code.find(top_marker) + 200]:
    code = code.replace(top_marker, top_replacement)

# 2. Fix the $certLabel implode error
broken_cert = "$certLabel = !empty($certs) ? implode(', ', $certs) : 'ECE 22.06 & DOT FMVSS 218';"
fixed_cert = "$certLabel = !empty($certs) ? (is_array($certs) ? implode(', ', $certs) : (string) $certs) : 'ECE 22.06 & DOT FMVSS 218';"

if broken_cert in code:
    code = code.replace(broken_cert, fixed_cert)

with open('HelmetsanWeb/helmetsan-theme/single-helmet.php', 'w') as f:
    f.write(code)

print("Updated single-helmet.php with certLabel type check and top-level variable safety!")
