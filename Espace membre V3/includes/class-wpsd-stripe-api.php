<?php
if (!defined('ABSPATH')) exit;

class WPSD_Stripe_API {

    public function request($method, $path, $body = []) {
        $sk = WPSD_Data::get_cached_option('stripe_secret_key');
        if (!$sk) return new WP_Error('no_key', 'Stripe secret key manquante');

        $url = 'https://api.stripe.com/v1' . $path;

        $args = [
            'method'  => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $sk,
                'Content-Type'  => 'application/x-www-form-urlencoded',
            ],
            'timeout' => 30,
        ];

        if (!empty($body)) {
            $args['body'] = http_build_query($body);
        }

        $res = wp_remote_request($url, $args);
        if (is_wp_error($res)) return $res;

        $code = wp_remote_retrieve_response_code($res);
        $json = json_decode(wp_remote_retrieve_body($res), true);

        if ($code < 200 || $code >= 300) {
            $msg = isset($json['error']['message']) ? $json['error']['message'] : 'Stripe error';
            return new WP_Error('stripe_error', $msg, ['status' => $code, 'body' => $json]);
        }
        return $json;
    }

    public function get($path, $params = []) {
        if (!empty($params)) {
            $path .= (strpos($path, '?') === false ? '?' : '&') . http_build_query($params);
        }
        return $this->request('GET', $path);
    }

    public function post($path, $body = []) {
        return $this->request('POST', $path, $body);
    }

    public function delete($path, $body = []) {
        return $this->request('DELETE', $path, $body);
    }
}