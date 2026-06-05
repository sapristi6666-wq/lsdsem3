<?php
if (!defined('ABSPATH')) exit;

class WPSD_REST_Admin {
    private $stripe;

    public function __construct($stripe) { $this->stripe = $stripe; }

    public function register_routes() {
        register_rest_route('wpsd/v1', '/admin/pending-registrations', ['methods'=>'GET','callback'=>[$this,'pending_list'],'permission_callback'=>fn()=>current_user_can('manage_options')||current_user_can('wpsd_moderate_registrations')]);
        register_rest_route('wpsd/v1', '/admin/approve-registration', ['methods'=>'POST','callback'=>[$this,'approve'],'permission_callback'=>fn()=>current_user_can('manage_options')||current_user_can('wpsd_moderate_registrations')]);
        register_rest_route('wpsd/v1', '/admin/reject-registration', ['methods'=>'POST','callback'=>[$this,'reject'],'permission_callback'=>fn()=>current_user_can('manage_options')||current_user_can('wpsd_moderate_registrations')]);
        register_rest_route('wpsd/v1', '/admin/users-by-role', ['methods'=>'GET','callback'=>[$this,'users_by_role'],'permission_callback'=>fn()=>current_user_can('manage_options')||current_user_can('wpsd_moderate_registrations')]);
        register_rest_route('wpsd/v1', '/admin/set-user-role', ['methods'=>'POST','callback'=>[$this,'set_user_role'],'permission_callback'=>fn()=>current_user_can('manage_options')||current_user_can('wpsd_moderate_registrations')]);
    }

    public function pending_list() {
        global $wpdb;
        return new WP_REST_Response(['items'=>$wpdb->get_results("SELECT * FROM ".WPSD_DB::table_pending_registrations()." WHERE status='pending' ORDER BY created_at DESC", ARRAY_A)], 200);
    }

    public function approve(WP_REST_Request $req) {
        global $wpdb; $pt = WPSD_DB::table_pending_registrations();
        $p = $wpdb->get_row($wpdb->prepare("SELECT * FROM $pt WHERE id=%d", (int)$req->get_param('id')), ARRAY_A);
        if (!$p) return new WP_REST_Response(['error'=>'Inscription non trouvée'], 404);
        $pw = wp_generate_password(12, true);
        $uid = wp_create_user($p['email'], $pw, $p['email']);
        if (is_wp_error($uid)) return new WP_REST_Response(['error'=>$uid->get_error_message()], 500);
        update_user_meta($uid, 'last_name', $p['nom']); update_user_meta($uid, 'first_name', $p['prenom']);
        update_user_meta($uid, 'phone', $p['phone']); update_user_meta($uid, 'stripe_customer_id', $p['stripe_customer_id']);
        update_user_meta($uid, 'stripe_subscription_id', $p['stripe_subscription_id']); update_user_meta($uid, 'subscription_status', 'active');
        update_user_meta($uid, 'plan_label', $p['plan']); update_user_meta($uid, 'payment_method', 'stripe'); update_user_meta($uid, 'wpsd_admin_approved', 1);
        foreach(['is_itinerant','is_passeur','is_hebergeur','is_sympathisant'] as $k) update_user_meta($uid, $k, 0);
        $rm = ['itinerant'=>'is_itinerant','passeur'=>'is_passeur','sympathisant'=>'is_sympathisant'];
        if (isset($rm[$p['role']])) update_user_meta($uid, $rm[$p['role']], 1);
        $this->send_password_reset_email($uid, $p['role']);
        $wpdb->delete($pt, ['id'=>(int)$req->get_param('id')]);
        return new WP_REST_Response(['ok'=>true], 200);
    }

    public function reject(WP_REST_Request $req) {
        global $wpdb; $pt = WPSD_DB::table_pending_registrations();
        $p = $wpdb->get_row($wpdb->prepare("SELECT * FROM $pt WHERE id=%d", (int)$req->get_param('id')), ARRAY_A);
        if (!$p) return new WP_REST_Response(['error'=>'Inscription non trouvée'], 404);
        $refund = $this->refund_stripe_subscription($p['stripe_subscription_id']);
        if (is_wp_error($refund)) return new WP_REST_Response(['error'=>'Erreur remboursement: '.$refund->get_error_message()], 500);
        $this->send_rejection_email($p['email'], $p['nom'], $p['prenom']);
        $wpdb->delete($pt, ['id'=>(int)$req->get_param('id')]);
        return new WP_REST_Response(['ok'=>true,'refunded'=>true], 200);
    }

    public function users_by_role(WP_REST_Request $req) {
        $role = sanitize_text_field($req->get_param('role') ?? ''); $search = sanitize_text_field($req->get_param('search') ?? '');
        $page = max(1,(int)($req->get_param('page')??1)); $pp = min(50,max(1,(int)($req->get_param('per_page')??20)));
        $no_role = (int)($req->get_param('no_role')??0); $mq = [];
        if ($no_role===1) { $mq['relation']='AND'; foreach(['is_itinerant','is_passeur','is_hebergeur','is_sympathisant'] as $k) $mq[]=['key'=>$k,'value'=>'1','compare'=>'!=']; }
        elseif (!empty($role)&&in_array($role,['is_itinerant','is_passeur','is_hebergeur','is_sympathisant'])) $mq[]=['key'=>$role,'value'=>'1','compare'=>'='];
        $args = ['number'=>$pp,'offset'=>($page-1)*$pp,'orderby'=>'display_name','order'=>'ASC','meta_query'=>$mq];
        if (!empty($search)) { $args['search']='*'.$search.'*'; $args['search_columns']=['user_email','display_name']; }
        $ca = $args; $ca['number']=9999; $ca['offset']=0; $ca['fields']='ID'; $total = count(get_users($ca));
        $items = array_map(function($u){ $uid=$u->ID; return ['id'=>$uid,'email'=>$u->user_email,'name'=>$u->display_name,'first_name'=>get_user_meta($uid,'first_name',true),'last_name'=>get_user_meta($uid,'last_name',true),'is_itinerant'=>(int)get_user_meta($uid,'is_itinerant',true),'is_passeur'=>(int)get_user_meta($uid,'is_passeur',true),'is_hebergeur'=>(int)get_user_meta($uid,'is_hebergeur',true),'is_sympathisant'=>(int)get_user_meta($uid,'is_sympathisant',true),'is_admin'=>user_can($uid,'manage_options'),'is_moderator'=>user_can($uid,'wpsd_moderate_registrations'),'subscription_status'=>get_user_meta($uid,'subscription_status',true)?:'','plan_label'=>get_user_meta($uid,'plan_label',true)?:'','edit_url'=>admin_url('user-edit.php?user_id='.$uid)]; }, get_users($args));
        return new WP_REST_Response(['items'=>$items,'total'=>$total,'page'=>$page,'per_page'=>$pp,'total_pages'=>ceil($total/$pp)], 200);
    }

    public function set_user_role(WP_REST_Request $req) {
        $params = $req->get_json_params(); $uid = (int)($params['user_id']??0); $role = sanitize_text_field($params['role']??'');
        if (!$uid || !in_array($role,['itinerant','passeur','hebergeur','sympathisant','none'])) return new WP_REST_Response(['error'=>'Paramètres invalides'], 400);
        foreach(['is_itinerant','is_passeur','is_hebergeur','is_sympathisant'] as $k) update_user_meta($uid, $k, 0);
        if ($role!=='none') update_user_meta($uid, 'is_'.$role, 1);
        return new WP_REST_Response(['ok'=>true,'new_role'=>$role], 200);
    }

    private function refund_stripe_subscription($sid) {
        $stripe = new WPSD_Stripe();
        $cancel = $stripe->request('DELETE','/subscriptions/'.$sid);
        if (is_wp_error($cancel)) return $cancel;
        $inv = $stripe->request('GET','/invoices',['subscription'=>$sid,'limit'=>1]);
        if (is_wp_error($inv)||empty($inv['data'][0]['id'])) return new WP_Error('no_invoice','Facture introuvable');
        return $stripe->request('POST','/refunds',['charge'=>$inv['data'][0]['charge']]);
    }

    private function get_email_option($key, $type, $default) {
        $v = get_option('wpsd_email_'.$type.'_'.$key, '');
        return $v !== '' ? $v : $default;
    }

    private function send_rejection_email($email, $nom, $prenom) {
        $subject = $this->get_email_option('user_rejected','subject','Votre adhésion a été refusée');
        $body = $this->get_email_option('user_rejected','body',"<p>Bonjour {{nom}} {{prenom}},</p>\n<p>Votre adhésion n'a pas pu être validée. Votre paiement a été remboursé.</p>");
        $body = str_replace(['{{nom}}','{{prenom}}','{{email}}'],[$nom,$prenom,$email],$body);
        $body .= '<p style="margin-top:16px;color:#666;font-size:12px">Message automatique — ne pas répondre.</p>';
        wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    private function send_password_reset_email($user_id, $role) {
        $user = get_userdata($user_id); if (!$user) return;
        $reset_key = get_password_reset_key($user); if (is_wp_error($reset_key)) return;
        $reset_url = network_site_url("wp-login.php?action=rp&key=$reset_key&login=".rawurlencode($user->user_login));
        $role_label = WPSD_Data::role_label($role, $role);
        $subject = $this->get_email_option('user_approved','subject','Bienvenue - Créez votre mot de passe');
        $body = $this->get_email_option('user_approved','body',"<p>Bonjour {{display_name}},</p>\n<p>Votre adhésion a été validée.</p>\n<p><a href=\"{{reset_url}}\">Créer mon mot de passe</a></p>");
        $body = str_replace(['{{display_name}}','{{email}}','{{role}}','{{reset_url}}'],[$user->display_name,$user->user_email,$role_label,$reset_url],$body);
        $body .= '<p style="margin-top:16px;color:#666;font-size:12px">Message automatique — ne pas répondre.</p>';
        wp_mail($user->user_email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }
}