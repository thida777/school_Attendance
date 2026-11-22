package com.school.attendance.activities;

import android.graphics.Color;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.activity.EdgeToEdge;
import androidx.activity.SystemBarStyle;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;

import com.school.attendance.R;
import com.school.attendance.config.Session;
import com.school.attendance.config.Ui;
import com.school.attendance.models.User;

/**
 * Personal information (read only). Teachers cannot change their name or phone in the app:
 * the admin manages staff details on the website, the same rule as before.
 */
public class PersonalInfoActivity extends AppCompatActivity {

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this, SystemBarStyle.dark(Color.TRANSPARENT));
        setContentView(R.layout.activity_personal_info);
        Ui.padBelowStatusBar(findViewById(R.id.topBar));
        ((TextView) findViewById(R.id.txtTopTitle)).setText(R.string.personal_info);
        findViewById(R.id.btnBack).setOnClickListener(v -> finish());

        User user = Session.getUser();
        if (user == null) {
            finish();
            return;
        }

        LinearLayout list = findViewById(R.id.infoList);
        addRow(list, R.string.name_latin, user.getName());
        addRow(list, R.string.name_khmer, user.getNameKm());
        addRow(list, R.string.phone_label, user.getPhoneFormatted());
        addRow(list, R.string.email, user.getEmail());
        addRow(list, R.string.homeroom, user.getHomeroomClass() == null ? null : user.getHomeroomClass().getName());
        addRow(list, R.string.role, getString(R.string.role_teacher));
    }

    /** One row: small grey label, then the value ("–" when empty), with a line between rows. */
    private void addRow(LinearLayout list, int labelRes, String value) {
        if (list.getChildCount() > 0) {
            View line = new View(this);
            line.setBackgroundColor(ContextCompat.getColor(this, R.color.border));
            list.addView(line, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, 1));
        }
        LinearLayout row = new LinearLayout(this);
        row.setOrientation(LinearLayout.VERTICAL);
        int pad = Math.round(12 * getResources().getDisplayMetrics().density);
        row.setPadding(0, pad, 0, pad);

        TextView label = new TextView(this);
        label.setText(labelRes);
        label.setTextSize(12);
        label.setTextColor(ContextCompat.getColor(this, R.color.text_muted));

        TextView text = new TextView(this);
        text.setText(value == null || value.isEmpty() ? "–" : value);
        text.setTextSize(16);
        text.setTextColor(ContextCompat.getColor(this, R.color.text_main));
        text.setTextIsSelectable(true);

        row.addView(label);
        row.addView(text);
        list.addView(row);
    }
}
