<?php

declare(strict_types=1);

namespace Helmetsan\Core\Cloudflare;

use Helmetsan\Core\Support\Config;

/**
 * Cloudflare Turnstile protection for WordPress native forms.
 *
 * Adds Turnstile challenge widgets to:
 *   - wp-login.php login form
 *   - WordPress comment form
 *
 * Verifies Turnstile tokens on submission via:
 *   - authenticate filter (login)
 *   - preprocess_comment filter (comments)
 *
 * Fail-open when Turnstile is not configured, so the site never locks out
 * users due to misconfiguration.
 */
final class TurnstileFormProtection
{
    private readonly TurnstileService $turnstile;
    private readonly Config $config;

    public function __construct(TurnstileService $turnstile, Config $config)
    {
        $this->turnstile = $turnstile;
        $this->config = $config;
    }

    public function register(): void
    {
        $secCfg = $this->config->securityConfig();
        if (!($secCfg['enable_turnstile'] ?? false)) {
            return;
        }

        // Load Turnstile JS on login page and any page rendering a comment form
        add_action('login_enqueue_scripts', [$this, 'enqueueTurnstileScript'], 10);
        add_action('wp_enqueue_scripts', [$this, 'enqueueTurnstileScriptOnCommentPages', 10]);

        // Render Turnstile widget in login and comment forms
        add_action('login_form', [$this, 'renderTurnstileWidget'], 20);
        add_action('comment_form', [$this, 'renderTurnstileWidget'], 20);

        // Verify Turnstile on login and comment submission
        add_filter('authenticate', [$this, 'verifyLoginTurnstile'], 10, 3);
        add_filter('preprocess_comment', [$this, 'verifyCommentTurnstile'], 10, 1);
    }

    /**
     * Enqueue Turnstile API script on the login page.
     */
    public function enqueueTurnstileScript(): void
    {
        wp_enqueue_script(
            'cloudflare-turnstile',
            'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
            [],
            null,
            true
        );
    }

    /**
     * Enqueue Turnstile API script on pages that render a comment form.
     */
    public function enqueueTurnstileScriptOnCommentPages(): void
    {
            if (is_singular() && comments_open() && !is_user_logged_in()) {
                wp_enqueue_script(
                    'cloudflare-turnstile',
                    'https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit',
                    [],
                    null,
                    true
                );
            }
        }

    /**
     * Render the Turnstile widget in a form.
     */
    public function renderTurnstileWidget(): void
    {
        $secCfg = $this->config->securityConfig();
        $siteKey = $secCfg['turnstile_site_key'] ?? '';
        if (empty($siteKey)) {
            return;
        }
        ?>
        <div class="cf-turnstile" data-sitekey="<?php echo esc_attr($siteKey); ?>" data-theme="auto" data-size="compact"></div>
        <p class="turnstile-error" style="display:none; color:#dc2626; font-size:0.875rem; margin-top:0.5rem;">
            <?php esc_html_e('Security verification failed. Please try again.', 'helmetsan-core'); ?>
        </p>
        <?php
    }

    /**
     * Verify Turnstile on WordPress login authentication.
     */
    public function verifyLoginTurnstile($user, $username, $password)
    {
        // If WordPress already failed auth, don't double-penalize
        if ($user instanceof \WP_Error) {
            return $user;
        }

        $secCfg = $this->config->securityConfig();
        if (!($secCfg['enable_turnstile'] ?? false)) {
            return $user;
        }

        $token = isset($_POST['cf-turnstile-response']) ? (string) $_POST['cf-turnstile-response'] : '';
        if (empty($token)) {
            return new \WP_Error(
                'turnstile_required',
                __('<strong>Error:</strong> Security verification is required. Please complete the Turnstile challenge.', 'helmetsan-core')
            );
        }

        $clientIp = isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)
            ? (string) $_SERVER['REMOTE_ADDR']
            : null;

        if (!$this->turnstile->verify($token, $clientIp)) {
            return new \WP_Error(
                'turnstile_failed',
                __('<strong>Error:</strong> Security verification failed. Please try again.', 'helmetsan-core')
            );
        }

        return $user;
    }

    /**
     * Verify Turnstile on comment submission.
     */
    public function verifyCommentTurnstile($commentdata)
    {
        $secCfg = $this->config->securityConfig();
        if (!($secCfg['enable_turnstile'] ?? false)) {
            return $commentdata;
        }

        // Logged-in users are already authenticated; skip Turnstile
        if (is_user_logged_in()) {
            return $commentdata;
        }

        $token = isset($_POST['cf-turnstile-response']) ? (string) $_POST['cf-turnstile-response'] : '';
        if (empty($token)) {
            wp_die(
                __('<strong>Error:</strong> Security verification is required. Please complete the Turnstile challenge.', 'helmetsan-core'),
                'Turnstile Required',
                ['response' => 403, 'link_url' => wp_get_referer(), 'link_text' => __('Go Back', 'helmetsan-core')]
            );
        }

        $clientIp = isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)
            ? (string) $_SERVER['REMOTE_ADDR']
            : null;

        if (!$this->turnstile->verify($token, $clientIp)) {
            wp_die(
                __('<strong>Error:</strong> Security verification failed. Please try again.', 'helmetsan-core'),
                'Turnstile Failed',
                ['response' => 403, 'link_url' => wp_get_referer(), 'link_text' => __('Go Back', 'helmetsan-core')]
            );
        }

        return $commentdata;
    }
}
