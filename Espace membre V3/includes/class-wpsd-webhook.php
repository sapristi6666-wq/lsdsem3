<?php
if (!defined('ABSPATH')) exit;

class WPSD_Webhook {
  private $stripe;

  public function __construct($stripe) {
    $this->stripe = $stripe;
  }

  private function opt($k, $default = '') {
    return WPSD_Data::get_cached_option($k, $default);
  }

  private function get_user_email($user_id): string {
    $u = get_userdata((int)$user_id);
    return ($u && !empty($u->user_email)) ? (string)$u->user_email : '';
  }

  private function get_email_template($key, $type) {
    $stored = get_option('wpsd_email_' . $type . '_' . $key, '');
    if ($stored !== '') return $stored;
    $defaults = $this->get_default_email_templates();
    return $defaults[$key . '_' . $type] ?? '';
  }

  private function send_subscription_email($user_id, $plan_key, $status) {
    $u = get_userdata((int)$user_id);
    if (!$u || empty($u->user_email)) return;

    $plan_label = WPSD_Data::plan_label($plan_key, $plan_key ?: '—');
    $subject = $this->get_email_template('subscription_active', 'subject');
    $body = $this->get_email_template('subscription_active', 'body');
    $replacements = ['{{plan_label}}' => $plan_label, '{{status}}' => $status, '{{account_url}}' => home_url('/mon-compte/')];
    $body = str_replace(array_keys($replacements), array_values($replacements), $body);
    $body .= '<p style="margin-top:16px;color:#666;font-size:12px">Message automatique — ne pas répondre.</p>';
    wp_mail($u->user_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
  }

  private function maybe_send_activation_email($user_id, $new_status) {
    if (!in_array($new_status, ['active'], true)) return;
    $sent = (int) get_user_meta($user_id, 'wpsd_sub_email_sent', true);
    if ($sent === 1) return;
    $plan_key = (string) get_user_meta($user_id, 'plan_key', true) ?: (string) get_user_meta($user_id, 'plan_label', true);
    $this->send_subscription_email($user_id, $plan_key, $new_status);
    update_user_meta($user_id, 'wpsd_sub_email_sent', 1);
  }

  private function sync_brevo_by_status($user_id, string $status) {
    if (!class_exists('WPSD_Brevo')) return;
    $email = $this->get_user_email($user_id);
    if (!$email) return;
    $is_active = in_array($status, ['active','trialing'], true);
    $is_inactive = in_array($status, ['canceled','unpaid','incomplete_expired'], true);
    $list_id = (int) WPSD_Data::get_cached_option('brevo_stripe_list_id', 0);
    if ($list_id <= 0) return;
    if ($is_active) WPSD_Brevo::subscribe($email, [], $list_id);
    elseif ($is_inactive) WPSD_Brevo::remove_from_list($email, $list_id);
  }

  private function find_user_by_meta($meta_key, $meta_value) {
    $users = get_users(['meta_key' => $meta_key, 'meta_value' => $meta_value, 'number' => 1, 'fields' => 'ID']);
    return !empty($users) ? intval($users[0]) : null;
  }

  private function notify_admin_pending_registration($email, $nom, $prenom, $role, $plan = 'member') {
    $admin_email = get_option('admin_email');
    if (!$admin_email) return;
    $subject = $this->get_email_template('admin_new_registration', 'subject');
    $body = $this->get_email_template('admin_new_registration', 'body');
    $role_label = WPSD_Data::role_label($role, $role);
    $plan_label = WPSD_Data::plan_label($plan, $plan);
    $replacements = ['{{nom}}' => $nom, '{{prenom}}' => $prenom, '{{email}}' => $email, '{{role}}' => $role_label, '{{plan}}' => $plan_label, '{{admin_url}}' => admin_url('admin.php?page=wpsd-pending-registrations')];
    $body = str_replace(array_keys($replacements), array_values($replacements), $body);
    $body .= '<p style="margin-top:16px;color:#666;font-size:12px">Message automatique — ne pas répondre.</p>';
    wp_mail($admin_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
  }

  private function send_user_pending_confirmation($email, $nom, $prenom, $role, $plan = 'member') {
    if (!$email) return;
    $subject = $this->get_email_template('user_pending', 'subject');
    $body = $this->get_email_template('user_pending', 'body');
    $role_label = WPSD_Data::role_label($role, $role);
    $plan_label = WPSD_Data::plan_label($plan, $plan);
    $replacements = ['{{nom}}' => $nom, '{{prenom}}' => $prenom, '{{email}}' => $email, '{{role}}' => $role_label, '{{plan}}' => $plan_label];
    $body = str_replace(array_keys($replacements), array_values($replacements), $body);
    $body .= '<p style="margin-top:16px;color:#666;font-size:12px">Message automatique — ne pas répondre.</p>';
    wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
  }

  private function get_default_email_templates() {
    $admin_url = admin_url('admin.php?page=wpsd-pending-registrations');
    $account_url = home_url('/mon-compte/');
    return [
      'admin_new_registration_subject' => 'Nouvelle inscription en attente de validation',
      'admin_new_registration_body'    => "<p>Bonjour,</p>\n<p>Une nouvelle inscription est en attente de validation :</p>\n<ul>\n<li>Nom : <strong>{{nom}} {{prenom}}</strong></li>\n<li>Email : <strong>{{email}}</strong></li>\n<li>Rôle : <strong>{{role}}</strong></li>\n<li>Plan : <strong>{{plan}}</strong></li>\n</ul>\n<p><a href=\"{{admin_url}}\">Valider ou refuser</a></p>",
      'user_pending_subject'           => 'Votre inscription a bien été reçue',
      'user_pending_body'              => "<p>Bonjour {{nom}} {{prenom}},</p>\n<p>Nous avons bien reçu votre inscription en tant que <strong>{{role}}</strong> avec l'offre <strong>{{plan}}</strong>.</p>\n<p>Votre compte est actuellement <strong>en attente de validation</strong> par notre équipe.</p>\n<p>Vous recevrez un email dès que votre compte sera validé, avec un lien pour créer votre mot de passe.</p>\n<p>Merci de votre patience !</p>",
      'subscription_active_subject'    => 'Votre abonnement est activé',
      'subscription_active_body'       => "<p>Bonjour,</p>\n<p>Votre abonnement est désormais <strong>actif</strong>.</p>\n<ul>\n<li>Offre : <strong>{{plan_label}}</strong></li>\n</ul>\n<p><a href=\"{{account_url}}\">Accéder à mon compte</a></p>",
      'renewal_reminder_subject'       => 'Rappel : renouvellement automatique le {{date_fr}}',
      'renewal_reminder_body'          => "<p>Bonjour {{display_name}},</p>\n<p>Votre abonnement sera renouvelé le {{date_fr}}.</p>\n<p><a href=\"{{account_url}}\">Gérer mon abonnement</a></p>",
      'pre_arrival_subject'            => 'Votre séjour commence bientôt',
      'pre_arrival_body'               => "<p>Bonjour {{display_name}},</p>\n<p>Votre séjour chez <strong>{{provider_name}}</strong> commence dans <strong>{{days}} jours</strong> (le {{date_start}}).</p>\n<p><strong>Activité :</strong> {{object_title}}</p>\n<p><strong>Adresse :</strong> {{address}}</p>\n<p><strong>Contact :</strong> {{provider_email}}</p>\n<p>Bon séjour !</p>",
    ];
  }

  public function handle(WP_REST_Request $req) {
    error_log('WPSD Webhook: handle() called at ' . current_time('mysql'));
    $payload = $req->get_body();
    $whsec = $this->opt('stripe_webhook_secret');
    if (!$whsec) return new WP_REST_Response(['error' => 'Webhook secret manquant'], 400);
    $event = json_decode($payload, true);
    if (!is_array($event) || empty($event['type']) || empty($event['data']['object'])) return new WP_REST_Response(['error' => 'Invalid payload'], 400);
    $type = $event['type'];

    if ($type === 'checkout.session.completed') {
      $obj = $event['data']['object']; $metadata = $obj['metadata'] ?? [];
      $register_email = sanitize_email($metadata['register_email'] ?? ''); $register_role = $metadata['register_role'] ?? '';
      $register_plan = $metadata['register_plan'] ?? ''; $register_nom = sanitize_text_field($metadata['register_nom'] ?? '');
      $register_prenom = sanitize_text_field($metadata['register_prenom'] ?? ''); $register_phone = sanitize_text_field($metadata['register_phone'] ?? '');
      $session_id = $obj['id'] ?? ''; $customer_id = sanitize_text_field($obj['customer'] ?? ''); $subscription_id = sanitize_text_field($obj['subscription'] ?? '');
      if (!empty($register_email) && !email_exists($register_email)) {
        global $wpdb; $pending_table = WPSD_DB::table_pending_registrations();
        if (!$wpdb->get_var($wpdb->prepare("SELECT id FROM $pending_table WHERE stripe_session_id=%s",$session_id))) {
          $wpdb->insert($pending_table, ['stripe_session_id'=>$session_id,'stripe_customer_id'=>$customer_id,'stripe_subscription_id'=>$subscription_id,'email'=>$register_email,'nom'=>$register_nom,'prenom'=>$register_prenom,'phone'=>$register_phone,'role'=>$register_role,'plan'=>$register_plan,'status'=>'pending'], ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']);
          $this->notify_admin_pending_registration($register_email, $register_nom, $register_prenom, $register_role, $register_plan);
          $this->send_user_pending_confirmation($register_email, $register_nom, $register_prenom, $register_role, $register_plan);
        }
      }
      return new WP_REST_Response(['ok'=>true], 200);
    }

    if (in_array($type, ['customer.subscription.created','customer.subscription.updated','customer.subscription.deleted'], true)) {
      $sub = $event['data']['object']; $customer_id = sanitize_text_field($sub['customer'] ?? ''); $status = sanitize_text_field($sub['status'] ?? '');
      $user_id = $customer_id ? $this->find_user_by_meta('stripe_customer_id', $customer_id) : null;
      if (!$user_id) return new WP_REST_Response(['ok'=>true], 200);
      update_user_meta($user_id, 'stripe_subscription_id', sanitize_text_field($sub['id']));
      update_user_meta($user_id, 'subscription_status', $status);
      update_user_meta($user_id, 'payment_method', 'stripe');
      $this->sync_brevo_by_status($user_id, $status);
      if (isset($sub['items']['data'][0]['price']['id'])) update_user_meta($user_id, 'price_id', sanitize_text_field($sub['items']['data'][0]['price']['id']));
      return new WP_REST_Response(['ok'=>true], 200);
    }

    if (in_array($type, ['invoice.paid','invoice.payment_failed'], true)) {
      $inv = $event['data']['object']; $customer_id = sanitize_text_field($inv['customer'] ?? '');
      $user_id = $customer_id ? $this->find_user_by_meta('stripe_customer_id', $customer_id) : null;
      if (!$user_id) return new WP_REST_Response(['ok'=>true], 200);
      if ($type === 'invoice.paid') {
        update_user_meta($user_id, 'subscription_status', 'active');
        $this->sync_brevo_by_status($user_id, 'active');
        $amount = ($inv['amount_paid'] ?? 0) / 100;
        $plan_key = $this->stripe->get_plan_key_for_user($user_id);
        WPSD_Plugin::record_payment($user_id, $amount, 'stripe', $plan_key, date('Y-m-d', $inv['period_start'] ?? time()), date('Y-m-d', $inv['period_end'] ?? strtotime('+1 year')));
      } else update_user_meta($user_id, 'subscription_status', 'past_due');
      return new WP_REST_Response(['ok'=>true], 200);
    }
    return new WP_REST_Response(['ignored'=>true], 200);
  }
}