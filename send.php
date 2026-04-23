<?php
/**
 * Atelier du Guidon — Traitement formulaire RDV
 * Répondre JSON : {"ok":true} ou {"ok":false,"error":"..."}
 */

header('Content-Type: application/json; charset=UTF-8');

// Bloquer les requêtes non-POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method Not Allowed']);
    exit;
}

// Sanitize helpers
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

// Destinataire — à remplacer par l'adresse réelle de l'atelier
$to      = 'contact@atelierduguidon.fr';
$subject = '[RDV] Demande de rendez-vous — ' . $service;

$body  = "Nouvelle demande de rendez-vous :\n\n";
$body .= "Nom       : {$nom}\n";
$body .= "Téléphone : {$tel}\n";
$body .= "Email     : {$email}\n";
$body .= "Service   : {$service}\n";
$body .= "Message   :\n{$message}\n";
$body .= "\n---\nEnvoyé depuis le site Atelier du Guidon\n";

$headers  = "From: noreply@atelierduguidon.fr\r\n";
$headers .= "Reply-To: {$email}\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

$sent = mail($to, $subject, $body, $headers);

if ($sent) {
    echo json_encode(['ok' => true]);
} else {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Erreur lors de l\'envoi. Veuillez nous appeler directement.']);
}
