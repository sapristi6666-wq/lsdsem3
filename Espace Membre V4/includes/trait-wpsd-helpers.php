<?php
if (!defined('ABSPATH')) exit;

trait WPSD_Helpers {

    private function opt($k, $default = '') {
        return WPSD_Plugin::opt($k, $default);
    }

    private function get_user_email($user_id): string {
        $u = get_userdata((int)$user_id);
        return ($u && !empty($u->user_email)) ? (string)$u->user_email : '';
    }

    private function get_user_name($user_id): string {
        $u = get_userdata((int)$user_id);
        if (!$u) return '';
        $first = get_user_meta($user_id, 'first_name', true);
        $last  = get_user_meta($user_id, 'last_name', true);
        $name = trim($first . ' ' . $last);
        return $name ?: ($u->display_name ?: $u->user_login);
    }

    private function admin_emails(): array {
        $email = sanitize_email(get_option('admin_email'));
        return $email ? [$email] : [];
    }

    private function wp_mail_html(array $to, string $subject, string $html, array $headersExtra = []): bool {
        if (empty($to)) return false;
        $headers = array_merge(['Content-Type: text/html; charset=UTF-8'], $headersExtra);
        return wp_mail($to, $subject, $html, $headers);
    }

    private function format_address($user_id): string {
        $parts = [];
        foreach (['address_line1','address_line2'] as $k) {
            $v = get_user_meta($user_id, $k, true);
            if ($v) $parts[] = $v;
        }
        $pc  = get_user_meta($user_id, 'postal_code', true);
        $city = get_user_meta($user_id, 'city', true);
        $line = trim($pc . ' ' . $city);
        if ($line) $parts[] = $line;
        $country = get_user_meta($user_id, 'country', true);
        if ($country) $parts[] = $country;
        return implode('<br>', array_map('esc_html', $parts));
    }
}