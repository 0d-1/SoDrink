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