<?php

declare(strict_types=1);

namespace Helmetsan\Core\Alerts;

use Helmetsan\Core\Support\Config;

final class AlertService
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * @param array<string,mixed> $context
     */
    public function send(string $type, string $title, string $message, array $context = []): array
    {
        $cfg = $this->config->alertsConfig();
        if (empty($cfg['enabled'])) {
            return ['ok' => false, 'message' => 'Alerts disabled'];
        }

        $results = [
            'ok' => true,
            'channels' => [],
        ];

        if (! empty($cfg['email_enabled'])) {
            $emailResult = $this->sendEmail($type, $title, $message, $context, $cfg);
            $results['channels']['email'] = $emailResult;
            if (! ($emailResult['ok'] ?? false)) {
                $results['ok'] = false;
            }
        }

        if (! empty($cfg['slack_enabled']) && ! empty($cfg['slack_webhook_url'])) {
            $slackResult = $this->sendSlack($title, $message, $context, (string) $cfg['slack_webhook_url']);
            $results['channels']['slack'] = $slackResult;
            if (! ($slackResult['ok'] ?? false)) {
                $results['ok'] = false;
            }
        }

        return $results;
    }

    /**
     * @param array<string,mixed> $context
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private function sendEmail(string $type, string $title, string $message, array $context, array $cfg): array
    {
        $to = sanitize_email((string) ($cfg['to_email'] ?? get_option('admin_email')));
        if ($to === '') {
            return ['ok' => false, 'message' => 'Invalid recipient email'];
        }

        $subjectPrefix = (string) ($cfg['subject_prefix'] ?? '[Helmetsan]');
        $subject = trim($subjectPrefix . ' ' . $title);

        $body = $message;
        if ($context !== []) {
            $json = wp_json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            $body .= "\n\nContext:\n" . (is_string($json) ? $json : 'N/A');
        }

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        $sent = wp_mail($to, $subject, $body, $headers);

        return [
            'ok' => (bool) $sent,
            'to' => $to,
            'type' => $type,
        ];
    }

    /**
     * @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function sendSlack(string $title, string $message, array $context, string $webhook): array
    {
        $text = '*' . $title . "*\n" . $message;
        if ($context !== []) {
            $json = wp_json_encode($context, JSON_UNESCAPED_SLASHES);
            if (is_string($json)) {
                $text .= "\n```" . $json . '```';
            }
        }

        $response = wp_remote_post($webhook, [
            'timeout' => 15,
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => wp_json_encode(['text' => $text]),
        ]);

        if (is_wp_error($response)) {
            return ['ok' => false, 'message' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        return [
            'ok' => $code >= 200 && $code < 300,
            'code' => $code,
        ];
    }
}
