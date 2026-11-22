package com.school.attendance.activities;

import android.content.Intent;
import android.content.res.Configuration;
import android.graphics.Color;
import android.graphics.Rect;
import android.os.Build;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.View;
import android.view.inputmethod.EditorInfo;
import android.view.inputmethod.InputMethodManager;
import android.widget.Button;
import android.widget.CheckBox;
import android.widget.ImageButton;
import android.widget.TextView;

import androidx.activity.EdgeToEdge;
import androidx.activity.SystemBarStyle;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.appcompat.app.AppCompatDelegate;
import androidx.core.content.ContextCompat;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

import com.google.android.material.textfield.TextInputEditText;
import com.school.attendance.R;
import com.school.attendance.config.ApiError;
import com.school.attendance.config.Config;
import com.school.attendance.config.RetrofitInstance;
import com.school.attendance.config.Session;
import com.school.attendance.models.LoginRequest;
import com.school.attendance.models.LoginResponse;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * Sign in with phone number + password (teachers only).
 * Also: English / Khmer switch, light / dark switch, "Remember me", "Forgot password?".
 */
public class LoginActivity extends AppCompatActivity {

    /** A message to show when this screen opens, e.g. "Your session has ended". */
    public static final String EXTRA_MESSAGE = "message";

    private View header;
    private TextView txtError, txtForgotInfo, langEn, langKm;
    private TextInputEditText edtPhone, edtPassword;
    private CheckBox chkRemember;
    private Button btnSignIn;
    private ImageButton btnTheme;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        // White icons on the gradient at the top; dark icons at the bottom in light mode (white card)
        EdgeToEdge.enable(this,
                SystemBarStyle.dark(Color.TRANSPARENT),
                isDarkMode() ? SystemBarStyle.dark(Color.TRANSPARENT)
                        : SystemBarStyle.light(Color.TRANSPARENT, Color.TRANSPARENT));
        setContentView(R.layout.activity_login);

        header = findViewById(R.id.loginHeader);
        txtError = findViewById(R.id.txtError);
        txtForgotInfo = findViewById(R.id.txtForgotInfo);
        langEn = findViewById(R.id.langEn);
        langKm = findViewById(R.id.langKm);
        edtPhone = findViewById(R.id.edtPhone);
        edtPassword = findViewById(R.id.edtPassword);
        chkRemember = findViewById(R.id.chkRemember);
        btnSignIn = findViewById(R.id.btnSignIn);
        btnTheme = findViewById(R.id.btnTheme);

        handleSystemBarsAndKeyboard();
        drawLanguageSwitch();
        btnTheme.setImageResource(isDarkMode() ? R.drawable.ic_sun : R.drawable.ic_moon);

        langEn.setOnClickListener(v -> switchLanguage("en"));
        langKm.setOnClickListener(v -> switchLanguage("km"));
        btnTheme.setOnClickListener(v -> Session.setNightMode(
                isDarkMode() ? AppCompatDelegate.MODE_NIGHT_NO : AppCompatDelegate.MODE_NIGHT_YES));
        findViewById(R.id.txtForgot).setOnClickListener(v -> txtForgotInfo.setVisibility(
                txtForgotInfo.getVisibility() == View.VISIBLE ? View.GONE : View.VISIBLE));

        btnSignIn.setOnClickListener(v -> signIn());
        edtPassword.setOnEditorActionListener((v, actionId, event) -> {
            if (actionId == EditorInfo.IME_ACTION_DONE) {
                signIn();
                return true;
            }
            return false;
        });

        // Typing again hides the old error
        TextWatcher hideError = new SimpleWatcher(() -> txtError.setVisibility(View.GONE));
        edtPhone.addTextChangedListener(hideError);
        edtPassword.addTextChangedListener(hideError);

        String message = getIntent().getStringExtra(EXTRA_MESSAGE);
        if (message != null) showError(message);
    }

    private void signIn() {
        String phone = edtPhone.getText() == null ? "" : edtPhone.getText().toString().trim();
        String password = edtPassword.getText() == null ? "" : edtPassword.getText().toString();
        if (phone.isEmpty() || password.isEmpty()) {
            showError(getString(R.string.fill_all));
            return;
        }

        // Close the keyboard first, so it does not stay over the next screen
        InputMethodManager keyboard = (InputMethodManager) getSystemService(INPUT_METHOD_SERVICE);
        if (keyboard != null) keyboard.hideSoftInputFromWindow(edtPassword.getWindowToken(), 0);

        setLoading(true);
        String device = Build.MANUFACTURER + " " + Build.MODEL; // shown to the admin as the phone's name
        RetrofitInstance.getAuthApi().login(new LoginRequest(phone, password, device))
                .enqueue(new Callback<LoginResponse>() {
                    @Override
                    public void onResponse(@NonNull Call<LoginResponse> call, @NonNull Response<LoginResponse> response) {
                        if (isFinishing()) return;
                        setLoading(false);
                        LoginResponse body = response.body();
                        if (response.isSuccessful() && body != null && body.getToken() != null) {
                            Session.saveLogin(body.getToken(), body.getUser(), chkRemember.isChecked());
                            openHome();
                        } else {
                            // Wrong password (422), admin account (403), too many tries (429)…
                            showError(ApiError.message(LoginActivity.this, response));
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<LoginResponse> call, @NonNull Throwable t) {
                        if (isFinishing()) return;
                        setLoading(false);
                        showError(ApiError.network(LoginActivity.this));
                    }
                });
    }

    /** Login is the only screen open here (Splash and Sign out close the others), so open Home and close Login. */
    private void openHome() {
        startActivity(new Intent(this, MainActivity.class));
        overridePendingTransition(android.R.anim.fade_in, android.R.anim.fade_out);
        finish();
    }

    private void setLoading(boolean loading) {
        btnSignIn.setEnabled(!loading);
        btnSignIn.setAlpha(loading ? 0.7f : 1f);
        btnSignIn.setText(loading ? R.string.signing_in : R.string.sign_in);
        edtPhone.setEnabled(!loading);
        edtPassword.setEnabled(!loading);
    }

    private void showError(String message) {
        txtError.setText(message);
        txtError.setVisibility(View.VISIBLE);
    }

    // ---------- Language and theme ----------

    private void switchLanguage(String language) {
        if (!language.equals(Config.currentLanguage())) {
            Config.setLanguage(language); // Android redraws this screen in the new language
        }
    }

    /** The chosen language is a white pill with teal text; the other one is plain white text. */
    private void drawLanguageSwitch() {
        boolean khmer = "km".equals(Config.currentLanguage());
        styleLanguage(langEn, !khmer);
        styleLanguage(langKm, khmer);
    }

    private void styleLanguage(TextView view, boolean selected) {
        view.setBackgroundResource(selected ? R.drawable.bg_pill_selected : android.R.color.transparent);
        view.setTextColor(selected ? ContextCompat.getColor(this, R.color.header_start) : Color.WHITE);
    }

    private boolean isDarkMode() {
        int mode = getResources().getConfiguration().uiMode & Configuration.UI_MODE_NIGHT_MASK;
        return mode == Configuration.UI_MODE_NIGHT_YES;
    }

    /**
     * The screen is drawn behind the status bar, the navigation bar and the keyboard.
     * Push the header below the status bar, and lift the card above the navigation bar / keyboard.
     */
    private void handleSystemBarsAndKeyboard() {
        int headerTop = header.getPaddingTop();
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.loginRoot), (view, insets) -> {
            Insets bars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            Insets keyboard = insets.getInsets(WindowInsetsCompat.Type.ime());
            header.setPadding(header.getPaddingLeft(), headerTop + bars.top, header.getPaddingRight(), header.getPaddingBottom());
            view.setPadding(0, 0, 0, Math.max(bars.bottom, keyboard.bottom));
            // Keyboard open: scroll so the Sign In button is not hidden under it
            if (keyboard.bottom > 0) {
                view.post(() -> btnSignIn.requestRectangleOnScreen(
                        new Rect(0, 0, btnSignIn.getWidth(), btnSignIn.getHeight() + dp(16))));
            }
            return WindowInsetsCompat.CONSUMED;
        });
    }

    private int dp(int value) {
        return Math.round(value * getResources().getDisplayMetrics().density);
    }

    /** A TextWatcher that only needs "after the text changed". */
    private static class SimpleWatcher implements TextWatcher {
        private final Runnable onChange;

        SimpleWatcher(Runnable onChange) {
            this.onChange = onChange;
        }

        @Override public void beforeTextChanged(CharSequence s, int start, int count, int after) { }
        @Override public void onTextChanged(CharSequence s, int start, int before, int count) { }
        @Override public void afterTextChanged(Editable s) { onChange.run(); }
    }
}
