<?php
if (!defined('ABSPATH')) exit;

require_once WPSD_PATH . 'includes/trait-wpsd-helpers.php';

class WPSD_Invoices {

  const ACTION = 'wpsd_invoice_download';

  use WPSD_Helpers;

  public function __construct() {
    add_action('admin_post_wpsd_invoice', [$this, 'handle_admin_invoice']);
  }

  /* ===============================
   * Helpers settings
   * =============================== */

  private function money_eur($cents) {
    $c = (int)$cents;
    return number_format($c / 100, 2, ',', ' ') . ' €';
  }

  private function invoice_number_for_reservation($rid) {
    $prefix = trim((string)$this->opt('invoice_prefix', 'INV'));
    $year = date('Y');
    // numéro simple et stable (tu peux changer plus tard)
    return sprintf('%s-%s-%06d', $prefix, $year, (int)$rid);
  }

  private function get_logo_url() {
    $logo_id = (int)$this->opt('association_logo_id', 0);
    if ($logo_id > 0) {
      $u = wp_get_attachment_image_url($logo_id, 'medium');
      if ($u) return $u;
    }
    return '';
  }

  private function reservation_row($rid) {
    global $wpdb;
    $table = WPSD_DB::table_reservations();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id=%d", (int)$rid), ARRAY_A);
  }

  private function get_user_block($uid) {
    $u = get_userdata((int)$uid);
    if (!$u) return ['name'=>'', 'email'=>'', 'addr'=>''];

    $first = get_user_meta($uid, 'first_name', true);
    $last  = get_user_meta($uid, 'last_name', true);
    $name = trim($first.' '.$last);
    if ($name === '') $name = $u->display_name ?: $u->user_login;

    $line1 = get_user_meta($uid, 'address_line1', true);
    $line2 = get_user_meta($uid, 'address_line2', true);
    $pc    = get_user_meta($uid, 'postal_code', true);
    $city  = get_user_meta($uid, 'city', true);
    $country = get_user_meta($uid, 'country', true);

    $addr = [];
    if ($line1) $addr[] = $line1;
    if ($line2) $addr[] = $line2;
    $lastLine = trim($pc.' '.$city);
    if ($lastLine) $addr[] = $lastLine;
    if ($country) $addr[] = $country;

    return [
      'name' => $name,
      'email' => $u->user_email,
      'addr' => implode("<br>", array_map('esc_html', $addr)),
    ];
  }

  /* ===============================
   * HTML invoice
   * =============================== */
  private function build_invoice_html($r) {
    $asso_name = (string)$this->opt('association_name', 'Association');
    $asso_addr = (string)$this->opt('association_address', '');
    $asso_email = (string)$this->opt('association_email', '');
    $asso_siret = (string)$this->opt('association_siret', '');
    $logo = $this->get_logo_url();

    $provider = $this->get_user_block($r['provider_user_id']);
    $itinerant = $this->get_user_block($r['itinerant_user_id']);

    $invoice_no = $this->invoice_number_for_reservation($r['id']);
    $invoice_date = date_i18n('d/m/Y');

    $object_title = get_the_title((int)$r['object_id']);
    $kindLabel = WPSD_Data::kind_label($r['kind'], ucfirst($r['kind']));

$unit_cents = (int)$this->opt('invoice_unit_price_cents', 2000); // 25€ par place
$qty = max(1, (int)$r['quantity']);
$total = $unit_cents * $qty;

    $dateLine = ($r['date_start'] === $r['date_end']) ? esc_html($r['date_start']) : esc_html($r['date_start'].' → '.$r['date_end']);

    ob_start();
    ?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>Facture <?php echo esc_html($invoice_no); ?></title>
  <style>
    body{font-family:system-ui,-apple-system,Segoe UI,Roboto,Ubuntu,Arial; color:#111; background:#fff; margin:0;}
    .page{max-width:900px;margin:24px auto;padding:24px;border:1px solid #eee;border-radius:14px;}
    .top{display:flex;justify-content:space-between;gap:16px;align-items:flex-start;}
    .logo{max-width:160px;height:auto;}
    h1{font-size:20px;margin:0 0 6px;}
    .muted{opacity:.75;font-size:13px;line-height:1.35;}
    .box{border:1px solid #eee;border-radius:12px;padding:14px;}
    .grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px;}
    table{width:100%;border-collapse:collapse;margin-top:14px;}
    th,td{border-bottom:1px solid #eee;padding:10px;text-align:left;vertical-align:top;}
    th{background:#fafafa;}
    .right{text-align:right;}
    .tot{font-weight:700;}
    .badge{display:inline-block;padding:4px 8px;border-radius:999px;background:#f3f4f6;font-size:12px;}
    .printbar{display:flex;gap:10px;justify-content:flex-end;margin-top:12px;}
    .btn{padding:10px 14px;border-radius:10px;border:1px solid #ddd;background:#fff;cursor:pointer;}
    .btn.primary{background:#111;color:#fff;border-color:#111;}
    @media print{
      .page{border:none;margin:0;border-radius:0;}
      .printbar{display:none;}
    }
  </style>
</head>
<body>
  <div class="page">

    <div class="top">
      <div>
        <h1>Facture <span class="badge"><?php echo esc_html($invoice_no); ?></span></h1>
        <div class="muted">Date : <?php echo esc_html($invoice_date); ?></div>
        <div class="muted">Objet : <?php echo esc_html($kindLabel); ?> — <?php echo esc_html($object_title ?: ('#'.$r['object_id'])); ?></div>
        <div class="muted">Période : <?php echo $dateLine; ?> — Qté : <?php echo (int)$r['quantity']; ?></div>
      </div>

      <div style="text-align:right">
        <?php if ($logo): ?>
          <img class="logo" src="<?php echo esc_url($logo); ?>" alt="Logo">
        <?php endif; ?>
        <div style="margin-top:10px;font-weight:700"><?php echo esc_html($asso_name); ?></div>
        <div class="muted">
          <?php echo nl2br(esc_html($asso_addr)); ?><br>
          <?php if ($asso_email) echo esc_html($asso_email).'<br>'; ?>
          <?php if ($asso_siret) echo 'SIRET : '.esc_html($asso_siret); ?>
        </div>
      </div>
    </div>

    <div class="grid">
      <div class="box">
        <div style="font-weight:700;margin-bottom:6px;">Bénéficiaire (prestataire)</div>
        <div><?php echo esc_html($provider['name']); ?></div>
        <div class="muted"><?php echo esc_html($provider['email']); ?></div>
        <div class="muted" style="margin-top:6px;"><?php echo $provider['addr']; ?></div>
      </div>

<div class="box">
  <div style="font-weight:700;margin-bottom:6px;">Payeur</div>
  <div><?php echo esc_html($asso_name); ?></div>
  <div class="muted">
    <?php echo nl2br(esc_html($asso_addr)); ?><br>
    <?php if ($asso_email) echo esc_html($asso_email).'<br>'; ?>
    <?php if ($asso_siret) echo 'SIRET : '.esc_html($asso_siret); ?>
  </div>
</div>
    </div>

    <table>
      <thead>
        <tr>
          <th>Désignation</th>
          <th class="right">Montant</th>
        </tr>
      </thead>
<tbody>
  <tr>
    <td>
      Prestation <?php echo esc_html($kindLabel); ?> — <?php echo esc_html($object_title ?: ('#'.$r['object_id'])); ?><br>
      <span class="muted">Période : <?php echo $dateLine; ?></span>
    </td>
    <td class="right">
      <?php echo esc_html($this->money_eur($unit_cents)); ?> × <?php echo (int)$qty; ?>
    </td>
  </tr>

  <tr>
    <td class="tot">Total à payer</td>
    <td class="right tot"><?php echo esc_html($this->money_eur($total)); ?></td>
  </tr>
</tbody>
    </table>

    <div class="muted" style="margin-top:14px;">
      Réservation #<?php echo (int)$r['id']; ?> — Statut : <?php echo esc_html($r['status']); ?><br>
      Confirmation prestataire : <?php echo ((int)$r['provider_done'] === 1) ? 'OK' : 'NON'; ?> —
      Confirmation itinérant : <?php echo ((int)$r['itinerant_done'] === 1) ? 'OK' : 'NON'; ?>
    </div>

    <div class="printbar">
      <button class="btn" onclick="window.print()">Imprimer / Enregistrer en PDF</button>
    </div>
  </div>
</body>
</html>
<?php
    return ob_get_clean();
  }

  /* ===============================
   * PDF generation (optional Dompdf)
   * =============================== */
  private function dompdf_available() {
    return class_exists('\Dompdf\Dompdf');
  }

  private function output_pdf_with_dompdf($html, $filename) {
    // Dompdf must be loaded by your plugin (vendor/autoload.php)
    $dompdf = new \Dompdf\Dompdf();
    $dompdf->loadHtml($html, 'UTF-8');
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="'.$filename.'"');
    echo $dompdf->output();
    exit;
  }

  /* ===============================
   * Controller admin-post
   * =============================== */
  public function handle_admin_invoice() {

    if (!current_user_can('manage_options')) {
      wp_die('Forbidden', 403);
    }

    $rid = isset($_GET['rid']) ? (int)$_GET['rid'] : 0;
    $type = isset($_GET['type']) ? sanitize_text_field($_GET['type']) : 'html';

    check_admin_referer(self::ACTION);

    if ($rid <= 0) wp_die('RID invalide', 400);

    $r = $this->reservation_row($rid);
    if (!$r) wp_die('Réservation introuvable', 404);

    // On génère facture uniquement si completed (tes règles)
    if ($r['status'] !== 'completed') {
      wp_die('Facture disponible uniquement quand la réservation est terminée (completed).', 409);
    }

    $html = $this->build_invoice_html($r);
    $invoice_no = $this->invoice_number_for_reservation($rid);
    $filename = $invoice_no . '.pdf';

    if ($type === 'pdf') {
      if ($this->dompdf_available()) {
        $this->output_pdf_with_dompdf($html, $filename);
      }

      // fallback: HTML imprimable (l’utilisateur enregistrera en PDF)
      header('Content-Type: text/html; charset=UTF-8');
      echo $html;
      exit;
    }

    // HTML
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
  }
}
