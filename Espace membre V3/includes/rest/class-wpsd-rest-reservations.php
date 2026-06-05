<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_Reservations {
    use WPSD_REST_Helpers;
    private $stripe;

    public function __construct($stripe) {
        $this->stripe = $stripe;
    }

    public function register_routes() {
        register_rest_route('wpsd/v1', '/reservations', [
            'methods' => 'POST', 'callback' => [$this, 'create'], 'permission_callback' => fn() => is_user_logged_in() && $this->require_itinerant(),
        ]);
        register_rest_route('wpsd/v1', '/reservations', [
            'methods' => 'GET', 'callback' => [$this, 'list'], 'permission_callback' => fn() => is_user_logged_in() && $this->is_active_user(),
        ]);
        register_rest_route('wpsd/v1', '/reservations/(?P<id>\d+)/approve', [
            'methods' => 'POST', 'callback' => [$this, 'approve'], 'permission_callback' => fn() => is_user_logged_in() && $this->require_provider(),
        ]);
        register_rest_route('wpsd/v1', '/reservations/(?P<id>\d+)/reject', [
            'methods' => 'POST', 'callback' => [$this, 'reject'], 'permission_callback' => fn() => is_user_logged_in() && $this->require_provider(),
        ]);
        register_rest_route('wpsd/v1', '/reservations/(?P<id>\d+)/cancel', [
            'methods' => 'POST', 'callback' => [$this, 'cancel'], 'permission_callback' => fn() => is_user_logged_in() && $this->is_active_user(),
        ]);
        register_rest_route('wpsd/v1', '/reservations/(?P<id>\d+)/provider-done', [
            'methods' => 'POST', 'callback' => [$this, 'provider_done'], 'permission_callback' => fn() => is_user_logged_in() && $this->require_provider(),
        ]);
        register_rest_route('wpsd/v1', '/reservations/(?P<id>\d+)/itinerant-done', [
            'methods' => 'POST', 'callback' => [$this, 'itinerant_done'], 'permission_callback' => fn() => is_user_logged_in(),
        ]);
    }

    private function load($id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->reservations_table()} WHERE id=%d", (int)$id), ARRAY_A) ?: null;
    }

        private function notify(int $reservation_id, string $event, ?array $after = null): void {
        $r = $after ?: $this->load($reservation_id);
        if (!$r) return;
        $recipients = $this->reservation_recipients($r);

        // Map internal events to template events
        $template_event_map = [
            'created'       => 'reservation_created',
            'approved'      => 'reservation_accepted',
            'rejected'      => 'reservation_rejected',
            'canceled'      => 'reservation_canceled',
            'provider_done' => 'provider_done',
            'itinerant_done'=> 'itinerant_done',
        ];

        $tpl_event = $template_event_map[$event] ?? '';
        if (!$tpl_event) return;

        // Build recipient targets per template event
        $target_map = [
            'reservation_created'  => array_merge($recipients['admin'], $recipients['provider']),
            'reservation_accepted' => array_merge($recipients['admin'], $recipients['itinerant']),
            'reservation_rejected' => array_merge($recipients['admin'], $recipients['itinerant']),
            'reservation_canceled' => array_merge($recipients['admin'], $recipients['provider'], $recipients['itinerant']),
            'provider_done'        => array_merge($recipients['admin'], $recipients['itinerant']),
            'itinerant_done'       => array_merge($recipients['admin'], $recipients['provider']),
        ];

        $targets = $target_map[$tpl_event] ?? [];
        if (empty($targets)) return;

        // Build shortcode data
        $itinerant = get_userdata((int)($r['itinerant_user_id'] ?? 0));
        $provider  = get_userdata((int)($r['provider_user_id'] ?? 0));
        $object_title = !empty($r['object_id']) ? (get_the_title((int)$r['object_id']) ?: '') : '';
        $date_start   = !empty($r['date_start']) ? date_i18n('d/m/Y', strtotime($r['date_start'])) : '';
        $date_end     = !empty($r['date_end'])   ? date_i18n('d/m/Y', strtotime($r['date_end']))   : '';
        $days = 0;
        if (!empty($r['date_start']) && !empty($r['date_end'])) {
            $days = (int) max(1, (strtotime($r['date_end']) - strtotime($r['date_start'])) / DAY_IN_SECONDS);
        }

        $data = [
            'display_name'  => $itinerant ? esc_html($itinerant->display_name ?: $itinerant->user_login) : '',
            'provider_name' => $provider  ? esc_html($provider->display_name ?: $provider->user_login)  : '',
            'object_title'  => esc_html($object_title),
            'date_start'    => $date_start,
            'date_end'      => $date_end,
            'days'          => (string) $days,
        ];

        $subject = WPSD_Data::get_email_template($tpl_event, 'subject');
        $body    = WPSD_Data::get_email_template($tpl_event, 'body');

        // Fallback to hard-coded defaults if template is empty
        if (empty($subject) || empty($body)) {
            $status_label = WPSD_Data::status_label((string)($r['status'] ?? ''), $r['status'] ?? '');
            $dates_display = !empty($r['date_start']) ? esc_html($r['date_start'] . ' → ' . $r['date_end']) : '';
            switch ($event) {
                case 'created':
                    $subject = "Nouvelle réservation : {$object_title}";
                    $body = "<p>Nouvelle réservation créée.</p><ul><li>Prestation : <strong>" . esc_html($object_title) . "</strong></li><li>Période : <strong>{$dates_display}</strong></li><li>Quantité : <strong>" . (int)($r['quantity'] ?? 1) . "</strong></li><li>Statut : <strong>{$status_label}</strong></li></ul>";
                    break;
                case 'approved':
                    $subject = "Réservation acceptée : {$object_title}";
                    $body = "<p>Réservation <strong>acceptée</strong>.</p><ul><li>Prestation : <strong>" . esc_html($object_title) . "</strong></li><li>Période : <strong>{$dates_display}</strong></li></ul>";
                    break;
                case 'rejected':
                    $subject = "Réservation refusée : {$object_title}";
                    $body = "<p>Réservation <strong>refusée</strong>.</p><ul><li>Prestation : <strong>" . esc_html($object_title) . "</strong></li><li>Période : <strong>{$dates_display}</strong></li></ul>";
                    break;
                case 'canceled':
                    $subject = "Réservation annulée : {$object_title}";
                    $body = "<p>Réservation <strong>annulée</strong>.</p><ul><li>Prestation : <strong>" . esc_html($object_title) . "</strong></li><li>Période : <strong>{$dates_display}</strong></li></ul>";
                    break;
                case 'provider_done':
                    $subject = "Prestation réalisée : {$object_title}";
                    $body = "<p>Le prestataire a confirmé la prestation.</p><ul><li>Prestation : <strong>" . esc_html($object_title) . "</strong></li><li>Période : <strong>{$dates_display}</strong></li></ul>";
                    break;
                case 'itinerant_done':
                    $subject = "Itinérant a confirmé : {$object_title}";
                    $body = "<p>L'itinérant a confirmé.</p><ul><li>Prestation : <strong>" . esc_html($object_title) . "</strong></li><li>Période : <strong>{$dates_display}</strong></li></ul>";
                    break;
                default:
                    return;
            }
        } else {
            $subject = WPSD_Data::render_email_template($subject, $data);
            $body    = WPSD_Data::render_email_template($body, $data);
        }

        $body .= WPSD_Data::get_email_legal_suffix();
        $this->wp_mail_html($targets, $subject, $body);
    }

    private function reservation_recipients(array $r): array {
        $admin = $this->admin_emails();
        $it = []; $pr = [];
        $it_email = $this->user_email_by_id($r['itinerant_user_id'] ?? 0); if ($it_email) $it[] = $it_email;
        $pr_email = $this->user_email_by_id($r['provider_user_id'] ?? 0); if ($pr_email) $pr[] = $pr_email;
        return ['admin' => $admin, 'itinerant' => $it, 'provider' => $pr];
    }

    private function admin_emails(): array { $e = sanitize_email(get_option('admin_email')); return $e ? [$e] : []; }
    private function user_email_by_id($uid): string { $u = get_userdata((int)$uid); return ($u && !empty($u->user_email)) ? sanitize_email($u->user_email) : ''; }
    private function wp_mail_html(array $to, string $subject, string $html): bool {
        if (empty($to)) return false;
        return wp_mail($to, $subject, $html, ['Content-Type: text/html; charset=UTF-8']);
    }

    public function create(WP_REST_Request $req) {
        global $wpdb;
        $uid = get_current_user_id(); $p = $req->get_json_params() ?: [];
        $kind = sanitize_text_field($p['kind'] ?? ''); $object_id = (int)($p['object_id'] ?? 0);
        $date_start = sanitize_text_field($p['date_start'] ?? ''); $date_end = sanitize_text_field($p['date_end'] ?? '');
        $quantity = max(1, (int)($p['quantity'] ?? 1)); $note = sanitize_textarea_field($p['itinerant_note'] ?? '');
        if (!in_array($kind, ['activity','accommodation'], true) || $object_id <= 0 || !$date_start || !$date_end) return new WP_REST_Response(['ok' => false, 'error' => 'Paramètres invalides'], 400);
        $cpt = ($kind === 'activity') ? 'wpsd_activity' : 'wpsd_accommodation';
        $post = get_post($object_id);
        if (!$post || $post->post_type !== $cpt || $post->post_status !== 'publish') return new WP_REST_Response(['ok' => false, 'error' => 'Prestation introuvable'], 404);
        $provider_id = (int)$post->post_author;
        if ($provider_id <= 0) return new WP_REST_Response(['ok' => false, 'error' => 'Prestataire invalide'], 400);

        $wpdb->query('START TRANSACTION');
        $slot = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->slots_table()} WHERE kind=%s AND object_id=%d AND date_start <= %s AND date_end >= %s ORDER BY date_start ASC LIMIT 1 FOR UPDATE", $kind, $object_id, $date_start, $date_end), ARRAY_A);
        if (!$slot) { $wpdb->query('ROLLBACK'); return new WP_REST_Response(['ok' => false, 'error' => 'Aucune disponibilité'], 409); }
        $slot_id = (int)$slot['id'];
        $slot_total = ($kind === 'activity') ? (int)$slot['capacity'] : (int)$slot['units'];
        $reserved = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(quantity),0) FROM {$this->reservations_table()} WHERE slot_id=%d AND status IN ('pending','approved')", $slot_id));
        $remaining = max(0, $slot_total - $reserved);
        if ($quantity > $remaining) { $wpdb->query('ROLLBACK'); return new WP_REST_Response(['ok' => false, 'error' => 'Plus assez de disponibilité (reste: '.$remaining.')'], 409); }
        $wpdb->insert($this->reservations_table(), ['itinerant_user_id' => $uid, 'provider_user_id' => $provider_id, 'kind' => $kind, 'object_id' => $object_id, 'slot_id' => $slot_id, 'date_start' => $date_start, 'date_end' => $date_end, 'quantity' => $quantity, 'status' => 'pending', 'itinerant_note' => $note], ['%d','%d','%s','%d','%d','%s','%s','%d','%s','%s']);
        $rid = (int)$wpdb->insert_id;
        if (!$rid) { $wpdb->query('ROLLBACK'); return new WP_REST_Response(['ok' => false, 'error' => 'Erreur insertion'], 500); }
        $wpdb->query('COMMIT');
        $this->notify($rid, 'created', $this->load($rid));
        return new WP_REST_Response(['ok' => true, 'id' => $rid, 'status' => 'pending'], 200);
    }

    public function list(WP_REST_Request $req) {
        global $wpdb; $uid = get_current_user_id();
        $role = sanitize_text_field($req->get_param('role') ?? 'auto'); $status = sanitize_text_field($req->get_param('status') ?? '');
        $is_admin = current_user_can('manage_options'); $is_it = (int)get_user_meta($uid, 'is_itinerant', true) === 1; $is_pr = $this->require_provider();
        if ($role === 'admin' && !$is_admin) $role = 'auto';
        if ($role === 'auto') { if ($is_admin) $role = 'admin'; elseif ($is_pr) $role = 'provider'; elseif ($is_it) $role = 'itinerant'; else $role = 'itinerant'; }
        $where = "1=1"; $args = [];
        if ($role === 'itinerant') { $where .= " AND itinerant_user_id=%d"; $args[] = $uid; }
        elseif ($role === 'provider') { $where .= " AND provider_user_id=%d"; $args[] = $uid; }
        if ($status) { $where .= " AND status=%s"; $args[] = $status; }
        $sql = "SELECT * FROM {$this->reservations_table()} WHERE $where ORDER BY created_at DESC LIMIT 500";
        $rows = $args ? $wpdb->get_results($wpdb->prepare($sql, ...$args), ARRAY_A) : $wpdb->get_results($sql, ARRAY_A);
        $items = array_map(function($r){ $oid = (int)($r['object_id'] ?? 0); $pr = get_userdata((int)($r['provider_user_id'] ?? 0)); $it = get_userdata((int)($r['itinerant_user_id'] ?? 0)); $r['object_title'] = $oid ? get_the_title($oid) : ''; $r['provider_name'] = $pr ? ($pr->display_name ?: $pr->user_login) : ''; $r['itinerant_name'] = $it ? ($it->display_name ?: $it->user_login) : ''; $r['status_label'] = WPSD_Data::status_label($r['status'], $r['status']); $r['provider_done'] = (int)($r['provider_done'] ?? 0); $r['itinerant_done'] = (int)($r['itinerant_done'] ?? 0); return $r; }, $rows);
        return new WP_REST_Response(['ok' => true, 'items' => $items], 200);
    }

    public function approve(WP_REST_Request $req) {
        global $wpdb; $uid = get_current_user_id(); $id = (int)$req['id']; $p = $req->get_json_params() ?: []; $note = sanitize_textarea_field($p['provider_note'] ?? '');
        $r = $this->load($id); if (!$r) return new WP_REST_Response(['ok' => false, 'error' => 'Introuvable'], 404);
        if ((int)$r['provider_user_id'] !== $uid) return new WP_REST_Response(['ok' => false, 'error' => 'Non autorisé'], 403);
        if ($r['status'] !== 'pending') return new WP_REST_Response(['ok' => false, 'error' => 'Statut non modifiable'], 409);
        $slot = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->slots_table()} WHERE id=%d", (int)$r['slot_id']), ARRAY_A);
        if (!$slot) return new WP_REST_Response(['ok' => false, 'error' => 'Slot introuvable'], 404);
        $slot_total = ($r['kind'] === 'activity') ? (int)$slot['capacity'] : (int)$slot['units'];
        $reserved = (int)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(quantity),0) FROM {$this->reservations_table()} WHERE slot_id=%d AND status IN ('pending','approved') AND id<>%d", (int)$r['slot_id'], $id));
        if ((int)$r['quantity'] > max(0, $slot_total - $reserved)) return new WP_REST_Response(['ok' => false, 'error' => 'Plus assez de capacité'], 409);
        $wpdb->update($this->reservations_table(), ['status' => 'approved', 'provider_note' => $note, 'approved_at' => current_time('mysql')], ['id' => $id], ['%s','%s','%s'], ['%d']);
        $this->notify($id, 'approved');
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function reject(WP_REST_Request $req) {
        global $wpdb; $uid = get_current_user_id(); $id = (int)$req['id']; $p = $req->get_json_params() ?: []; $note = sanitize_textarea_field($p['provider_note'] ?? '');
        $r = $this->load($id); if (!$r) return new WP_REST_Response(['ok' => false, 'error' => 'Introuvable'], 404);
        if ((int)$r['provider_user_id'] !== $uid) return new WP_REST_Response(['ok' => false, 'error' => 'Non autorisé'], 403);
        if ($r['status'] !== 'pending') return new WP_REST_Response(['ok' => false, 'error' => 'Statut non modifiable'], 409);
        $wpdb->update($this->reservations_table(), ['status' => 'rejected', 'provider_note' => $note, 'rejected_at' => current_time('mysql')], ['id' => $id], ['%s','%s','%s'], ['%d']);
        $this->notify($id, 'rejected');
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function cancel(WP_REST_Request $req) {
        global $wpdb; $uid = get_current_user_id(); $id = (int)$req['id'];
        $r = $this->load($id); if (!$r) return new WP_REST_Response(['ok' => false, 'error' => 'Introuvable'], 404);
        $is_admin = current_user_can('manage_options'); $is_owner = ((int)$r['itinerant_user_id'] === $uid) || ((int)$r['provider_user_id'] === $uid);
        if (!$is_admin && !$is_owner) return new WP_REST_Response(['ok' => false, 'error' => 'Non autorisé'], 403);
        if (in_array($r['status'], ['canceled','rejected','completed'], true)) return new WP_REST_Response(['ok' => false, 'error' => 'Statut non annulable'], 409);
        $wpdb->update($this->reservations_table(), ['status' => 'canceled', 'canceled_at' => current_time('mysql')], ['id' => $id], ['%s','%s'], ['%d']);
        $this->notify($id, 'canceled');
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function provider_done(WP_REST_Request $req) {
        global $wpdb; $uid = get_current_user_id(); $id = (int)$req['id'];
        $r = $this->load($id); if (!$r) return new WP_REST_Response(['ok' => false, 'error' => 'Introuvable'], 404);
        if ((int)$r['provider_user_id'] !== $uid) return new WP_REST_Response(['ok' => false, 'error' => 'Non autorisé'], 403);
        if (!in_array($r['status'], ['approved','completed'], true)) return new WP_REST_Response(['ok' => false, 'error' => 'Statut invalide'], 409);
        if ((int)$r['provider_done'] === 1) return new WP_REST_Response(['ok' => true, 'already' => true], 200);
        $wpdb->update($this->reservations_table(), ['provider_done' => 1, 'provider_done_at' => current_time('mysql')], ['id' => $id], ['%d','%s'], ['%d']);
        $this->notify($id, 'provider_done');
        $r2 = $this->load($id);
        if ($r2 && (int)$r2['itinerant_done'] === 1 && $r2['status'] === 'approved') { $wpdb->update($this->reservations_table(), ['status' => 'completed', 'completed_at' => current_time('mysql')], ['id' => $id], ['%s','%s'], ['%d']); $this->notify($id, 'completed'); }
        return new WP_REST_Response(['ok' => true], 200);
    }

    public function itinerant_done(WP_REST_Request $req) {
        global $wpdb; $uid = get_current_user_id(); $id = (int)$req['id'];
        $r = $this->load($id); if (!$r) return new WP_REST_Response(['ok' => false, 'error' => 'Introuvable'], 404);
        if ((int)$r['itinerant_user_id'] !== $uid) return new WP_REST_Response(['ok' => false, 'error' => 'Non autorisé'], 403);
        if (!in_array($r['status'], ['approved','completed'], true)) return new WP_REST_Response(['ok' => false, 'error' => 'Statut invalide'], 409);
        if ((int)$r['provider_done'] !== 1) return new WP_REST_Response(['ok' => false, 'error' => 'Attends la validation du prestataire'], 409);
        if ((int)$r['itinerant_done'] === 1) return new WP_REST_Response(['ok' => true, 'already' => true], 200);
        $wpdb->update($this->reservations_table(), ['itinerant_done' => 1, 'itinerant_done_at' => current_time('mysql')], ['id' => $id], ['%d','%s'], ['%d']);
        $this->notify($id, 'itinerant_done');
        if ($r['status'] === 'approved') { $wpdb->update($this->reservations_table(), ['status' => 'completed', 'completed_at' => current_time('mysql')], ['id' => $id], ['%s','%s'], ['%d']); $this->notify($id, 'completed'); }
        return new WP_REST_Response(['ok' => true], 200);
    }
}