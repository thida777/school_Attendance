package com.school.attendance.fragments;

import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.school.attendance.R;
import com.school.attendance.activities.MainActivity;
import com.school.attendance.activities.MarkAttendanceActivity;
import com.school.attendance.adapters.LessonAdapter;
import com.school.attendance.config.ApiError;
import com.school.attendance.config.RetrofitInstance;
import com.school.attendance.config.Session;
import com.school.attendance.config.Ui;
import com.school.attendance.models.Lesson;
import com.school.attendance.models.TodayResponse;
import com.school.attendance.models.User;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * Dashboard (Home tab). Loads GET /today and shows:
 * the header (name, date, photo, 2 chips), the Next class card, and Today's schedule.
 */
public class HomeFragment extends Fragment {

    private SwipeRefreshLayout swipeRefresh;
    private TextView txtName, txtDate, txtInitials, chipAbsent, chipMarked;
    private TextView txtNextTime, txtNextClass, txtNextSubject, txtDoneCount, stateText;
    private ImageView imgPhoto, stateIcon;
    private View stateBox, stateProgress, btnRetry, cardNext, cardAllDone, cardEmpty, cardSchedule;
    private LessonAdapter adapter;
    private Lesson nextLesson;
    private Call<TodayResponse> call;
    private boolean loadedOnce;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        return inflater.inflate(R.layout.fragment_home, container, false);
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        Ui.padBelowStatusBar(view.findViewById(R.id.homeHeader));

        swipeRefresh = view.findViewById(R.id.swipeRefresh);
        txtName = view.findViewById(R.id.txtName);
        txtDate = view.findViewById(R.id.txtDate);
        txtInitials = view.findViewById(R.id.txtInitials);
        imgPhoto = view.findViewById(R.id.imgPhoto);
        chipAbsent = view.findViewById(R.id.chipAbsent);
        chipMarked = view.findViewById(R.id.chipMarked);
        stateBox = view.findViewById(R.id.stateBox);
        stateProgress = view.findViewById(R.id.stateProgress);
        stateIcon = view.findViewById(R.id.stateIcon);
        stateText = view.findViewById(R.id.stateText);
        btnRetry = view.findViewById(R.id.btnRetry);
        cardNext = view.findViewById(R.id.cardNext);
        cardAllDone = view.findViewById(R.id.cardAllDone);
        cardEmpty = view.findViewById(R.id.cardEmpty);
        cardSchedule = view.findViewById(R.id.cardSchedule);
        txtNextTime = view.findViewById(R.id.txtNextTime);
        txtNextClass = view.findViewById(R.id.txtNextClass);
        txtNextSubject = view.findViewById(R.id.txtNextSubject);
        txtDoneCount = view.findViewById(R.id.txtDoneCount);

        adapter = new LessonAdapter(Ui.isKhmer(), this::openLesson);
        RecyclerView recycler = view.findViewById(R.id.recyclerLessons);
        recycler.setLayoutManager(new LinearLayoutManager(requireContext()));
        recycler.setAdapter(adapter);

        swipeRefresh.setColorSchemeResources(R.color.brand);
        swipeRefresh.setOnRefreshListener(this::loadToday);
        btnRetry.setOnClickListener(v -> loadToday());
        view.findViewById(R.id.btnStart).setOnClickListener(v -> openLesson(nextLesson));
        view.findViewById(R.id.btnAvatar).setOnClickListener(v -> ((MainActivity) requireActivity()).openTab(R.id.nav_profile));
        view.findViewById(R.id.btnViewHistory).setOnClickListener(v -> ((MainActivity) requireActivity()).openTab(R.id.nav_history));

        showTeacher();
        showLoading();
        loadToday();
    }

    @Override
    public void onResume() {
        super.onResume();
        showTeacher(); // the photo may have changed on the Profile tab
        if (loadedOnce) loadToday(); // back from marking: show the new "Done" status
    }

    private void showTeacher() {
        User user = Session.getUser();
        if (user == null) return;
        txtName.setText(user.displayName(Ui.isKhmer()));
        Ui.showAvatar(imgPhoto, txtInitials, user);
    }

    private void loadToday() {
        if (call != null) call.cancel();
        call = RetrofitInstance.getTeachingApi().today();
        call.enqueue(new Callback<TodayResponse>() {
            @Override
            public void onResponse(@NonNull Call<TodayResponse> c, @NonNull Response<TodayResponse> response) {
                if (!isAdded()) return;
                swipeRefresh.setRefreshing(false);
                if (ApiError.sessionEnded(getActivity(), response)) return;
                if (response.isSuccessful() && response.body() != null) {
                    showToday(response.body());
                } else {
                    showError(ApiError.message(requireContext(), response));
                }
            }

            @Override
            public void onFailure(@NonNull Call<TodayResponse> c, @NonNull Throwable t) {
                if (!isAdded() || c.isCanceled()) return;
                swipeRefresh.setRefreshing(false);
                showError(ApiError.network(requireContext()));
            }
        });
    }

    private void showToday(TodayResponse today) {
        loadedOnce = true;
        stateBox.setVisibility(View.GONE);
        txtDate.setText(Ui.longDate(today.getDate()));
        chipAbsent.setText(getString(R.string.absent_today, today.getAbsentToday()));
        chipMarked.setText(getString(R.string.classes_marked, today.getDone(), today.getTotal()));

        boolean noLessons = today.getTotal() == 0;
        nextLesson = today.getNext();

        cardEmpty.setVisibility(noLessons ? View.VISIBLE : View.GONE);
        cardSchedule.setVisibility(noLessons ? View.GONE : View.VISIBLE);
        cardNext.setVisibility(!noLessons && nextLesson != null ? View.VISIBLE : View.GONE);
        cardAllDone.setVisibility(!noLessons && nextLesson == null ? View.VISIBLE : View.GONE);

        if (nextLesson != null) {
            boolean khmer = Ui.isKhmer();
            txtNextTime.setText(getString(R.string.period_time, nextLesson.getPeriod(),
                    nextLesson.getStartTime(), nextLesson.getEndTime()));
            txtNextClass.setText(nextLesson.getSchoolClass().getName());
            txtNextSubject.setText(getString(R.string.subject_students,
                    nextLesson.getSubject().name(khmer), nextLesson.getStudents()));
        }

        txtDoneCount.setText(getString(R.string.done_count, today.getDone(), today.getTotal()));
        adapter.setLessons(today.getLessons(), nextLesson);
    }

    private void showLoading() {
        stateBox.setVisibility(View.VISIBLE);
        stateProgress.setVisibility(View.VISIBLE);
        stateIcon.setVisibility(View.GONE);
        stateText.setVisibility(View.GONE);
        btnRetry.setVisibility(View.GONE);
    }

    /** "Cannot connect" box with a Try again button (the lessons already shown stay). */
    private void showError(String message) {
        if (cardSchedule.getVisibility() == View.VISIBLE) {
            Toast.makeText(requireContext(), message, Toast.LENGTH_LONG).show();
            return;
        }
        stateBox.setVisibility(View.VISIBLE);
        stateProgress.setVisibility(View.GONE);
        stateIcon.setVisibility(View.VISIBLE);
        stateText.setVisibility(View.VISIBLE);
        stateText.setText(message);
        btnRetry.setVisibility(View.VISIBLE);
    }

    /** Open the marking screen for this lesson (Start for a new one, Edit for one already marked). */
    private void openLesson(Lesson lesson) {
        if (lesson == null) return;
        Intent intent = new Intent(requireContext(), MarkAttendanceActivity.class);
        intent.putExtra(MarkAttendanceActivity.EXTRA_SLOT_ID, lesson.getSlotId());
        startActivity(intent);
    }

    @Override
    public void onDestroyView() {
        if (call != null) call.cancel();
        super.onDestroyView();
    }
}
