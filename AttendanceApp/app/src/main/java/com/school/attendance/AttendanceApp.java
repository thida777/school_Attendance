package com.school.attendance;

import android.app.Application;

import androidx.appcompat.app.AppCompatDelegate;

import com.school.attendance.config.Session;

/**
 * Runs once when the app starts, before any screen.
 * Opens the saved login (Session) and applies the saved light / dark choice.
 */
public class AttendanceApp extends Application {

    @Override
    public void onCreate() {
        super.onCreate();
        Session.init(this);
        AppCompatDelegate.setDefaultNightMode(Session.getNightMode());
    }
}
