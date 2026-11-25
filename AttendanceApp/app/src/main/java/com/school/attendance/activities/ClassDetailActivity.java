package com.school.attendance.activities;

import android.content.Intent;
import android.graphics.Color;
import android.net.Uri;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.activity.EdgeToEdge;
import androidx.activity.SystemBarStyle;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.content.ContextCompat;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;

import com.google.android.material.button.MaterialButton;
import com.google.android.material.chip.Chip;
import com.google.android.material.chip.ChipGroup;
import com.google.android.material.progressindicator.CircularProgressIndicator;
import com.google.gson.Gson;
import com.school.attendance.R;
import com.school.attendance.adapters.ClassCardAdapter;
import com.school.attendance.config.ApiError;
import com.school.attendance.config.RetrofitInstance;
import com.school.attendance.config.Ui;
import com.school.attendance.models.Lesson;
import com.school.attendance.models.Parent;
import com.school.attendance.models.SchoolClass;
import com.school.attendance.models.Student;
import com.school.attendance.models.StudentListResponse;
import com.school.attendance.models.Subject;
import com.school.attendance.models.TodayResponse;

import java.util.List;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * Class details (read only, like the website: only the admin edits students).
 * Shows the rate, a "Mark attendance" button when the class has a lesson today,
 * the students with their rate, Good / Watch / At risk, and the parent (tap 📞 to call), and my subjects.
 */
public class ClassDetailActivity extends AppCompatActivity {

    public static final String EXTRA_CLASS = "class_json";

    private SchoolClass schoolClass;
    private boolean khmer;
    private MaterialButton btnMark;
    private Lesson todayLesson;
    private View stateBox, stateProgress, btnRetry, cardStudents;
    private TextView stateText;
    private ImageView stateIcon;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this, SystemBarStyle.dark(Color.TRANSPARENT));
        setContentView(R.layout.activity_class_detail);
        Ui.padBelowStatusBar(findViewById(R.id.topBar));
        ViewCompat.setOnApplyWindowInsetsListener(findViewById(R.id.detailRoot), (view, insets) -> {
            Insets bars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            view.setPadding(0, 0, 0, bars.bottom);
            return insets;
        });
        ((TextView) findViewById(R.id.txtTopTitle)).setText(R.string.class_details);
        findViewById(R.id.btnBack).setOnClickListener(v -> finish());

        schoolClass = new Gson().fromJson(getIntent().getStringExtra(EXTRA_CLASS), SchoolClass.class);
        if (schoolClass == null) {
            finish();
            return;
        }
        khmer = Ui.isKhmer();
        stateBox = findViewById(R.id.stateBox);
        stateProgress = findViewById(R.id.stateProgress);
        stateIcon = findViewById(R.id.stateIcon);
        stateText = findViewById(R.id.stateText);
        btnRetry = findViewById(R.id.btnRetry);
        cardStudents = findViewById(R.id.cardStudents);
        btnMark = findViewById(R.id.btnMark);

        showSummary();
        showSubjects();
        btnRetry.setOnClickListener(v -> loadStudents());
        btnMark.setOnClickListener(v -> {
            if (todayLesson == null) return;
            Intent intent = new Intent(this, MarkAttendanceActivity.class);
            intent.putExtra(MarkAttendanceActivity.EXTRA_SLOT_ID, todayLesson.getSlotId());
            startActivity(intent);
        });
    }

    @Override
    protected void onResume() {
        super.onResume();
        loadStudents();     // rates change after marking
        findTodayLesson();
    }

    private void showSummary() {
        Double rate = schoolClass.getAttendanceRate();
        ((TextView) findViewById(R.id.txtClassName)).setText(schoolClass.getName());
        ((TextView) findViewById(R.id.txtClassInfo)).setText(ClassCardAdapter.gradeText(this, schoolClass)
                + " · " + getString(R.string.students_count, schoolClass.getStudents()));
        findViewById(R.id.txtHomeroom).setVisibility(schoolClass.isHomeroom() ? View.VISIBLE : View.GONE);
        CircularProgressIndicator ring = findViewById(R.id.ringRate);
        ring.setProgressCompat(rate == null ? 0 : (int) Math.round(rate), false);
        ring.setIndicatorColor(Ui.rateColor(this, rate));
        ((TextView) findViewById(R.id.txtRate)).setText(Ui.rateText(rate));
        ((TextView) findViewById(R.id.txtRateLabel)).setText(rate == null ? R.string.no_records_yet : R.string.attendance_rate);
    }

    private void showSubjects() {
        ChipGroup group = findViewById(R.id.chipSubjects);
        group.removeAllViews();
        if (schoolClass.getSubjects() == null) return;
        for (Subject s : schoolClass.getSubjects()) {
            Chip chip = Ui.makeChip(this, s.getCode() + " · " + s.name(khmer));
            chip.setCheckable(false);
            chip.setClickable(false);
            group.addView(chip);
        }
    }

    /** "Mark attendance · Period 3" when this class has a lesson of mine today; otherwise the button is off. */
    private void findTodayLesson() {
        btnMark.setEnabled(false);
        btnMark.setAlpha(0.6f);
        btnMark.setText(R.string.no_lesson_today);
        RetrofitInstance.getTeachingApi().today().enqueue(new Callback<TodayResponse>() {
            @Override
            public void onResponse(@NonNull Call<TodayResponse> call, @NonNull Response<TodayResponse> response) {
                if (isFinishing() || !response.isSuccessful() || response.body() == null) return;
                todayLesson = null;
                for (Lesson lesson : response.body().getLessons()) {
                    if (lesson.getSchoolClass().getId() != schoolClass.getId()) continue;
                    // Prefer a lesson not marked yet; otherwise the first one (to edit it)
                    if (todayLesson == null || (todayLesson.isDone() && !lesson.isDone())) todayLesson = lesson;
                }
                if (todayLesson != null) {
                    btnMark.setEnabled(true);
                    btnMark.setAlpha(1f);
                    btnMark.setText(getString(R.string.mark_period, todayLesson.getPeriod()));
                }
            }

            @Override
            public void onFailure(@NonNull Call<TodayResponse> call, @NonNull Throwable t) {
                // The button stays off; the students list shows the connection problem
            }
        });
    }

    private void loadStudents() {
        if (cardStudents.getVisibility() != View.VISIBLE) showState(true, null);
        RetrofitInstance.getTeachingApi().students(schoolClass.getId()).enqueue(new Callback<StudentListResponse>() {
            @Override
            public void onResponse(@NonNull Call<StudentListResponse> call, @NonNull Response<StudentListResponse> response) {
                if (isFinishing() || ApiError.sessionEnded(ClassDetailActivity.this, response)) return;
                if (response.isSuccessful() && response.body() != null) {
                    showStudents(response.body().getData());
                } else {
                    showState(false, ApiError.message(ClassDetailActivity.this, response));
                }
            }

            @Override
            public void onFailure(@NonNull Call<StudentListResponse> call, @NonNull Throwable t) {
                if (isFinishing()) return;
                if (cardStudents.getVisibility() != View.VISIBLE) showState(false, ApiError.network(ClassDetailActivity.this));
            }
        });
    }

    private void showStudents(List<Student> students) {
        stateBox.setVisibility(View.GONE);
        cardStudents.setVisibility(View.VISIBLE);
        ((TextView) findViewById(R.id.txtStudentsTitle)).setText(getString(R.string.students_title, students.size()));

        LinearLayout list = findViewById(R.id.studentList);
        list.removeAllViews();
        LayoutInflater inflater = LayoutInflater.from(this);
        for (int i = 0; i < students.size(); i++) {
            Student s = students.get(i);
            if (i > 0) {
                View line = new View(this);
                line.setBackgroundColor(ContextCompat.getColor(this, R.color.border));
                list.addView(line, new LinearLayout.LayoutParams(LinearLayout.LayoutParams.MATCH_PARENT, 1));
            }
            View row = inflater.inflate(R.layout.item_class_student, list, false);
            Ui.showStudentPhoto(row.findViewById(R.id.imgAvatar), row.findViewById(R.id.txtAvatar), s, !s.isActive());
            ((TextView) row.findViewById(R.id.txtName)).setText(s.displayName(khmer));
            ((TextView) row.findViewById(R.id.txtCode)).setText(s.isActive()
                    ? s.getStudentCode() : s.getStudentCode() + " · " + getString(R.string.inactive));

            TextView rate = row.findViewById(R.id.txtRate);
            rate.setText(Ui.rateText(s.getAttendanceRate()));
            rate.setTextColor(Ui.rateColor(this, s.getAttendanceRate()));
            Ui.showRisk(row.findViewById(R.id.txtRisk), s.getRisk());

            Parent parent = s.getParent();
            TextView parentLine = row.findViewById(R.id.txtParent);
            View call = row.findViewById(R.id.btnCall);
            if (parent != null && parent.getPhone() != null) {
                parentLine.setText(getString(R.string.parent_line, parent.getName() + " · " + parent.getPhoneFormatted()));
                // Opens the phone app with the number filled in (no permission needed; the teacher presses Call)
                call.setOnClickListener(v -> startActivity(new Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + parent.getPhone()))));
            } else {
                parentLine.setVisibility(View.GONE);
                call.setVisibility(View.GONE);
            }

            // Telegram: ✓ when the parent gets messages there; otherwise a QR invite to show at a parents' meeting
            TextView telegram = row.findViewById(R.id.txtTelegram);
            View qr = row.findViewById(R.id.btnQr);
            if (parent != null) {
                telegram.setVisibility(View.VISIBLE);
                telegram.setText(parent.onTelegram() ? R.string.telegram_linked : R.string.telegram_not_yet);
                telegram.setTextColor(ContextCompat.getColor(this, parent.onTelegram() ? R.color.present_text : R.color.text_muted));
                telegram.setBackgroundResource(parent.onTelegram() ? R.drawable.bg_chip_present : R.drawable.bg_chip_grey);
                if (!parent.onTelegram() && parent.getInviteLink() != null) {
                    qr.setVisibility(View.VISIBLE);
                    qr.setOnClickListener(v -> Ui.showTelegramQr(this, parent.getName(), parent.getInviteLink()));
                }
            }
            if (!s.isActive()) row.setAlpha(0.55f);
            list.addView(row);
        }
    }

    private void showState(boolean loading, String message) {
        stateBox.setVisibility(View.VISIBLE);
        stateProgress.setVisibility(loading ? View.VISIBLE : View.GONE);
        stateIcon.setVisibility(loading ? View.GONE : View.VISIBLE);
        stateIcon.setImageResource(R.drawable.ic_wifi_off);
        stateText.setVisibility(loading ? View.GONE : View.VISIBLE);
        stateText.setText(message);
        btnRetry.setVisibility(loading ? View.GONE : View.VISIBLE);
    }
}
