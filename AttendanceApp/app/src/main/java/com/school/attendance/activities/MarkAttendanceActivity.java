package com.school.attendance.activities;

import android.content.Context;
import android.content.Intent;
import android.graphics.Color;
import android.os.Bundle;
import android.text.Editable;
import android.text.TextWatcher;
import android.view.LayoutInflater;
import android.view.View;
import android.widget.Button;
import android.widget.LinearLayout;
import android.widget.TextView;

import androidx.activity.EdgeToEdge;
import androidx.activity.OnBackPressedCallback;
import androidx.activity.SystemBarStyle;
import androidx.annotation.NonNull;
import androidx.appcompat.app.AppCompatActivity;
import androidx.core.graphics.Insets;
import androidx.core.view.ViewCompat;
import androidx.core.view.WindowInsetsCompat;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;

import com.google.android.material.bottomsheet.BottomSheetDialog;
import com.google.android.material.button.MaterialButton;
import com.google.android.material.button.MaterialButtonToggleGroup;
import com.google.android.material.chip.Chip;
import com.google.android.material.chip.ChipGroup;
import com.google.android.material.progressindicator.CircularProgressIndicator;
import com.google.android.material.snackbar.Snackbar;
import com.school.attendance.R;
import com.school.attendance.adapters.StudentMarkAdapter;
import com.school.attendance.config.ApiError;
import com.school.attendance.config.RetrofitInstance;
import com.school.attendance.config.StepBar;
import com.school.attendance.config.Ui;
import com.school.attendance.models.Lesson;
import com.school.attendance.models.RosterLesson;
import com.school.attendance.models.RosterResponse;
import com.school.attendance.models.Student;
import com.school.attendance.models.SubmitRequest;
import com.school.attendance.models.SubmitResponse;
import com.school.attendance.models.TodayResponse;

import java.util.ArrayList;
import java.util.HashMap;
import java.util.List;
import java.util.Map;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * Mark attendance for one lesson of today (steps 2 → 3 → 4 of the Figma design).
 *   Step 2 Mark:   everyone starts Present (or with the saved marks); tap a student or ✗ for Absent; Undo.
 *   Step 3 Review: totals and a reason for each absent student (optional), then "Submit?".
 *   Step 4 Done:   the server's totals, the absent names, and a button to the next lesson.
 * Open it with: intent.putExtra(MarkAttendanceActivity.EXTRA_SLOT_ID, lesson.getSlotId()).
 */
public class MarkAttendanceActivity extends AppCompatActivity {

    public static final String EXTRA_SLOT_ID = "slot_id";

    private static final int PAGE_LOADING = 0, PAGE_MARK = 2, PAGE_REVIEW = 3, PAGE_DONE = 4;

    private int slotId;
    private int page = PAGE_LOADING;
    private boolean khmer;
    private boolean changed;            // the teacher marked something → ask before leaving
    private RosterResponse roster;
    private final List<Student> students = new ArrayList<>();
    private final Map<Integer, String> statuses = new HashMap<>(); // student id → present / absent
    private final Map<Integer, String> reasons = new HashMap<>();  // student id → sick, other…
    private String filter = "all";
    private String query = "";
    private Lesson nextLesson;

    private View stateBox, stateProgress, stateIcon, btnRetry, pageMark, pageReview, pageDone, stepBar;
    private TextView stateText, txtTitle, txtRate, txtLesson, txtLessonTime, txtPresentCount, txtAbsentCount;
    private TextView txtBottomSummary, txtNoStudents;
    private CircularProgressIndicator ringRate;
    private MaterialButton btnFilterAll, btnFilterPresent, btnFilterAbsent, btnNextLesson;
    private Button btnSubmit;
    private StudentMarkAdapter adapter;
    private Snackbar undoBar;

    @Override
    protected void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);
        EdgeToEdge.enable(this, SystemBarStyle.dark(Color.TRANSPARENT));
        setContentView(R.layout.activity_mark_attendance);
        slotId = getIntent().getIntExtra(EXTRA_SLOT_ID, -1);
        khmer = Ui.isKhmer();

        bindViews();
        handleSystemBarsAndKeyboard();
        txtTitle.setText(R.string.mark_attendance);
        StepBar.show(stepBar, 2);
        findViewById(R.id.btnBack).setOnClickListener(v -> getOnBackPressedDispatcher().onBackPressed());
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                goBack();
            }
        });

        adapter = new StudentMarkAdapter(statuses, khmer, this::mark);
        RecyclerView recycler = findViewById(R.id.recyclerStudents);
        recycler.setLayoutManager(new LinearLayoutManager(this));
        recycler.setAdapter(adapter);

        setUpFilters();
        btnRetry.setOnClickListener(v -> loadRoster());
        findViewById(R.id.btnToReview).setOnClickListener(v -> showPage(PAGE_REVIEW));
        btnSubmit.setOnClickListener(v -> confirmSubmit());
        findViewById(R.id.btnBackHome).setOnClickListener(v -> finish());
        btnNextLesson.setOnClickListener(v -> openLesson(nextLesson));

        loadRoster();
    }

    private void bindViews() {
        stepBar = findViewById(R.id.stepBar);
        txtTitle = findViewById(R.id.txtTopTitle);
        stateBox = findViewById(R.id.stateBox);
        stateProgress = findViewById(R.id.stateProgress);
        stateIcon = findViewById(R.id.stateIcon);
        stateText = findViewById(R.id.stateText);
        btnRetry = findViewById(R.id.btnRetry);
        pageMark = findViewById(R.id.pageMark);
        pageReview = findViewById(R.id.pageReview);
        pageDone = findViewById(R.id.pageDone);
        ringRate = findViewById(R.id.ringRate);
        txtRate = findViewById(R.id.txtRate);
        txtLesson = findViewById(R.id.txtLesson);
        txtLessonTime = findViewById(R.id.txtLessonTime);
        txtPresentCount = findViewById(R.id.txtPresentCount);
        txtAbsentCount = findViewById(R.id.txtAbsentCount);
        txtBottomSummary = findViewById(R.id.txtBottomSummary);
        txtNoStudents = findViewById(R.id.txtNoStudents);
        btnFilterAll = findViewById(R.id.btnFilterAll);
        btnFilterPresent = findViewById(R.id.btnFilterPresent);
        btnFilterAbsent = findViewById(R.id.btnFilterAbsent);
        btnSubmit = findViewById(R.id.btnSubmit);
        btnNextLesson = findViewById(R.id.btnNextLesson);
    }

    // ================= Load the students =================

    private void loadRoster() {
        showState(true, null);
        RetrofitInstance.getAttendanceApi().roster(slotId).enqueue(new Callback<RosterResponse>() {
            @Override
            public void onResponse(@NonNull Call<RosterResponse> call, @NonNull Response<RosterResponse> response) {
                if (isFinishing() || ApiError.sessionEnded(MarkAttendanceActivity.this, response)) return;
                if (response.isSuccessful() && response.body() != null) {
                    showRoster(response.body());
                } else {
                    // e.g. 403 "This lesson was submitted more than 24 hours ago…"
                    showState(false, ApiError.message(MarkAttendanceActivity.this, response));
                    btnRetry.setVisibility(response.code() >= 500 ? View.VISIBLE : View.GONE);
                }
            }

            @Override
            public void onFailure(@NonNull Call<RosterResponse> call, @NonNull Throwable t) {
                if (isFinishing()) return;
                showState(false, ApiError.network(MarkAttendanceActivity.this));
            }
        });
    }

    private void showRoster(RosterResponse body) {
        roster = body;
        students.clear();
        students.addAll(body.getStudents());
        for (Student s : students) {
            statuses.put(s.getId(), s.getStatus());
            if (s.getAbsenceReason() != null) reasons.put(s.getId(), s.getAbsenceReason());
        }
        RosterLesson lesson = body.getLesson();
        txtLesson.setText(lesson.getSchoolClass().getName() + " · " + lesson.getSubject().name(khmer));
        txtLessonTime.setText(getString(R.string.period_time, lesson.getPeriod(), lesson.getStartTime(), lesson.getEndTime()));
        findViewById(R.id.txtAlreadyTaken).setVisibility(body.isAlreadyTaken() ? View.VISIBLE : View.GONE);
        showPage(PAGE_MARK);
    }

    // ================= Step 2: mark =================

    /** One tap: change the student's mark, update the numbers, and offer Undo for 4 seconds. */
    private void mark(Student student, String newStatus) {
        String old = statuses.get(student.getId());
        if (newStatus.equals(old)) return;
        statuses.put(student.getId(), newStatus);
        changed = true;
        refreshMark();

        if (undoBar != null) undoBar.dismiss();
        String text = getString(R.string.marked_as, student.displayName(khmer),
                getString(Student.ABSENT.equals(newStatus) ? R.string.absent : R.string.present));
        undoBar = Snackbar.make(pageMark, text, 4000)
                .setAnchorView(R.id.btnToReview)
                .setActionTextColor(getColor(R.color.button_start))
                .setAction(R.string.undo, v -> {
                    statuses.put(student.getId(), old);
                    refreshMark();
                });
        undoBar.show();
    }

    private void setUpFilters() {
        MaterialButtonToggleGroup group = findViewById(R.id.toggleFilter);
        group.addOnButtonCheckedListener((g, id, checked) -> {
            if (!checked) return;
            filter = id == R.id.btnFilterPresent ? Student.PRESENT : id == R.id.btnFilterAbsent ? Student.ABSENT : "all";
            refreshMark();
        });
        ((TextView) findViewById(R.id.edtSearch)).addTextChangedListener(new TextWatcher() {
            @Override public void beforeTextChanged(CharSequence s, int a, int b, int c) { }
            @Override public void onTextChanged(CharSequence s, int a, int b, int c) { }
            @Override public void afterTextChanged(Editable s) {
                query = s.toString().trim();
                refreshMark();
            }
        });
        findViewById(R.id.btnAllPresent).setOnClickListener(v -> {
            for (Student s : students) statuses.put(s.getId(), Student.PRESENT);
            changed = true;
            refreshMark();
        });
    }

    /** Redraw the numbers, the ring, the filter counts, the bottom bar and the visible list. */
    private void refreshMark() {
        int absent = countAbsent();
        int present = students.size() - absent;
        int rate = students.isEmpty() ? 0 : Math.round(present * 100f / students.size());

        ringRate.setProgressCompat(rate, true);
        ringRate.setIndicatorColor(Ui.rateColor(this, (double) rate));
        txtRate.setText(rate + "%");
        txtPresentCount.setText(getString(R.string.present_count, present));
        txtAbsentCount.setText(getString(R.string.absent_count, absent));
        btnFilterAll.setText(getString(R.string.filter_all, students.size()));
        btnFilterPresent.setText(getString(R.string.filter_present, present));
        btnFilterAbsent.setText(getString(R.string.filter_absent, absent));
        txtBottomSummary.setText(absent == 0 ? getString(R.string.all_present_bar) : getString(R.string.summary_bar, present, absent));
        txtBottomSummary.setTextColor(getColor(absent == 0 ? R.color.present_text : R.color.text_main));

        List<Student> visible = new ArrayList<>();
        for (Student s : students) {
            boolean statusOk = "all".equals(filter) || filter.equals(statuses.get(s.getId()));
            if (statusOk && s.matches(query)) visible.add(s);
        }
        adapter.show(visible);
        txtNoStudents.setVisibility(visible.isEmpty() ? View.VISIBLE : View.GONE);
    }

    private int countAbsent() {
        int absent = 0;
        for (Student s : students) if (Student.ABSENT.equals(statuses.get(s.getId()))) absent++;
        return absent;
    }

    // ================= Step 3: review and reasons =================

    private void buildReview() {
        RosterLesson lesson = roster.getLesson();
        int absent = countAbsent();
        ((TextView) findViewById(R.id.txtReviewLesson)).setText(
                getString(R.string.lesson_line, lesson.getSchoolClass().getName(), lesson.getSubject().name(khmer), lesson.getPeriod())
                        + " · " + Ui.longDate(lesson.getDate()));
        ((TextView) findViewById(R.id.txtReviewPresent)).setText(String.valueOf(students.size() - absent));
        ((TextView) findViewById(R.id.txtReviewAbsent)).setText(String.valueOf(absent));

        LinearLayout list = findViewById(R.id.absentList);
        list.removeAllViews();
        LayoutInflater inflater = LayoutInflater.from(this);
        for (Student s : students) {
            if (!Student.ABSENT.equals(statuses.get(s.getId()))) continue;
            View row = inflater.inflate(R.layout.item_absent_reason, list, false);
            ((TextView) row.findViewById(R.id.txtAvatar)).setText(s.initials());
            ((TextView) row.findViewById(R.id.txtName)).setText(s.displayName(khmer));
            ((TextView) row.findViewById(R.id.txtCode)).setText(s.getStudentCode());

            ChipGroup chips = row.findViewById(R.id.chipReasons);
            String chosen = reasons.containsKey(s.getId()) ? reasons.get(s.getId()) : "not_given";
            for (String code : roster.getAbsenceReasons()) {
                Chip chip = new Chip(this, null, com.google.android.material.R.attr.chipStyle);
                chip.setText(Ui.reasonLabel(this, code));
                chip.setCheckable(true);
                applyChipStyle(chip);
                chip.setChecked(code.equals(chosen));
                chip.setOnCheckedChangeListener((b, isChecked) -> {
                    if (isChecked) reasons.put(s.getId(), code);
                });
                chips.addView(chip);
            }
            list.addView(row);
        }
        findViewById(R.id.txtReasonHint).setVisibility(absent > 0 ? View.VISIBLE : View.GONE);
        findViewById(R.id.txtNobodyAbsent).setVisibility(absent == 0 ? View.VISIBLE : View.GONE);
    }

    /** Teal when chosen, white with a border otherwise (colors/chip_*.xml). */
    private void applyChipStyle(Chip chip) {
        Context c = this;
        chip.setChipBackgroundColor(androidx.core.content.ContextCompat.getColorStateList(c, R.color.chip_bg));
        chip.setTextColor(androidx.core.content.ContextCompat.getColorStateList(c, R.color.chip_text));
        chip.setChipStrokeColor(androidx.core.content.ContextCompat.getColorStateList(c, R.color.chip_stroke));
        chip.setChipStrokeWidth(getResources().getDisplayMetrics().density);
        chip.setCheckedIconVisible(false);
        chip.setTextSize(12);
        chip.setEnsureMinTouchTargetSize(false);
    }

    private void confirmSubmit() {
        int absent = countAbsent();
        BottomSheetDialog sheet = new BottomSheetDialog(this);
        View view = getLayoutInflater().inflate(R.layout.sheet_confirm, null);
        ((TextView) view.findViewById(R.id.txtTitle)).setText(R.string.submit_question);
        ((TextView) view.findViewById(R.id.txtText)).setText(R.string.submit_note);
        view.findViewById(R.id.statsRow).setVisibility(View.VISIBLE);
        ((TextView) view.findViewById(R.id.txtPresent)).setText(String.valueOf(students.size() - absent));
        ((TextView) view.findViewById(R.id.txtAbsent)).setText(String.valueOf(absent));
        ((Button) view.findViewById(R.id.btnCancel)).setText(R.string.cancel);
        ((Button) view.findViewById(R.id.btnConfirm)).setText(R.string.submit);
        view.findViewById(R.id.btnCancel).setOnClickListener(v -> sheet.dismiss());
        view.findViewById(R.id.btnConfirm).setOnClickListener(v -> {
            sheet.dismiss();
            submit();
        });
        sheet.setContentView(view);
        sheet.show();
    }

    private void submit() {
        RosterLesson lesson = roster.getLesson();
        SubmitRequest body = new SubmitRequest(lesson.getSchoolClass().getId(), lesson.getSubject().getId(),
                lesson.getDate(), lesson.getPeriod());
        for (Student s : students) {
            String status = statuses.get(s.getId());
            String reason = Student.ABSENT.equals(status) ? (reasons.containsKey(s.getId()) ? reasons.get(s.getId()) : "not_given") : null;
            body.add(s.getId(), status, reason);
        }

        setSubmitting(true);
        RetrofitInstance.getAttendanceApi().submit(body).enqueue(new Callback<SubmitResponse>() {
            @Override
            public void onResponse(@NonNull Call<SubmitResponse> call, @NonNull Response<SubmitResponse> response) {
                if (isFinishing()) return;
                setSubmitting(false);
                if (ApiError.sessionEnded(MarkAttendanceActivity.this, response)) return;
                if (response.isSuccessful() && response.body() != null) {
                    changed = false;
                    showDone(response.body(), response.code() == 200 && roster.isAlreadyTaken());
                } else {
                    Snackbar.make(pageReview, ApiError.message(MarkAttendanceActivity.this, response), Snackbar.LENGTH_LONG).show();
                }
            }

            @Override
            public void onFailure(@NonNull Call<SubmitResponse> call, @NonNull Throwable t) {
                if (isFinishing()) return;
                setSubmitting(false);
                // The marks stay on screen: the teacher can press Submit again when the connection is back
                Snackbar.make(pageReview, ApiError.network(MarkAttendanceActivity.this), Snackbar.LENGTH_LONG).show();
            }
        });
    }

    private void setSubmitting(boolean submitting) {
        btnSubmit.setEnabled(!submitting);
        btnSubmit.setAlpha(submitting ? 0.7f : 1f);
        btnSubmit.setText(submitting ? R.string.submitting : R.string.submit_attendance);
    }

    // ================= Step 4: done =================

    private void showDone(SubmitResponse result, boolean updated) {
        RosterLesson lesson = roster.getLesson();
        ((TextView) findViewById(R.id.txtDoneTitle)).setText(updated ? R.string.success_updated : R.string.success_title);
        ((TextView) findViewById(R.id.txtDoneLesson)).setText(
                getString(R.string.lesson_line, lesson.getSchoolClass().getName(), lesson.getSubject().name(khmer), lesson.getPeriod()));
        ((TextView) findViewById(R.id.txtDonePresent)).setText(String.valueOf(result.getPresent()));
        ((TextView) findViewById(R.id.txtDoneAbsent)).setText(String.valueOf(result.getAbsent()));

        TextView names = findViewById(R.id.txtDoneAbsentNames);
        StringBuilder text = new StringBuilder();
        for (SubmitResponse.AbsentStudent s : result.getAbsentStudents()) {
            if (text.length() > 0) text.append('\n');
            text.append("✗  ").append(s.displayName(khmer)).append(" · ").append(Ui.reasonLabel(this, s.getAbsenceReason()));
        }
        names.setText(text);
        names.setVisibility(text.length() > 0 ? View.VISIBLE : View.GONE);

        showPage(PAGE_DONE);
        findNextLesson();
    }

    /** Ask /today again: the first lesson still not marked becomes the "Next: …" button. */
    private void findNextLesson() {
        RetrofitInstance.getTeachingApi().today().enqueue(new Callback<TodayResponse>() {
            @Override
            public void onResponse(@NonNull Call<TodayResponse> call, @NonNull Response<TodayResponse> response) {
                if (isFinishing() || !response.isSuccessful() || response.body() == null) return;
                nextLesson = response.body().getNext();
                if (nextLesson == null) return;
                btnNextLesson.setText(getString(R.string.next_lesson, nextLesson.getSchoolClass().getName(),
                        nextLesson.getSubject().name(khmer)));
                btnNextLesson.setVisibility(View.VISIBLE);
            }

            @Override
            public void onFailure(@NonNull Call<TodayResponse> call, @NonNull Throwable t) {
                // No "Next" button; "Back to home" still works
            }
        });
    }

    private void openLesson(Lesson lesson) {
        if (lesson == null) return;
        Intent intent = new Intent(this, MarkAttendanceActivity.class);
        intent.putExtra(EXTRA_SLOT_ID, lesson.getSlotId());
        startActivity(intent);
        finish();
    }

    // ================= Pages, back button =================

    private void showPage(int newPage) {
        page = newPage;
        if (undoBar != null) undoBar.dismiss();
        stateBox.setVisibility(View.GONE);
        pageMark.setVisibility(newPage == PAGE_MARK ? View.VISIBLE : View.GONE);
        pageReview.setVisibility(newPage == PAGE_REVIEW ? View.VISIBLE : View.GONE);
        pageDone.setVisibility(newPage == PAGE_DONE ? View.VISIBLE : View.GONE);
        txtTitle.setText(newPage == PAGE_REVIEW ? R.string.review_attendance : R.string.mark_attendance);
        StepBar.show(stepBar, newPage == PAGE_DONE ? 4 : newPage == PAGE_REVIEW ? 3 : 2);
        if (newPage == PAGE_MARK) refreshMark();
        if (newPage == PAGE_REVIEW) buildReview();
    }

    private void showState(boolean loading, String message) {
        stateBox.setVisibility(View.VISIBLE);
        pageMark.setVisibility(View.GONE);
        stateProgress.setVisibility(loading ? View.VISIBLE : View.GONE);
        stateIcon.setVisibility(loading ? View.GONE : View.VISIBLE);
        stateText.setVisibility(loading ? View.GONE : View.VISIBLE);
        stateText.setText(message);
        btnRetry.setVisibility(loading ? View.GONE : View.VISIBLE);
    }

    /** Review → back to Mark. Mark with changes → "Leave without submitting?". Otherwise close. */
    private void goBack() {
        if (page == PAGE_REVIEW) {
            showPage(PAGE_MARK);
        } else if (page == PAGE_MARK && changed) {
            BottomSheetDialog sheet = new BottomSheetDialog(this);
            View view = getLayoutInflater().inflate(R.layout.sheet_confirm, null);
            ((TextView) view.findViewById(R.id.txtTitle)).setText(R.string.leave_question);
            ((TextView) view.findViewById(R.id.txtText)).setText(R.string.leave_text);
            MaterialButton cancel = view.findViewById(R.id.btnCancel);
            MaterialButton confirm = view.findViewById(R.id.btnConfirm);
            cancel.setText(R.string.keep_marking);
            confirm.setText(R.string.leave);
            confirm.setBackgroundTintList(android.content.res.ColorStateList.valueOf(getColor(R.color.absent)));
            cancel.setOnClickListener(v -> sheet.dismiss());
            confirm.setOnClickListener(v -> {
                sheet.dismiss();
                finish();
            });
            sheet.setContentView(view);
            sheet.show();
        } else {
            finish();
        }
    }

    /** Below the status bar at the top; above the navigation bar and the keyboard at the bottom. */
    private void handleSystemBarsAndKeyboard() {
        Ui.padBelowStatusBar(findViewById(R.id.topBar));
        View root = findViewById(R.id.markRoot);
        ViewCompat.setOnApplyWindowInsetsListener(root, (view, insets) -> {
            Insets bars = insets.getInsets(WindowInsetsCompat.Type.systemBars());
            Insets keyboard = insets.getInsets(WindowInsetsCompat.Type.ime());
            view.setPadding(0, 0, 0, Math.max(bars.bottom, keyboard.bottom));
            return insets;
        });
    }
}
