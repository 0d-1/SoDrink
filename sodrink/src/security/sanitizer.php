<?php
// src/security/sanitizer.php
// Nettoyage et validations d'inputs

declare(strict_types=1);

namespace SoDrink\Security;

function safe_text(string $s, int $max = 255): string {
    $s = trim($s);
    $s = preg_replace('/[[:cntrl:]]/u', '', $s); // évite \x00.. sans backslashs
    $s = strip_tags($s);
    if ($max > 0) $s = mb_substr($s, 0, $max);
    return $s;
}

function safe_multiline(string $s, int $max = 500): string {
    $s = trim(str_replace(["\r\n", "\r"], "\n", $s));
    // Retire les caractères de contrôle, mais conserve les retours à la ligne
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $s);
    $s = strip_tags($s);
    if ($max > 0) $s = mb_substr($s, 0, $max);
    return $s;
}

function sanitize_url(?string $url): ?string {
    if ($url === null) return null;
    $url = trim($url);
    if ($url === '') return null;
    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://' . $url;
    }
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    return $url;
}

function valid_pseudo(string $s): bool {
    return (bool)preg_match('/^[A-Za-z0-9_.-]{3,20}$/', $s);
}

function valid_name(string $s): bool {
    return (bool)preg_match('/^[A-Za-zÀ-ÖØ-öø-ÿ\-"\s]{1,40}$/u', $s);
}

function valid_password(string $s): bool {
    return mb_strlen($s) >= 8; // simplicité : 8+ caractères
}

function sanitize_instagram(?string $h): ?string {
    if (!$h) return null;
    $h = trim($h);
    if ($h === '') return null;
    $h = ltrim($h, '@');
    $h = strtolower($h);
    if (!preg_match('/^[a-z0-9_.]{1,30}$/', $h)) return null;
    return $h;
}