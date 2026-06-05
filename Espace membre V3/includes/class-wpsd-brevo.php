<?php
if (!defined('ABSPATH')) exit;

class WPSD_Brevo {

  const API_BASE = 'https://api.brevo.com/v3';

  public function __construct() {
    add_action('rest_api_init', [$this, 'register_routes']);
  }

  public static function api_key(): string {
    return (string) WPSD_Data::get_cached_option('brevo_api_key', '');
  }

  public static function list_id(): int {
    return (int) WPSD_Data::get_cached_option('brevo_list_id', 0);
  }

  public static function webhook_token(): string {
    return (string) WPSD_Data::get_cached_option('brevo_webhook_token', '');
  }

  public function register_routes() {
    register_rest_route('wpsd/v1', '/brevo-webhook', [
      'methods'  => 'POST',
      'callback' => [$this, 'handle_webhook'],
      'permission_callback' => '__return_true',
    ]);
  }

  public static function subscribe(string $email, array $attributes = [], ?int $list_id = null) {
    $email = sanitize_email($email);
    if (!is_email($email)) return new WP_Error('invalid_email', 'Email invalide');

    $key = self::api_key();
    if (!$key) return new WP_Error('no_brevo_key', 'Brevo API key manquante (réglages plugin)');

    $list_id = $list_id ?? self::list_id();
    if ($list_id <= 0) return new WP_Error('no_list', 'Brevo list ID manquant (réglages plugin)');

    $payload = [
      'email' => $email,
      'updateEnabled' => true,
      'listIds' => [(int)$list_id],
    ];
    if (!empty($attributes)) $payload['attributes'] = $attributes;

    return self::request('POST', '/contacts', $payload);
  }

  public static function remove_from_list(string $email, ?int $list_id = null) {
    $email = sanitize_email($email);
    if (!is_email($email)) return new WP_Error('invalid_email', 'Email invalide');

    $key = self::api_key();
    if (!$key) return new WP_Error('no_brevo_key', 'Brevo API key manquante (réglages plugin)');

    $list_id = $list_id ?? self::list_id();
    if ($list_id <= 0) return new WP_Error('no_list', 'Brevo list ID manquant (réglages plugin)');

    $payload = [ 'emails' => [$email] ];

    return self::request('POST', '/contacts/lists/'.(int)$list_id.'/contacts/remove', $payload);
  }

  public static function list_contacts(int $list_id, int $limit = 50, int $offset = 0) {
    $key = self::api_key();
    if (!$key) return new WP_Error('no_brevo_key', 'Brevo API key manquante');

    if ($list_id <= 0) return new WP_Error('no_list', 'Brevo list ID invalide');

    $qs = http_build_query([
      'limit'  => max(1, min(500, $limit)),
      'offset' => max(0, $offset),
    ]);

    return self::request('GET', '/contacts/lists/'.$list_id.'/contacts?'.$qs, []);
  }

  private static function request(string $method, string $path, array $payload = []) {
    $key = self::api_key();
    $url = self::API_BASE . $path;

    $args = [
      'method'  => $method,
      'timeout' => 25,
      'headers' => [
        'api-key'       => $key,
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
      ],
    ];

    if (!empty($payload)) $args['body'] = wp_json_encode($payload);

    $res = wp_remote_request($url, $args);
    if (is_wp_error($res)) return $res;

    $code = (int) wp_remote_retrieve_response_code($res);
    $body = (string) wp_remote_retrieve_body($res);
    $json = json_decode($body, true);

    if ($code < 200 || $code >= 300) {
      $msg = $json['message'] ?? $json['error'] ?? 'Brevo error';
      return new WP_Error('brevo_error', (string)$msg, ['status' => $code, 'body' => $json ?: $body]);
    }

    return $json ?: ['ok' => true];
  }

  public function handle_webhook(WP_REST_Request $req) {
    $token    = (string) ($req->get_param('token') ?? '');
    $expected = self::webhook_token();

    if (!$expected || !hash_equals($expected, $token)) {
      return new WP_REST_Response(['ok' => false, 'error' => 'unauthorized'], 401);
    }

    $payload = $req->get_json_params();
    if (!is_array($payload)) {
      return new WP_REST_Response(['ok' => false, 'error' => 'invalid_json'], 400);
    }

    $event = (string)($payload['event'] ?? $payload['type'] ?? $payload['eventType'] ?? '');
    $email = (string)($payload['email'] ?? $payload['contact']['email'] ?? $payload['contactEmail'] ?? '');

    $event_l = strtolower($event);
    $is_unsub = (str_contains($event_l, 'unsub') || str_contains($event_l, 'unsubscribe'));

    if ($is_unsub && $email) {
      $r = self::remove_from_list($email);
      if (is_wp_error($r)) {
        error_log('[WPSD_Brevo] webhook unsubscribe remove_from_list error: '.$r->get_error_message());
      }
    }

    return new WP_REST_Response(['ok' => true], 200);
  }
}