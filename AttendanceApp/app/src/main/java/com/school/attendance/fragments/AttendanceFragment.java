package com.school.attendance.fragments;

import android.content.Intent;
import android.os.Bundle;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.annotation.Nullable;
import androidx.fragment.app.Fragment;
import androidx.recyclerview.widget.LinearLayoutManager;
import androidx.recyclerview.widget.RecyclerView;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

import com.school.attendance.R;
import com.school.attendance.activities.MarkAttendanceActivity;
import com.school.attendance.adapters.LessonCardAdapter;
import com.school.attendance.config.ApiError;
import com.school.attendance.config.RetrofitInstance;
import com.school.attendance.config.StepBar;
import com.school.attendance.config.Ui;
import com.school.attendance.models.Lesson;
import com.school.attendance.models.TodayResponse;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/** Attendance tab = step 1: today's lessons as cards with Start (or Edit when already marked). */
public class AttendanceFragment extends Fragment {

    private SwipeRefreshLayout swipeRefresh;
    private TextView txtDate, txtDoneCount, stateText;
    private ImageView stateIcon;
    private View stateBox, stateProgress, btnRetry;
    private LessonCardAdapter adapter;
    private Call<TodayResponse> call;
    private boolean loadedOnce;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        return inflater.inflate(R.layout.fragment_attendance, container, false);
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        Ui.padBelowStatusBar(view.findViewById(R.id.attendanceHeader));
        StepBar.show(view.findViewById(R.id.stepBar), 1);

        swipeRefresh = view.findViewById(R.id.swipeRefresh);
        txtDate = view.findViewById(R.id.txtDate);
        txtDoneCount = view.findViewById(R.id.txtDoneCount);
        stateBox = view.findViewById(R.id.stateBox);
        stateProgress = view.findViewById(R.id.stateProgress);
        stateIcon = view.findViewById(R.id.stateIcon);
        stateText = view.findViewById(R.id.stateText);
        btnRetry = view.findViewById(R.id.btnRetry);

        adapter = new LessonCardAdapter(Ui.isKhmer(), this::openLesson);
        RecyclerView recycler = view.findViewById(R.id.recyclerLessons);
        recycler.setLayoutManager(new LinearLayoutManager(requireContext()));
        recycler.setAdapter(adapter);

        swipeRefresh.setColorSchemeResources(R.color.brand);
        swipeRefresh.setOnRefreshListener(this::load);
        btnRetry.setOnClickListener(v -> load());
        load();
    }

    @Override
    public void onResume() {
        super.onResume();
        if (loadedOnce) load(); // back from marking: show the new "Done" status
    }

    private void load() {
        if (call != null) call.cancel();
        if (!loadedOnce) showState(true, null, 0);
        call = RetrofitInstance.getTeachingApi().today();
        call.enqueue(new Callback<TodayResponse>() {
            @Override
            public void onResponse(@NonNull Call<TodayResponse> c, @NonNull Response<TodayResponse> response) {
                if (!isAdded()) return;
                swipeRefresh.setRefreshing(false);
                if (ApiError.sessionEnded(getActivity(), response)) return;
                if (response.isSuccessful() && response.body() != null) {
                    loadedOnce = true;
                    show(response.body());
                } else {
                    showState(false, ApiError.message(requireContext(), response), R.drawable.ic_alert);
                }
            }

            @Override
            public void onFailure(@NonNull Call<TodayResponse> c, @NonNull Throwable t) {
                if (!isAdded() || c.isCanceled()) return;
                swipeRefresh.setRefreshing(false);
                if (loadedOnce) {
                    // Lessons are already on screen: keep them, just say the refresh failed
                    android.widget.Toast.makeText(requireContext(), ApiError.network(requireContext()), android.widget.Toast.LENGTH_LONG).show();
                } else {
                    showState(false, ApiError.network(requireContext()), R.drawable.ic_wifi_off);
                }
            }
        });
    }

    private void show(TodayResponse today) {
        txtDate.setText(Ui.longDate(today.getDate()));
        txtDoneCount.setText(getString(R.string.done_count, today.getDone(), today.getTotal()));
        adapter.setLessons(today.getLessons(), today.getNext());
        if (today.getTotal() == 0) {
            showState(false, getString(R.string.no_lessons_title) + "\n" + getString(R.string.no_lessons_text), R.drawable.ic_coffee);
            btnRetry.setVisibility(View.GONE);
        } else {
            stateBox.setVisibility(View.GONE);
        }
    }

    private void showState(boolean loading, String message, int icon) {
        stateBox.setVisibility(View.VISIBLE);
        stateProgress.setVisibility(loading ? View.VISIBLE : View.GONE);
        stateIcon.setVisibility(loading ? View.GONE : View.VISIBLE);
        if (icon != 0) stateIcon.setImageResource(icon);
        stateText.setVisibility(loading ? View.GONE : View.VISIBLE);
        stateText.setText(message);
        btnRetry.setVisibility(loading ? View.GONE : View.VISIBLE);
    }

    private void openLesson(Lesson lesson) {
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
