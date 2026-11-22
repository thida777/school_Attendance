package com.school.attendance.config;

import android.content.Context;
import android.content.SharedPreferences;

import androidx.appcompat.app.AppCompatDelegate;

import com.google.gson.Gson;
import com.school.attendance.models.User;

/**
 * What the phone remembers (SharedPreferences): the login token, the teacher, "Remember me"
 * and the light / dark choice. Attendance itself is never stored here: it always comes from the server.
 */
public final class Session {

    private static final String PREFS = "attendance_session";
    private static final String KEY_TOKEN = "token";
    private static final String KEY_USER = "user";
    private static final String KEY_REMEMBER = "remember";
    private static final String KEY_NIGHT_MODE = "night_mode";

    private static SharedPreferences prefs;
    private static final Gson gson = new Gson();

    private Session() {
    }

    /** Called once in AttendanceApp.onCreate(). */
    public static void init(Context context) {
        prefs = context.getApplicationContext().getSharedPreferences(PREFS, Context.MODE_PRIVATE);
    }

    public static void saveLogin(String token, User user, boolean remember) {
        prefs.edit()
                .putString(KEY_TOKEN, token)
                .putString(KEY_USER, gson.toJson(user))
                .putBoolean(KEY_REMEMBER, remember)
                .apply();
    }

    public static String getToken() {
        return prefs.getString(KEY_TOKEN, null);
    }

    public static boolean isLoggedIn() {
        return getToken() != null;
    }

    /** False = the teacher unticked "Remember me": the login ends when the app is closed. */
    public static boolean isRemembered() {
        return prefs.getBoolean(KEY_REMEMBER, true);
    }

    public static User getUser() {
        String json = prefs.getString(KEY_USER, null);
        return json == null ? null : gson.fromJson(json, User.class);
    }

    public static void saveUser(User user) {
        prefs.edit().putString(KEY_USER, gson.toJson(user)).apply();
    }

    /** Log out on this phone: forget the token and the teacher (the light / dark choice stays). */
    public static void clear() {
        prefs.edit().remove(KEY_TOKEN).remove(KEY_USER).remove(KEY_REMEMBER).apply();
    }

    public static int getNightMode() {
        return prefs.getInt(KEY_NIGHT_MODE, AppCompatDelegate.MODE_NIGHT_FOLLOW_SYSTEM);
    }

    public static void setNightMode(int mode) {
        prefs.edit().putInt(KEY_NIGHT_MODE, mode).apply();
        AppCompatDelegate.setDefaultNightMode(mode);
    }
}
