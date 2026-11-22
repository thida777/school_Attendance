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
import com.school.attendance.models.PasswordRequest;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * Change password: current + new + repeat → PUT /me/password.
 * This phone stays signed in; other phones of the same teacher are signed out by the server.
 */
public class ChangePasswordActivity extends AppCompatActivity {

    private TextInputEditText edtCurrent, edtNew, edtRepeat;
    private TextView txtError;
    private Button btnSave;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this, SystemBarStyle.dark(Color.TRANSPARENT));
        setContentView(R.layout.activity_change_password);
        Ui.padBelowStatusBar(findViewById(R.id.topBar));
        ((TextView) findViewById(R.id.txtTopTitle)).setText(R.string.change_password);
        findViewById(R.id.btnBack).setOnClickListener(v -> finish());

        // Keep the form above the keyboard and the navigation bar
        View root = findViewById(R.id.passwordRoot);
        ViewCompat.setOnApplyWindowInsetsListener(root, (view, insets) -> {
            Insets bars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            Insets keyboard = insets.getInsets(WindowInsetsCompat.Type.ime());
            view.setPadding(0, 0, 0, Math.max(bars.bottom, keyboard.bottom));
            return insets;
        });

        edtCurrent = findViewById(R.id.edtCurrent);
        edtNew = findViewById(R.id.edtNew);
        edtRepeat = findViewById(R.id.edtRepeat);
        txtError = findViewById(R.id.txtError);
        btnSave = findViewById(R.id.btnSave);
        btnSave.setOnClickListener(v -> save());
    }

    private void save() {
        String current = text(edtCurrent), password = text(edtNew), repeat = text(edtRepeat);
        if (current.isEmpty() || password.isEmpty() || repeat.isEmpty()) {
            showError(getString(R.string.fill_all_password));
            return;
        }
        if (!password.equals(repeat)) {
            showError(getString(R.string.passwords_not_match));
            return;
        }
        // "At least 8 characters" is checked by the server, so the message is the same as on the website

        setSaving(true);
        RetrofitInstance.getAuthApi().changePassword(new PasswordRequest(current, password, repeat))
                .enqueue(new Callback<ApiMessage>() {
                    @Override
                    public void onResponse(@NonNull Call<ApiMessage> call, @NonNull Response<ApiMessage> response) {
                        if (isFinishing()) return;
                        setSaving(false);
                        if (ApiError.sessionEnded(ChangePasswordActivity.this, response)) return;
                        if (response.isSuccessful()) {
                            Toast.makeText(ChangePasswordActivity.this, R.string.password_changed, Toast.LENGTH_LONG).show();
                            finish();
                        } else {
                            showError(ApiError.message(ChangePasswordActivity.this, response));
                        }
                    }

                    @Override
                    public void onFailure(@NonNull Call<ApiMessage> call, @NonNull Throwable t) {
                        if (isFinishing()) return;
                        setSaving(false);
                        showError(ApiError.network(ChangePasswordActivity.this));
                    }
                });
    }

    private static String text(TextInputEditText field) {
        return field.getText() == null ? "" : field.getText().toString();
    }

    private void setSaving(boolean saving) {
        btnSave.setEnabled(!saving);
        btnSave.setAlpha(saving ? 0.7f : 1f);
        btnSave.setText(saving ? R.string.saving : R.string.update_password);
        if (saving) txtError.setVisibility(View.GONE);
    }

    private void showError(String message) {
        txtError.setText(message);
        txtError.setVisibility(View.VISIBLE);
    }
}
