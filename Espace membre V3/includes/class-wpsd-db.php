<?php
if (!defined('ABSPATH')) exit;

class WPSD_DB {
  public static function table_family() {
    global $wpdb;
    return $wpdb->prefix . 'wpsd_family_members';
  }

  public static function table_parcours() {
    global $wpdb;
    return $wpdb->prefix . 'wpsd_parcours';
  }

  public static function table_etapes() {
    global $wpdb;
    return $wpdb->prefix . 'wpsd_etapes';
  }

  public static function table_member_cards() {
    global $wpdb;
    return $wpdb->prefix . 'wpsd_member_cards';
  }

  public static function activate() {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    $charset = $wpdb->get_charset_collate();
    $table = self::table_family();

    $sql = "CREATE TABLE $table (
      id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
      user_id BIGINT UNSIGNED NOT NULL,
      first_name VARCHAR(190) NOT NULL DEFAULT '',
      last_name  VARCHAR(190) NOT NULL DEFAULT '',
      email      VARCHAR(190) NOT NULL DEFAULT '',
      phone      VARCHAR(50)  NOT NULL DEFAULT '',
      birth_date DATE NULL,
      address_line1 VARCHAR(255) NOT NULL DEFAULT '',
      address_line2 VARCHAR(255) NOT NULL DEFAULT '',
      postal_code   VARCHAR(20)  NOT NULL DEFAULT '',
      city          VARCHAR(190) NOT NULL DEFAULT '',
      country       VARCHAR(2)   NOT NULL DEFAULT 'FR',
      lat DECIMAL(10,7) NULL,
      lng DECIMAL(10,7) NULL,
      bio_text TEXT NULL,
      photo_id BIGINT UNSIGNED NULL,
      created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      PRIMARY KEY (id),
      KEY user_id (user_id)
    ) $charset;";

    dbDelta($sql);
    
    
    $slots = self::table_slots();

$sql2 = "CREATE TABLE $slots (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(20) NOT NULL,
  object_id BIGINT UNSIGNED NOT NULL,
  date_start DATE NOT NULL,
  date_end DATE NOT NULL,
  capacity INT NULL,
  units INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY user_id (user_id),
  KEY kind_object (kind, object_id),
  KEY date_range (date_start, date_end)
) $charset;";

dbDelta($sql2);

$res = self::table_reservations();

$sql3 = "CREATE TABLE $res (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  itinerant_user_id BIGINT UNSIGNED NOT NULL,
  provider_user_id  BIGINT UNSIGNED NOT NULL,
  kind VARCHAR(20) NOT NULL,
  object_id BIGINT UNSIGNED NOT NULL,
  slot_id BIGINT UNSIGNED NOT NULL,
  date_start DATE NOT NULL,
  date_end   DATE NOT NULL,
  provider_done TINYINT(1) NOT NULL DEFAULT 0,
  itinerant_done TINYINT(1) NOT NULL DEFAULT 0,
  provider_done_at DATETIME NULL,
  itinerant_done_at DATETIME NULL,
  quantity INT NOT NULL DEFAULT 1,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  provider_note TEXT NULL,
  itinerant_note TEXT NULL,
  pre_arrival_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  approved_at  DATETIME NULL,
  rejected_at  DATETIME NULL,
  canceled_at  DATETIME NULL,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY itinerant_user_id (itinerant_user_id),
  KEY provider_user_id (provider_user_id),
  KEY kind_object (kind, object_id),
  KEY slot_id (slot_id),
  KEY status (status),
  KEY date_range (date_start, date_end)
) $charset;";

dbDelta($sql3);

$pending = self::table_pending_registrations();

$sql4 = "CREATE TABLE $pending (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    stripe_session_id VARCHAR(255) NOT NULL,
    stripe_customer_id VARCHAR(255) NOT NULL,
    stripe_subscription_id VARCHAR(255) NOT NULL,
    email VARCHAR(190) NOT NULL,
    nom VARCHAR(190) NOT NULL DEFAULT '',
    prenom VARCHAR(190) NOT NULL DEFAULT '',
    phone VARCHAR(50) NOT NULL DEFAULT '',
    role VARCHAR(50) NOT NULL DEFAULT '',
    plan VARCHAR(50) NOT NULL DEFAULT '',
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY email (email),
    KEY status (status),
    KEY stripe_session_id (stripe_session_id)
) $charset;";

$payments = $wpdb->prefix . 'wpsd_payments';

$sql_payments = "CREATE TABLE $payments (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    method VARCHAR(20) NOT NULL DEFAULT 'stripe',
    status VARCHAR(20) NOT NULL DEFAULT 'paid',
    plan VARCHAR(50) NOT NULL DEFAULT '',
    period_start DATE NULL,
    period_end DATE NULL,
    received_by BIGINT UNSIGNED NULL,
    reference VARCHAR(100) NULL DEFAULT '',
    notes TEXT NULL,
    invoice_number VARCHAR(50) NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY method (method),
    KEY status (status),
    KEY created_at (created_at)
) $charset;";

dbDelta($sql_payments);

    $drafts = $wpdb->prefix . 'wpsd_itinerary_drafts';

    $sql_drafts = "CREATE TABLE $drafts (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        data LONGTEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_id (user_id)
    ) $charset;";

    dbDelta($sql_drafts);

    // NOUVELLES TABLES
    // =================

    // Table parcours
    $parcours = self::table_parcours();
    $sql_parcours = "CREATE TABLE $parcours (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT UNSIGNED NOT NULL,
        date_debut_globale DATE NOT NULL,
        statut VARCHAR(30) NOT NULL DEFAULT 'draft',
        total_paye DECIMAL(10,2) NULL,
        payment_intent_id VARCHAR(255) NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY user_id (user_id),
        KEY statut (statut)
    ) $charset;";
    dbDelta($sql_parcours);

    // Table étapes
    $etapes = self::table_etapes();
    $sql_etapes = "CREATE TABLE $etapes (
        id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
        parcours_id BIGINT UNSIGNED NOT NULL,
        numero_ordre INT NOT NULL DEFAULT 0,
        duree INT NOT NULL DEFAULT 1,
        date_debut DATE NOT NULL,
        date_fin DATE NOT NULL,
        activity_id BIGINT UNSIGNED NULL,
        hebergement_id BIGINT UNSIGNED NULL,
        travel_mode VARCHAR(50) NULL,
        travel_days INT NOT NULL DEFAULT 0,
        travel_hours INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY parcours_id (parcours_id),
        KEY activity_id (activity_id),
        KEY hebergement_id (hebergement_id)
    ) $charset;";
    dbDelta($sql_etapes);

    // Table member_cards
    $cards = self::table_member_cards();
    $sql_cards = "CREATE TABLE $cards (
        user_id BIGINT UNSIGNED NOT NULL,
        photo_url VARCHAR(255) NULL,
        bio TEXT NULL,
        centre_interet VARCHAR(100) NULL,
        langues VARCHAR(255) NULL,
        visible_carte TINYINT(1) NOT NULL DEFAULT 1,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (user_id)
    ) $charset;";
    dbDelta($sql_cards);

    // Ajouter les index manquants
    $res = self::table_reservations();
    $wpdb->query("ALTER TABLE $res ADD INDEX date_start (date_start)");
    $wpdb->query("ALTER TABLE $res ADD INDEX date_end (date_end)");

    $slots = self::table_slots();
    $wpdb->query("ALTER TABLE $slots ADD INDEX object_kind (object_id, kind)");
  }

  /**
   * Migration douce : ajoute les colonnes manquantes à la table reservations
   * sans supprimer les données existantes.
   */
  public static function migrate_reservations_table() {
    global $wpdb;
    $table = self::table_reservations();
    $row = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'parcours_id'");
    if (empty($row)) {
      $wpdb->query("ALTER TABLE $table ADD COLUMN parcours_id BIGINT UNSIGNED NULL AFTER id, ADD INDEX idx_parcours_id (parcours_id)");
    }
    $row = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'etape_id'");
    if (empty($row)) {
      $wpdb->query("ALTER TABLE $table ADD COLUMN etape_id BIGINT UNSIGNED NULL AFTER parcours_id, ADD INDEX idx_etape_id (etape_id)");
    }
    $row = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'activity_id'");
    if (empty($row)) {
      $wpdb->query("ALTER TABLE $table ADD COLUMN activity_id BIGINT UNSIGNED NULL AFTER etape_id");
    }
    $row = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'hebergement_id'");
    if (empty($row)) {
      $wpdb->query("ALTER TABLE $table ADD COLUMN hebergement_id BIGINT UNSIGNED NULL AFTER activity_id");
    }
    // D'abord notes (colonne d'ancrage pour les suivantes)
    $row = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'notes'");
    if (empty($row)) {
      $wpdb->query("ALTER TABLE $table ADD COLUMN notes TEXT NULL AFTER hebergement_id");
    }
    $row = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'confirmation_itinérant'");
    if (empty($row)) {
      $wpdb->query("ALTER TABLE $table ADD COLUMN confirmation_itinérant TINYINT(1) NOT NULL DEFAULT 0 AFTER notes");
    }
    $row = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'confirmation_prestataire'");
    if (empty($row)) {
      $wpdb->query("ALTER TABLE $table ADD COLUMN confirmation_prestataire TINYINT(1) NOT NULL DEFAULT 0 AFTER confirmation_itinérant");
    }
    $row = $wpdb->get_results("SHOW COLUMNS FROM $table LIKE 'payment_intent_id'");
    if (empty($row)) {
      $wpdb->query("ALTER TABLE $table ADD COLUMN payment_intent_id VARCHAR(255) NULL AFTER confirmation_prestataire");
    }
    $wpdb->query("ALTER TABLE $table MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'pending'");

    update_option('wpsd_migrated_reservations_v2', 1);
  }

  public static function table_slots() {
    global $wpdb;
    return $wpdb->prefix . 'wpsd_availability_slots';
  }

  public static function table_itinerary_drafts() {
    global $wpdb;
    return $wpdb->prefix . 'wpsd_itinerary_drafts';
  }

  public static function table_reservations() {
    global $wpdb;
    return $wpdb->prefix . 'wpsd_reservations';
  }

  public static function table_pending_registrations() {
    global $wpdb;
    return $wpdb->prefix . 'wpsd_pending_registrations';
  }

  public static function table_payments() {
    global $wpdb;
    return $wpdb->prefix . 'wpsd_payments';
  }
}