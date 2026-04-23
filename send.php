<?php
/**
 * Atelier du Guidon — Traitement formulaire RDV
 * - Sauvegarde en base Supabase (table rdv_demandes)
 * - Envoi email de notification (PHP mail)
 * Répondre JSON : {"ok":true} ou {"ok":false,"error":"..."}
 */

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/config.php';

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

// ── 2. Notification email via Mailjet (best-effort) ────────
$mjSubject = '[RDV] Demande de rendez-vous — ' . $service;

$mjTextBody  = "Nouvelle demande de rendez-vous :\n\n";
$mjTextBody .= "Nom       : {$nom}\n";
$mjTextBody .= "Téléphone : {$tel}\n";
$mjTextBody .= "Email     : {$email}\n";
$mjTextBody .= "Service   : {$service}\n";
$mjTextBody .= "Message   :\n{$message}\n";
$mjTextBody .= "\n---\nEnvoyé depuis le site Atelier du Guidon\n";
$mjTextBody .= "Voir tous les RDV : https://supabase.com/dashboard/project/oozngkpfjdvmkounialr/editor\n";

$mjPayload = json_encode([
    'Messages' => [[
        'From'     => ['Email' => MJ_FROM_EMAIL, 'Name' => MJ_FROM_NAME],
        'To'       => [['Email' => MJ_TO_EMAIL]],
        'ReplyTo'  => ['Email' => $email, 'Name' => $nom],
        'Subject'  => $mjSubject,
        'TextPart' => $mjTextBody,
    ]],
]);

$mjCh = curl_init('https://api.mailjet.com/v3.1/send');
curl_setopt_array($mjCh, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $mjPayload,
    CURLOPT_USERPWD        => MJ_APIKEY_PUBLIC . ':' . MJ_APIKEY_PRIVATE,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT        => 10,
]);
curl_exec($mjCh); // best-effort, pas bloquant
curl_close($mjCh);

echo json_encode(['ok' => true]);
