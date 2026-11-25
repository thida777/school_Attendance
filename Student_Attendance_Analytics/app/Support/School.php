<?php

namespace App\Support;

use App\Models\Setting;

/**
 * The school's name, set by the admin on the School settings page.
 * Used on the website, in printed reports, in Telegram messages and (through the API) in the Android app.
 */
class School
{
    public const DEFAULT_EN = 'Demo High School';

    public const DEFAULT_KM = 'វិទ្យាល័យសាកល្បង';

    /** The name in a language ("en" or "km"); without one, the language of the page. */
    public static function name(?string $locale = null): string
    {
        return ($locale ?? app()->getLocale()) === 'km'
            ? Setting::get('school_name_km', self::DEFAULT_KM)
            : Setting::get('school_name_en', self::DEFAULT_EN);
    }

    /** "Khmer / English", the signature of messages to parents. */
    public static function signature(): string
    {
        return '— '.self::name('km').' / '.self::name('en');
    }
}
