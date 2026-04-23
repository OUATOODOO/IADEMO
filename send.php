<?php
/**
 * Atelier du Guidon — Traitement formulaire RDV
 * - Sauvegarde en base Supabase (table rdv_demandes)
 * - Envoi email de notification (PHP mail)
 * Répondre JSON : {"ok":true} ou {"ok":false,"error":"..."}
 */

header('Content-Type: application/json; charset=UTF-8');

// ── Configuration Supabase ─────────────────────────────────
define('SUPABASE_URL',     'https://oozngkpfjdvmkounialr.supabase.co');
define('SUPABASE_ANON_KEY','eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Im9vem5na3BmamR2bWtvdW5pYWxyIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzY4OTkzMTAsImV4cCI6MjA5MjQ3NTMxMH0.iCvamljc5WswvSthO7fqmFFvtiHFoT8jr_kxoKOlH6k');

// Bloquer les requêtes non-POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Sanitize helper
function clean(string $val): string {
    return htmlspecialchars(strip_tags(trim($val)), ENT_QUOTES, 'UTF-8');
}

// Champs requis
$nom     = clean($_POST['nom']     ?? '');
$tel     = clean($_POST['tel']     ?? '');
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
$service = clean($_POST['service'] ?? '');
$message = clean($_POST['message'] ?? '');

// Validation basique
if ($nom === '' || $tel === '' || $email === '' || $service === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Champs obligatoires manquants.']);
    exit;
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Adresse email invalide.']);
    exit;
}

// ── 1. Sauvegarde Supabase ─────────────────────────────────
$payload = json_encode([
    'nom'     => $nom,
    'tel'     => $tel,
    'email'   => $email,
    'service' => $service,
    'message' => $message,
    'statut'  => 'nouveau',
]);

$ch = curl_init(SUPABASE_URL . '/rest/v1/rdv_demandes');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'apikey: '        . SUPABASE_ANON_KEY,
        'Authorization: Bearer ' . SUPABASE_ANON_KEY,
        'Prefer: return=minimal',
    ],
    CURLOPT_TIMEOUT => 10,
]);
$sbResponse = curl_exec($ch);
$sbHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($sbHttpCode >= 300) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erreur base de données. Veuillez nous appeler directement.']);
    exit;
}

// ── 2. Notification email (best-effort) ───────────────────
$to      = 'contact@atelierduguidon.fr';
$subject = '[RDV] Demande de rendez-vous — ' . $service;

$body  = "Nouvelle demande de rendez-vous :\n\n";
$body .= "Nom       : {$nom}\n";
$body .= "Téléphone : {$tel}\n";
$body .= "Email     : {$email}\n";
$body .= "Service   : {$service}\n";
$body .= "Message   :\n{$message}\n";
$body .= "\n---\nEnvoyé depuis le site Atelier du Guidon\n";
$body .= "Voir tous les RDV : https://supabase.com/dashboard/project/oozngkpfjdvmkounialr/editor\n";

$headers  = "From: noreply@atelierduguidon.fr\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

mail($to, $subject, $body, $headers); // best-effort, pas bloquant

echo json_encode(['ok' => true]);
