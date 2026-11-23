package com.school.attendance.config;

import androidx.appcompat.app.AppCompatDelegate;
import androidx.core.os.LocaleListCompat;

import java.util.Locale;

/** Settings of the app in one place. */
public final class Config {

    /**
     * Address of the Laravel API: the website online (alwaysdata, HTTPS), so the app works on any phone.
     * To test against the computer instead (emulator + php artisan serve), use LOCAL_URL.
     */
    public static final String ONLINE_URL = "https://demohs-attendance.alwaysdata.net/api/v1/";

    /** 10.0.2.2 = the computer, seen from the Android emulator. Plain http is allowed only for it (network_security_config.xml). */
    public static final String LOCAL_URL = "http://10.0.2.2:8000/api/v1/";

    public static final String BASE_URL = ONLINE_URL;

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
