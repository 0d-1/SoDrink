<?php
declare(strict_types=1);

namespace SoDrink\Domain;

final class NotificationPreferences
{
    /**
     * Keys exposed to the UI. All boolean by nature.
     *
     * @var array<string,bool>
     */
    private const DEFAULTS = [
        'messages'      => true,
        'events'        => true,
        'gallery'       => true,
        'torpille'      => true,
        'announcements' => true,
    ];

    /**
     * Mapping from notification type to preference key.
     *
     * @var array<string,string>
     */
    private const TYPE_MAP = [
        'chat_message'     => 'messages',
        'event_created'    => 'events',
        'event_updated'    => 'events',
        'event_deleted'    => 'events',
        'event_join'       => 'events',
        'gallery_like'     => 'gallery',
        'gallery_comment'  => 'gallery',
        'gallery_created'  => 'gallery',
        'gallery_updated'  => 'gallery',
        'gallery_deleted'  => 'gallery',
        'torpille'         => 'torpille',
        'admin_broadcast'  => 'announcements',
    ];

    /**
     * Return the default preferences.
     *
     * @return array<string,bool>
     */
    public static function defaults(): array
    {
        return self::DEFAULTS;
    }

    /**
     * Normalize raw preferences to known keys, coercing values to bools.
     *
     * @param array<string,mixed>|null $raw
     * @return array<string,bool>
     */
    public static function normalize(?array $raw): array
    {
        $normalized = self::DEFAULTS;
        if (!is_array($raw)) {
            return $normalized;
        }
        foreach ($normalized as $key => $default) {
            if (array_key_exists($key, $raw)) {
                $normalized[$key] = (bool)$raw[$key];
            }
        }
        return $normalized;
    }

    public static function typeToKey(string $type): string
    {
        return self::TYPE_MAP[$type] ?? 'announcements';
    }

    /**
     * Determine if the provided raw preferences allow a given notification type.
     */
    public static function allows(?array $raw, string $type): bool
    {
        $settings = self::normalize($raw);
        $key = self::typeToKey($type);
        return $settings[$key] ?? true;
    }

    /**
     * Convenience wrapper for a full user record.
     */
    public static function userAllows(?array $user, string $type): bool
    {
        if (!$user || !isset($user['id'])) {
            return false;
        }
        $raw = isset($user['notification_settings']) && is_array($user['notification_settings'])
            ? $user['notification_settings']
            : null;
        return self::allows($raw, $type);
    }
}
