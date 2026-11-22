package com.school.attendance.activities;

import android.app.Activity;
import android.content.Intent;
import android.graphics.Color;
import android.os.Bundle;

import androidx.activity.EdgeToEdge;
import androidx.activity.SystemBarStyle;
import androidx.appcompat.app.AppCompatActivity;
import androidx.fragment.app.Fragment;

import com.google.android.material.bottomnavigation.BottomNavigationView;
import com.school.attendance.R;
import com.school.attendance.config.Session;
import com.school.attendance.fragments.AttendanceFragment;
import com.school.attendance.fragments.ClassesFragment;
import com.school.attendance.fragments.HistoryFragment;
import com.school.attendance.fragments.HomeFragment;
import com.school.attendance.fragments.ProfileFragment;

/**
 * The app's frame after login: one tab on screen, the 5-tab menu at the bottom
 * (Home, Classes, Attendance, History, Profile). Each tab is a Fragment.
 */
public class MainActivity extends AppCompatActivity {

    private BottomNavigationView bottomNav;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        // White status bar icons on the gradient headers; the navigation bar follows light / dark
        EdgeToEdge.enable(this, SystemBarStyle.dark(Color.TRANSPARENT));
        setContentView(R.layout.activity_main);

        if (Session.getUser() == null) {
            openLogin(this);
            return;
        }

        bottomNav = findViewById(R.id.bottomNav);
        bottomNav.setOnItemSelectedListener(item -> {
            showTab(item.getItemId());
            return true;
        });
        bottomNav.setOnItemReselectedListener(item -> { /* already open */ });

        // After a language or theme change Android rebuilds this screen and keeps the open tab by itself
        if (savedInstanceState == null) {
            showTab(R.id.nav_home);
        }
    }

    /** Opens a tab from code, e.g. the photo on the Dashboard opens Profile. */
    public void openTab(int tabId) {
        bottomNav.setSelectedItemId(tabId);
    }

    private void showTab(int tabId) {
        Fragment fragment;
        if (tabId == R.id.nav_profile) {
            fragment = new ProfileFragment();
        } else if (tabId == R.id.nav_classes) {
            fragment = new ClassesFragment();
        } else if (tabId == R.id.nav_attendance) {
            fragment = new AttendanceFragment();
        } else if (tabId == R.id.nav_history) {
            fragment = new HistoryFragment();
        } else {
            fragment = new HomeFragment();
        }
        getSupportFragmentManager().beginTransaction()
                .setReorderingAllowed(true)
                .replace(R.id.fragmentContainer, fragment)
                .commit();
    }

    /** Back to Login and close everything else (after Sign out). */
    public static void openLogin(Activity activity) {
        Intent intent = new Intent(activity, LoginActivity.class);
        intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK | Intent.FLAG_ACTIVITY_CLEAR_TASK);
        activity.startActivity(intent);
        activity.finish();
    }
}
