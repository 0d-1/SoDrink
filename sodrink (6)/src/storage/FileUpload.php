<?php
// src/storage/FileUpload.php
// Gestion d'upload d'images (validation MIME/taille, renommage unique)

declare(strict_types=1);

namespace SoDrink\Storage;

class FileUpload
{
    public static function ensureDir(string $dir): void {
        if (!is_dir($dir)) @mkdir($dir, 0775, true);
    }

    public static function fromImage(array $file, string $targetDir, int $maxMb, array $allowedMimes): array
    {
        if (!isset($file['error']) || is_array($file['error'])) {
            throw new \RuntimeException('Paramètres fichier invalides');
        }
        switch ($file['error']) {
            case UPLOAD_ERR_OK: break;
            case UPLOAD_ERR_NO_FILE: throw new \RuntimeException('Aucun fichier envoyé');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE: throw new \RuntimeException('Fichier trop volumineux');
            default: throw new \RuntimeException('Erreur upload');
        }
        $maxBytes = $maxMb * 1024 * 1024;
        if (($file['size'] ?? 0) > $maxBytes) throw new \RuntimeException('Fichier trop volumineux');

        // Détecte MIME réel
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);
        if (!in_array($mime, $allowedMimes, true)) throw new \RuntimeException('Type de fichier non autorisé');

        $ext = match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp',
            default      => 'bin',
        };

        self::ensureDir($targetDir);
        $basename = bin2hex(random_bytes(8)) . '-' . time();
        $filename = $basename . '.' . $ext;
        $dest = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR . $filename;

        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            throw new \RuntimeException('Échec déplacement du fichier');
        }
        @chmod($dest, 0664);

        return [
            'path' => $dest,
            'filename' => $filename,
            'mime' => $mime,
            'ext' => $ext,
            'size' => (int)$file['size'],
        ];
    }
}