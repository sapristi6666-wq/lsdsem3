<?php
if (!defined('ABSPATH')) exit;

class WPSD_Data {

    const PLANS = [
        'member'      => 'Membre (50€/an)',
        'family'      => 'Couple / Famille (70€/an)',
        'institution' => 'Institution (100€/an)',
    ];

    const ROLES = [
        'itinerant'     => 'Itinérant-apprenant',
        'passeur'       => 'Passeur de savoir',
        'sympathisant'  => 'Sympathisant',
        'hebergeur'     => 'Hébergeur',
    ];

    const STATUSES = [
        'pending'   => 'En attente',
        'approved'  => 'Accepté',
        'rejected'  => 'Refusé',
        'canceled'  => 'Annulé',
        'completed' => 'Terminé',
    ];

    const PAYMENT_METHODS = [
        'stripe'   => 'Carte bancaire',
        'cash'     => 'Espèces',
        'cheque'   => 'Chèque',
        'transfer' => 'Virement',
        'free'     => 'Gratuit',
    ];

    const KINDS = [
        'activity'      => 'Activité',
        'accommodation' => 'Hébergement',
    ];


        const EMAIL_TEMPLATES_KEY = 'wpsd_email_templates';

    const EMAIL_TEMPLATES = [
        'reservation_created'  => ['Nouvelle réservation créée',       '{{display_name}}, {{provider_name}}, {{object_title}}, {{date_start}}, {{date_end}}, {{days}}'],
        'reservation_accepted' => ['Réservation acceptée',             '{{display_name}}, {{provider_name}}, {{object_title}}, {{date_start}}, {{date_end}}, {{days}}'],
        'reservation_rejected' => ['Réservation refusée',              '{{display_name}}, {{provider_name}}, {{object_title}}, {{date_start}}, {{date_end}}, {{days}}'],
        'reservation_canceled' => ['Réservation annulée',              '{{display_name}}, {{provider_name}}, {{object_title}}, {{date_start}}, {{date_end}}, {{days}}'],
        'provider_done'        => ['Prestataire confirme la prestation','{{display_name}}, {{provider_name}}, {{object_title}}, {{date_start}}, {{date_end}}, {{days}}'],
        'itinerant_done'       => ['Itinérant confirme le séjour',     '{{display_name}}, {{provider_name}}, {{object_title}}, {{date_start}}, {{date_end}}, {{days}}'],
        'pre_arrival'          => ['Rappel J-3 avant arrivée',         '{{display_name}}, {{provider_name}}, {{object_title}}, {{date_start}}, {{date_end}}, {{days}}'],
    ];

    /**
     * Get an email template (subject or body) with fallback to default.
     */
    public static function get_email_template($event, $type) {
        $templates = get_option(self::EMAIL_TEMPLATES_KEY, []);
        $key = $event . '_' . $type;
        if (isset($templates[$key]) && $templates[$key] !== '') {
            return $templates[$key];
        }
        $defaults = self::get_default_email_templates();
        return $defaults[$key] ?? '';
    }

    /**
     * Build email content by replacing {{shortcodes}} in a template.
     */
    public static function render_email_template($template, $data) {
        $search = [];
        $replace = [];
        foreach ($data as $key => $value) {
            $search[] = '{{' . $key . '}}';
            $replace[] = $value;
        }
        return str_replace($search, $replace, (string) $template);
    }

    /**
     * Legal suffix appended to all notification emails.
     */
    public static function get_email_legal_suffix() {
        return '<p style="margin-top:16px;color:#666;font-size:12px">Message automatique — ne pas répondre.</p>';
    }

    /**
     * Default email templates for reservation notifications.
     */
    public static function get_default_email_templates() {
        return [
            'reservation_created_subject'  => 'Nouvelle réservation : {{object_title}}',
            'reservation_created_body'     => '<p>Nouvelle réservation créée.</p><ul><li>Prestation : <strong>{{object_title}}</strong></li><li>Période : <strong>{{date_start}} → {{date_end}}</strong></li><li>Quantité : <strong>{{days}} jour(s)</strong></li><li>Itinérant : <strong>{{display_name}}</strong></li><li>Prestataire : <strong>{{provider_name}}</strong></li></ul>',

            'reservation_accepted_subject'  => 'Réservation acceptée : {{object_title}}',
            'reservation_accepted_body'     => '<p>Réservation <strong>acceptée</strong> par {{provider_name}}.</p><ul><li>Prestation : <strong>{{object_title}}</strong></li><li>Période : <strong>{{date_start}} → {{date_end}}</strong></li></ul>',

            'reservation_rejected_subject'  => 'Réservation refusée : {{object_title}}',
            'reservation_rejected_body'     => '<p>Réservation <strong>refusée</strong> par {{provider_name}}.</p><ul><li>Prestation : <strong>{{object_title}}</strong></li><li>Période : <strong>{{date_start}} → {{date_end}}</strong></li></ul>',

            'reservation_canceled_subject'  => 'Réservation annulée : {{object_title}}',
            'reservation_canceled_body'     => '<p>Réservation <strong>annulée</strong>.</p><ul><li>Prestation : <strong>{{object_title}}</strong></li><li>Période : <strong>{{date_start}} → {{date_end}}</strong></li></ul>',

            'provider_done_subject'  => 'Prestation réalisée : {{object_title}}',
            'provider_done_body'     => '<p>Le prestataire <strong>{{provider_name}}</strong> a confirmé la prestation.</p><ul><li>Prestation : <strong>{{object_title}}</strong></li><li>Période : <strong>{{date_start}} → {{date_end}}</strong></li></ul>',

            'itinerant_done_subject'  => 'Itinérant a confirmé : {{object_title}}',
            'itinerant_done_body'     => '<p>L\'itinérant <strong>{{display_name}}</strong> a confirmé le séjour.</p><ul><li>Prestation : <strong>{{object_title}}</strong></li><li>Période : <strong>{{date_start}} → {{date_end}}</strong></li></ul>',

            'pre_arrival_subject'  => 'Votre séjour commence dans {{days}} jours',
            'pre_arrival_body'     => '<p>Bonjour {{display_name}},</p><p>Votre séjour chez <strong>{{provider_name}}</strong> commence dans <strong>{{days}} jours</strong> (le {{date_start}}).</p><p><strong>Activité :</strong> {{object_title}}</p><p>Bon séjour !</p>',
        ];
    }

    public static function plan_label($key, $default = '—') {
        return self::PLANS[$key] ?? $default;
    }

    public static function role_label($key, $default = '—') {
        return self::ROLES[$key] ?? $default;
    }

    public static function status_label($key, $default = '—') {
        return self::STATUSES[$key] ?? $default;
    }

    public static function payment_label($key, $default = '—') {
        return self::PAYMENT_METHODS[$key] ?? $default;
    }

    public static function kind_label($key, $default = '—') {
        return self::KINDS[$key] ?? $default;
    }

    public static function get_cached_option($key, $default = '') {
    $cache_key = 'wpsd_opt_' . $key;
    $cached = get_transient($cache_key);
    if ($cached !== false) return $cached;
    
    $value = WPSD_Plugin::opt($key, $default);
    set_transient($cache_key, $value, 12 * HOUR_IN_SECONDS);
    return $value;
    }

    public static function invalidate_option_cache($key) {
        delete_transient('wpsd_opt_' . $key);
    }
}