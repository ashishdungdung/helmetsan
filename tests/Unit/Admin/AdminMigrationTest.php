<?php

declare(strict_types=1);

// Global stubs specifically for testing Admin settings migration export and import
namespace {
    if (!function_exists('update_option')) {
        function update_option($option, $value) {
            $GLOBALS['wp_options'][$option] = $value;
            return true;
        }
    }
    if (!function_exists('current_user_can')) {
        function current_user_can($capability) {
            return $GLOBALS['current_user_can_val'] ?? true;
        }
    }
    if (!function_exists('check_admin_referer')) {
        function check_admin_referer($action, $query_arg = '_wpnonce') {
            return true;
        }
    }
    if (!function_exists('wp_json_encode')) {
        function wp_json_encode($data, $options = 0) {
            return json_encode($data, $options);
        }
    }
    if (!function_exists('home_url')) {
        function home_url() {
            return 'https://helmetsan.com';
        }
    }
    if (!function_exists('admin_url')) {
        function admin_url($path = '') {
            return '/wp-admin/' . $path;
        }
    }
    if (!function_exists('add_query_arg')) {
        function add_query_arg(...$args) {
            if (count($args) === 3) {
                $key = $args[0];
                $value = $args[1];
                $url = $args[2];
                $parts = parse_url($url);
                $query = [];
                if (isset($parts['query'])) {
                    parse_str($parts['query'], $query);
                }
                $query[$key] = $value;
                $GLOBALS['last_redirect_query'] = $query;
                return ($parts['path'] ?? '') . '?' . http_build_query($query);
            } else {
                $assoc = $args[0];
                $url = $args[1] ?? '';
                $parts = parse_url($url);
                $query = [];
                if (isset($parts['query'])) {
                    parse_str($parts['query'], $query);
                }
                $query = array_merge($query, $assoc);
                $GLOBALS['last_redirect_query'] = $query;
                return ($parts['path'] ?? '') . '?' . http_build_query($query);
            }
        }
    }
    if (!function_exists('wp_safe_redirect')) {
        function wp_safe_redirect($location, $status = 302) {
            $GLOBALS['last_redirect_location'] = $location;
            throw new \RuntimeException("Redirected to: " . $location);
        }
    }
}

namespace Helmetsan\Core\Admin\Tests {
    use Helmetsan\Core\Admin\Admin;
    use Helmetsan\Core\Support\Config;
    use PHPUnit\Framework\TestCase;

    final class AdminMigrationTest extends TestCase
    {
        protected function setUp(): void
        {
            parent::setUp();
            $GLOBALS['wp_options'] = [];
            $GLOBALS['last_redirect_location'] = '';
            $GLOBALS['last_redirect_query'] = [];
            $GLOBALS['current_user_can_val'] = true;
        }

        public function testExportSettingsAction(): void
        {
            $GLOBALS['wp_options']['helmetsan_analytics'] = ['enable_analytics' => true];
            $GLOBALS['wp_options']['helmetsan_features'] = ['enable_ajax_catalog_filters' => true];

            $config = new Config();
            
            // Bypass constructor to avoid mocking final dependencies
            $reflector = new \ReflectionClass(Admin::class);
            $admin = $reflector->newInstanceWithoutConstructor();

            $configProp = $reflector->getProperty('config');
            $configProp->setValue($admin, $config);

            ob_start();
            try {
                $admin->handleExportSettingsAction();
            } catch (\Exception $e) {
                // If it exited or redirected
            }
            $output = ob_get_clean();

            $data = json_decode($output, true);
            $this->assertIsArray($data);
            $this->assertSame('Helmetsan Settings Backup', $data['generator']);
            $this->assertTrue($data['options']['helmetsan_analytics']['enable_analytics']);
            $this->assertTrue($data['options']['helmetsan_features']['enable_ajax_catalog_filters']);
        }

        public function testImportSettingsActionSuccess(): void
        {
            $config = new Config();
            
            // Bypass constructor
            $reflector = new \ReflectionClass(Admin::class);
            $admin = $reflector->newInstanceWithoutConstructor();

            $configProp = $reflector->getProperty('config');
            $configProp->setValue($admin, $config);

            // Create temporary backup file
            $backupData = [
                'options' => [
                    'helmetsan_analytics' => ['enable_analytics' => true],
                    'helmetsan_features'  => ['enable_ajax_catalog_filters' => true],
                    'invalid_foreign_key' => 'hack_attempt',
                ]
            ];
            $tmpFile = tempnam(sys_get_temp_dir(), 'hs_test');
            file_put_contents($tmpFile, json_encode($backupData));

            $_FILES['hs_settings_file'] = [
                'tmp_name' => $tmpFile,
                'error'    => 0,
            ];

            try {
                $admin->handleImportSettingsAction();
            } catch (\RuntimeException $e) {
                // Expected redirect exception
            }

            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }

            // Assertions
            $this->assertTrue($GLOBALS['wp_options']['helmetsan_analytics']['enable_analytics']);
            $this->assertTrue($GLOBALS['wp_options']['helmetsan_features']['enable_ajax_catalog_filters']);
            // Foreign key must NOT be imported
            $this->assertArrayNotHasKey('invalid_foreign_key', $GLOBALS['wp_options']);
            
            // Assert redirect message
            $this->assertSame(2, $GLOBALS['last_redirect_query']['imported']); // Should import whitelisted keys
        }
    }
}
