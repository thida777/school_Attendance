package com.school.attendance.config;

import androidx.appcompat.app.AppCompatDelegate;
import androidx.core.os.LocaleListCompat;

import java.util.Locale;

/** Settings of the app in one place. */
public final class Config {

    /**
     * Address of the Laravel API.
     * 10.0.2.2 = the computer, seen from the Android emulator (start Laravel with: php artisan serve).
     * Real phone on the same Wi-Fi: use the computer's IP, e.g. "http://192.168.1.20:8000/api/v1/",
     * start Laravel with: php artisan serve --host=0.0.0.0, and add the IP to res/xml/network_security_config.xml.
     */
    public static final String BASE_URL = "http://10.0.2.2:8000/api/v1/";

    private Config() {
    }

    /** "km" or "en": the language the app shows now. Sent to the API so its messages match. */
    public static String currentLanguage() {
        LocaleListCompat appLocales = AppCompatDelegate.getApplicationLocales();
        Locale locale = appLocales.isEmpty() ? Locale.getDefault() : appLocales.get(0);
        return locale != null && "km".equals(locale.getLanguage()) ? "km" : "en";
    }

    /** Switch the whole app to "en" or "km". Android redraws the open screen in the new language. */
    public static void setLanguage(String language) {
        AppCompatDelegate.setApplicationLocales(LocaleListCompat.forLanguageTags(language));
    }
}
