<?php

declare(strict_types=1);

namespace Rarus\Echo\Enum;

/**
 * Supported languages for transcription
 */
enum Language: string
{
    /**
     * Automatic language detection (default)
     */
    case AUTO = 'auto';

    /**
     * Russian
     */
    case RU = 'ru';

    /**
     * English
     */
    case EN = 'en';

    /**
     * German
     */
    case DE = 'de';

    /**
     * French
     */
    case FR = 'fr';

    /**
     * Spanish
     */
    case ES = 'es';

    /**
     * Portuguese
     */
    case PT = 'pt';

    /**
     * Armenian
     */
    case HY = 'hy';

    /**
     * Japanese
     */
    case JA = 'ja';

    /**
     * Turkish
     */
    case TR = 'tr';

    /**
     * Arabic
     */
    case AR = 'ar';

    /**
     * Chinese
     */
    case ZH = 'zh';

    /**
     * Hebrew
     */
    case HE = 'he';

    /**
     * Vietnamese
     */
    case VI = 'vi';

    /**
     * Get all available language codes
     *
     * @return array<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Get language name in English
     */
    public function getName(): string
    {
        return match ($this) {
            self::AUTO => 'Auto-detect',
            self::RU => 'Russian',
            self::EN => 'English',
            self::DE => 'German',
            self::FR => 'French',
            self::ES => 'Spanish',
            self::PT => 'Portuguese',
            self::HY => 'Armenian',
            self::JA => 'Japanese',
            self::TR => 'Turkish',
            self::AR => 'Arabic',
            self::ZH => 'Chinese',
            self::HE => 'Hebrew',
            self::VI => 'Vietnamese',
        };
    }
}
