<?php
// src/utils/secrets.php
// Helpers pour chiffrer/déchiffrer des secrets applicatifs

declare(strict_types=1);

namespace SoDrink\Utils\Secrets;

use RuntimeException;

/**
 * Récupère la clé de chiffrement à partir de la variable d'environnement.
 * Nécessite UCLSPORT_SECRET_KEY définie côté serveur.
 */
function get_secret_key(): string
{
    $raw = \env('UCLSPORT_SECRET_KEY');
    if (!$raw) {
        throw new RuntimeException('La clé de chiffrement UCLSPORT_SECRET_KEY est manquante.');
    }
    // Normalise sur 32 octets pour AES-256
    return hash('sha256', (string)$raw, true);
}

/**
 * Chiffre une chaîne en utilisant AES-256-GCM et retourne une représentation base64.
 */
function encrypt_secret(string $plain): string
{
    if ($plain === '') {
        return '';
    }

    $key = get_secret_key();
    $iv = random_bytes(12); // recommandé pour GCM
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false || $tag === '') {
        throw new RuntimeException('Chiffrement impossible.');
    }

    return base64_encode($iv . $tag . $cipher);
}

/**
 * Déchiffre une valeur générée via encrypt_secret().
 */
function decrypt_secret(?string $encoded): ?string
{
    if ($encoded === null || $encoded === '') {
        return null;
    }

    $blob = base64_decode($encoded, true);
    if ($blob === false || strlen($blob) <= 28) {
        throw new RuntimeException('Secret corrompu.');
    }

    $iv = substr($blob, 0, 12);
    $tag = substr($blob, 12, 16);
    $cipher = substr($blob, 28);

    $key = get_secret_key();
    $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($plain === false) {
        throw new RuntimeException('Déchiffrement impossible.');
    }

    return $plain;
}

