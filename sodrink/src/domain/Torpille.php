<?php
declare(strict_types=1);

namespace SoDrink\Domain;

use SoDrink\Storage\JsonStore;

class Torpille
{
    private JsonStore $photos;
    private string $stateFile;
    private string $uploadDir;
    private string $uploadWebBase;

    public function __construct(?string $photosFile = null, ?string $stateFile = null)
    {
        // __DIR__ = .../src/domain -> $root = .../src
        $root = realpath(__DIR__ . '/..');
        $this->photos    = new JsonStore($photosFile ?: ($root . '/../data/torpille.json'));
        $this->stateFile = $stateFile ?: ($root . '/../data/torpille_state.json'); // <- fix ici (suppr d’une parenthèse)

        // Mettre les fichiers dans public/uploads/torpille (servables via le web)
        $projectRoot = dirname((string)$root);
        $this->uploadDir = $projectRoot . '/public/uploads/torpille';
        if (!is_dir($this->uploadDir)) @mkdir($this->uploadDir, 0775, true);

        // URL publique
        $this->uploadWebBase = (defined('WEB_BASE') ? WEB_BASE : '') . '/uploads/torpille';
    }

    /* ---------- State helpers ---------- */

    private function loadState(): array
    {
        if (!is_file($this->stateFile)) {
            $s = ['current_user_id' => null, 'sequence' => 0, 'updated_at' => date('c')];
            file_put_contents($this->stateFile, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $s;
        }
        $s = json_decode((string)file_get_contents($this->stateFile), true) ?: [];
        $s['current_user_id'] = isset($s['current_user_id']) ? (int)$s['current_user_id'] : null;
        $s['sequence'] = (int)($s['sequence'] ?? 0);
        return $s;
    }

    private function saveState(array $s): void
    {
        $s['updated_at'] = date('c');
        file_put_contents($this->stateFile, json_encode($s, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function getState(): array { return $this->loadState(); }
    public function currentUserId(): ?int { $s = $this->loadState(); return $s['current_user_id'] ?: null; }
    public function nextSeq(): int { $s = $this->loadState(); return (int)$s['sequence'] + 1; }

    public function setInitial(int $userId): void
    {
        $s = $this->loadState();
        $s['current_user_id'] = $userId;
        $this->saveState($s);
    }

    /* ---------- Photos & listing ---------- */

    /** Liste paginée — 2 par page */
    public function listPhotos(int $page = 1, int $perPage = 2): array
    {
        $all = $this->photos->getAll();
        usort($all, fn($a,$b)=>($b['id']??0)<=>($a['id']??0));
        $total = count($all);
        $pages = max(1, (int)ceil($total / max(1, $perPage)));
        $page = max(1, min($page, $pages));
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($all, $offset, $perPage);
        return [
            'items' => $slice,
            'page' => $page,
            'pages' => $pages,
            'total' => $total,
        ];
    }

    public function latest(): ?array
    {
        $all = $this->photos->getAll();
        if (!$all) return null;
        usort($all, fn($a,$b)=>($b['id']??0)<=>($a['id']??0));
        return $all[0];
    }

    /* ---------- Upload + watermark + pass ---------- */

    private function loadImage(string $path): array
    {
        $info = @getimagesize($path);
        if (!$info) throw new \RuntimeException('Image invalide');
        $type = $info[2];
        if ($type === IMAGETYPE_JPEG) $img = imagecreatefromjpeg($path);
        elseif ($type === IMAGETYPE_PNG) $img = imagecreatefrompng($path);
        elseif ($type === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) $img = imagecreatefromwebp($path);
        else throw new \RuntimeException('Format non supporté');
        if (!$img) throw new \RuntimeException('Ouverture image échouée');
        return [$img, imagesx($img), imagesy($img), $type];
    }

    private function processAndSave(string $tmpPath, int $seq): array
    {
        [$im, $w, $h] = $this->loadImage($tmpPath);

        $ratio = 3 / 4; // largeur / hauteur

        // Recadrage centré pour garantir le ratio 3:4
        $cropW = $w;
        $cropH = (int)round($w / $ratio);
        $cropX = 0;
        $cropY = 0;
        if ($cropH > $h) {
            $cropH = $h;
            $cropW = (int)round($h * $ratio);
            $cropX = (int)max(0, floor(($w - $cropW) / 2));
        } else {
            $cropY = (int)max(0, floor(($h - $cropH) / 2));
        }

        $maxWidth = 1200;
        $destW = min($cropW, $maxWidth);
        $destH = (int)round($destW / $ratio);
        if ($destH > $cropH) {
            $destH = $cropH;
            $destW = (int)round($destH * $ratio);
        }

        $canvas = imagecreatetruecolor($destW, $destH);
        imagecopyresampled($canvas, $im, 0, 0, $cropX, $cropY, $destW, $destH, $cropW, $cropH);

        // Filigrane plus discret avec fond semi-transparent
        $margin = max(12, (int)round(min($destW, $destH) * 0.025));
        $text = '#' . $seq;
        $font = 5; // bitmap font GD
        $tw = imagefontwidth($font) * strlen($text);
        $th = imagefontheight($font);
        $pad = (int)round($margin * 0.6);

        $x1 = $destW - $tw - $pad * 2 - $margin;
        $y1 = $destH - $th - $pad * 2 - $margin;
        $x2 = $destW - $margin;
        $y2 = $destH - $margin;

        imagealphablending($canvas, true);
        $bg = imagecolorallocatealpha($canvas, 0, 0, 0, 70);
        imagefilledrectangle($canvas, $x1, $y1, $x2, $y2, $bg);

        $white = imagecolorallocate($canvas, 255, 255, 255);
        imagestring($canvas, $font, $x1 + $pad, $y1 + $pad, $text, $white);

        $name = 'torpille_' . time() . '_' . bin2hex(random_bytes(3)) . '.jpg';
        $dest = $this->uploadDir . '/' . $name;
        imageinterlace($canvas, true);
        imagejpeg($canvas, $dest, 82);

        imagedestroy($canvas);
        imagedestroy($im);

        $web = $this->uploadWebBase . '/' . $name;
        return [$dest, $web];
    }

    public function passWithPhoto(int $userId, string $tmpFile, string $origName, int $nextUserId): array
    {
        $state = $this->loadState();
        if ((int)($state['current_user_id'] ?? 0) !== $userId) {
            throw new \RuntimeException("Vous n'êtes pas la personne torpillée.");
        }
        if ($nextUserId <= 0 || $nextUserId === $userId) {
            throw new \RuntimeException("Choix du prochain torpillé invalide.");
        }

        $seq = $this->nextSeq();
        [$abs, $web] = $this->processAndSave($tmpFile, $seq);

        $rec = [
            'user_id'    => $userId,
            'path'       => $web,
            'filename'   => basename($abs),
            'seq'        => $seq,
            'created_at' => date('c'),
        ];
        $rec = $this->photos->append($rec);

        $state['sequence'] = $seq;
        $state['current_user_id'] = $nextUserId;
        $this->saveState($state);

        return $rec;
    }
}
