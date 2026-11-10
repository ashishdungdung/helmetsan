with open('HelmetsanWeb/helmetsan-core/includes/Media/MediaService.php', 'r') as f:
    code = f.read()

target = """        // 2. Gallery Images (geo_media_json)
        $geoMediaJson = get_post_meta($helmetId, 'geo_media_json', true);
        if (is_string($geoMediaJson) && $geoMediaJson !== '') {
            $media = json_decode($geoMediaJson, true);
            if (is_array($media)) {
                foreach ($media as $imageUrl) {
                    if (! is_string($imageUrl) || $imageUrl === '') {
                        continue;
                    }
                    $imageUrl = esc_url_raw($imageUrl);
                    if ($imageUrl === '') {
                        continue;
                    }
                    $gallery[] = [
                        'type' => 'image',
                        'url' => $imageUrl,
                        'thumb' => $imageUrl,
                        'alt' => get_the_title($helmetId),
                    ];
                }
            }
        }"""

replacement = """        // 2. Gallery Images (geo_media_json with recursive extraction & placehold.co filter)
        $geoMediaJson = get_post_meta($helmetId, 'geo_media_json', true);
        if (is_string($geoMediaJson) && $geoMediaJson !== '') {
            $media = json_decode($geoMediaJson, true);
            if (is_array($media)) {
                $rawUrls = [];
                $extractFn = function ($items) use (&$extractFn, &$rawUrls) {
                    foreach ($items as $val) {
                        if (is_string($val) && $val !== '' && strpos($val, 'placehold.co') === false) {
                            $rawUrls[] = $val;
                        } elseif (is_array($val)) {
                            $extractFn($val);
                        }
                    }
                };
                $extractFn($media);

                foreach (array_unique($rawUrls) as $imageUrl) {
                    $cleanUrl = esc_url_raw($imageUrl);
                    if ($cleanUrl === '') {
                        continue;
                    }
                    $gallery[] = [
                        'type' => 'image',
                        'url' => $cleanUrl,
                        'thumb' => $cleanUrl,
                        'alt' => get_the_title($helmetId),
                    ];
                }
            }
        }"""

if target in code:
    code = code.replace(target, replacement)

    parent_check = """        // 4. Fallback to Parent Helmet Gallery if variant has no images
        if (empty($gallery)) {
            $parentId = wp_get_post_parent_id($helmetId);
            if ($parentId > 0 && $parentId !== $helmetId) {
                return $this->getProductGallery($parentId);
            }
        }

        return apply_filters('helmetsan_product_gallery', $gallery, $helmetId);"""

    old_ret = "return apply_filters('helmetsan_product_gallery', $gallery, $helmetId);"
    if old_ret in code:
        code = code.replace(old_ret, parent_check)

    with open('HelmetsanWeb/helmetsan-core/includes/Media/MediaService.php', 'w') as f:
        f.write(code)
    print('Updated MediaService.php successfully')
else:
    print('Target not found in MediaService.php')
