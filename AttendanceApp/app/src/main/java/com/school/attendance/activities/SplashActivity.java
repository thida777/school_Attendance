package com.school.attendance.activities;

import android.content.Intent;
import android.graphics.Color;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.os.SystemClock;
import android.view.View;
import android.view.ViewGroup;

import androidx.activity.EdgeToEdge;
import androidx.activity.SystemBarStyle;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

import com.school.attendance.R;
import com.school.attendance.config.Session;
import com.school.attendance.config.RetrofitInstance;
import com.school.attendance.models.UserResponse;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * First screen. Shows the logo for a moment, then:
 *   - no saved login            → Login
 *   - saved login still valid   → Home (the teacher is refreshed from the server)
 *   - token no longer valid     → Login, with "Your session has ended"
 *   - server cannot be reached  → Home with the saved teacher (each screen shows the error later)
 */
public class SplashActivity extends AppCompatActivity {

    /** Show the logo at least this long, so the screen does not just flash. */
    private static final long MIN_SHOW_MS = 1200;

    private final Handler handler = new Handler(Looper.getMainLooper());
    private long startedAt;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        // Draw behind the status and navigation bars, with white bar icons on the gradient
        EdgeToEdge.enable(this, SystemBarStyle.dark(Color.TRANSPARENT), SystemBarStyle.dark(Color.TRANSPARENT));
        setContentView(R.layout.activity_splash);
        keepFooterAboveNavigationBar();
        startedAt = SystemClock.elapsedRealtime();

        // "Remember me" was off: a new start of the app means signing in again
        if (Session.isLoggedIn() && !Session.isRemembered()) {
            Session.clear();
        }

        if (!Session.isLoggedIn()) {
            open(LoginActivity.class, null);
            return;
        }

        RetrofitInstance.getAuthApi().me().enqueue(new Callback<UserResponse>() {
            @Override
            public void onResponse(@NonNull Call<UserResponse> call, @NonNull Response<UserResponse> response) {
                if (response.isSuccessful() && response.body() != null) {
                    Session.saveUser(response.body().getData());
                    open(MainActivity.class, null);
                } else if (response.code() == 401) {
                    Session.clear();
                    open(LoginActivity.class, getString(R.string.error_session));
                } else {
                    open(MainActivity.class, null);
                }
            }

            @Override
            public void onFailure(@NonNull Call<UserResponse> call, @NonNull Throwable t) {
                open(MainActivity.class, null);
            }
        });
    }

    /** Opens the next screen once the logo has been shown long enough, and closes the splash. */
    private void open(Class<?> screen, String message) {
        long wait = Math.max(0, MIN_SHOW_MS - (SystemClock.elapsedRealtime() - startedAt));
        handler.postDelayed(() -> {
            if (isFinishing()) return;
            Intent intent = new Intent(this, screen);
            if (message != null) intent.putExtra(LoginActivity.EXTRA_MESSAGE, message);
            startActivity(intent);
            overridePendingTransition(android.R.anim.fade_in, android.R.anim.fade_out);
            finish();
        }, wait);
    }

    /** Only the footer moves up above the navigation bar; the gradient and circles still fill the whole screen. */
    private void keepFooterAboveNavigationBar() {
        View footer = findViewById(R.id.splashFooter);
        ViewGroup.MarginLayoutParams params = (ViewGroup.MarginLayoutParams) footer.getLayoutParams();
        int baseMargin = params.bottomMargin;
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.splashRoot), (view, insets) -> {
            Insets bars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            params.bottomMargin = baseMargin + bars.bottom;
            footer.setLayoutParams(params);
            return WindowInsetsCompat.CONSUMED;
        });
    }

    @Override
    protected void onDestroy() {
        handler.removeCallbacksAndMessages(null);
        super.onDestroy();
    }
}
