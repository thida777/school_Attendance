package com.school.attendance.activities;

import android.graphics.Color;
import android.os.Bundle;
import android.view.View;
import android.widget.Button;
import android.widget.TextView;
import android.widget.Toast;

import androidx.activity.EdgeToEdge;
import androidx.activity.SystemBarStyle;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

import com.google.android.material.textfield.TextInputEditText;
import com.school.attendance.R;
import com.school.attendance.config.ApiError;
import com.school.attendance.config.RetrofitInstance;
import com.school.attendance.config.Ui;
import com.school.attendance.models.ApiMessage;
import com.school.attendance.models.ForgotRequest;
import com.school.attendance.models.ResetRequest;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * Forgot password, two steps on one screen (the same rules as the website):
 *  1. Phone number → POST /password/forgot → the school bot sends a 6-digit code by Telegram.
 *  2. Code + new password → POST /password/reset → back to the login screen.
 * The server gives the same answer for every phone number, so nobody can test which accounts exist.
 */
public class ForgotPasswordActivity extends AppCompatActivity {

    /** The phone number typed on the login screen, if any. */
    public static final String EXTRA_LOGIN = "login";

    private View stepPhone, stepCode;
    private TextInputEditText edtLogin, edtCode, edtNew, edtRepeat;
    private TextView txtError, txtSent, txtAccount;
    private Button btnSend, btnSave;
    private String login = "";

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this, SystemBarStyle.dark(Color.TRANSPARENT));
        setContentView(R.layout.activity_forgot_password);
        Ui.padBelowStatusBar(findViewById(R.id.topBar));
        ((TextView) findViewById(R.id.txtTopTitle)).setText(R.string.forgot_title);
        findViewById(R.id.btnBack).setOnClickListener(v -> finish());

        // Keep the form above the keyboard and the navigation bar
        View root = findViewById(R.id.forgotRoot);
        ViewCompat.setOnApplyWindowInsetsListener(root, (view, insets) -> {
            Insets bars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            Insets keyboard = insets.getInsets(WindowInsetsCompat.Type.ime());
            view.setPadding(0, 0, 0, Math.max(bars.bottom, keyboard.bottom));
            return insets;
        });

        stepPhone = findViewById(R.id.stepPhone);
        stepCode = findViewById(R.id.stepCode);
        edtLogin = findViewById(R.id.edtLogin);
        edtCode = findViewById(R.id.edtCode);
        edtNew = findViewById(R.id.edtNew);
        edtRepeat = findViewById(R.id.edtRepeat);
        txtError = findViewById(R.id.txtError);
        txtSent = findViewById(R.id.txtSent);
        txtAccount = findViewById(R.id.txtAccount);
        btnSend = findViewById(R.id.btnSend);
        btnSave = findViewById(R.id.btnSave);

        String fromLogin = getIntent().getStringExtra(EXTRA_LOGIN);
        if (fromLogin != null) edtLogin.setText(fromLogin);

        btnSend.setOnClickListener(v -> sendCode());
        btnSave.setOnClickListener(v -> save());
        findViewById(R.id.txtNewCode).setOnClickListener(v -> showStep(false));
    }

    /** Step 1: ask the server to send a code by Telegram. */
    private void sendCode() {
        login = text(edtLogin).trim();
        if (login.isEmpty()) {
            showError(getString(R.string.enter_phone));
            return;
        }

        setBusy(btnSend, true, R.string.send_code);
        RetrofitInstance.getAuthApi().forgotPassword(new ForgotRequest(login)).enqueue(new Callback<ApiMessage>() {
            @Override
            public void onResponse(@NonNull Call<ApiMessage> call, @NonNull Response<ApiMessage> response) {
                if (isFinishing()) return;
                setBusy(btnSend, false, R.string.send_code);
                if (response.isSuccessful() && response.body() != null) {
                    txtSent.setText(response.body().getMessage()); // in the app language (Accept-Language)
                    txtAccount.setText(getString(R.string.account_label, login));
                    showStep(true);
                } else {
                    showError(ApiError.message(ForgotPasswordActivity.this, response));
                }
            }

            @Override
            public void onFailure(@NonNull Call<ApiMessage> call, @NonNull Throwable t) {
                if (isFinishing()) return;
                setBusy(btnSend, false, R.string.send_code);
                showError(ApiError.network(ForgotPasswordActivity.this));
            }
        });
    }

    /** Step 2: code + new password. */
    private void save() {
        String code = text(edtCode).trim(), password = text(edtNew), repeat = text(edtRepeat);
        if (code.isEmpty() || password.isEmpty() || repeat.isEmpty()) {
            showError(getString(R.string.fill_all_password));
            return;
        }
        if (!password.equals(repeat)) {
            showError(getString(R.string.passwords_not_match));
            return;
        }

        setBusy(btnSave, true, R.string.save_new_password);
        RetrofitInstance.getAuthApi().resetPassword(new ResetRequest(login, code, password, repeat))
                .enqueue(new Callback<ApiMessage>() {
                    @Override
                    public void onResponse(@NonNull Call<ApiMessage> call, @NonNull Response<ApiMessage> response) {
                        if (isFinishing()) return;
                        setBusy(btnSave, false, R.string.save_new_password);
                        if (response.isSuccessful() && response.body() != null) {
                            Toast.makeText(ForgotPasswordActivity.this, response.body().getMessage(), Toast.LENGTH_LONG).show();
                            finish(); // back to the login screen
                        } else {
                            showError(ApiError.message(ForgotPasswordActivity.this, response));
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<ApiMessage> call, @NonNull Throwable t) {
                        if (isFinishing()) return;
                        setBusy(btnSave, false, R.string.save_new_password);
                        showError(ApiError.network(ForgotPasswordActivity.this));
                    }
                });
    }

    private void showStep(boolean code) {
        stepPhone.setVisibility(code ? View.GONE : View.VISIBLE);
        stepCode.setVisibility(code ? View.VISIBLE : View.GONE);
        txtError.setVisibility(View.GONE);
        (code ? edtCode : edtLogin).requestFocus();
    }

    private static String text(TextInputEditText field) {
        return field.getText() == null ? "" : field.getText().toString();
    }

    private void setBusy(Button button, boolean busy, int label) {
        button.setEnabled(!busy);
        button.setAlpha(busy ? 0.7f : 1f);
        button.setText(busy ? R.string.please_wait : label);
        if (busy) txtError.setVisibility(View.GONE);
    }

    private void showError(String message) {
        txtError.setText(message);
        txtError.setVisibility(View.VISIBLE);
    }
}
