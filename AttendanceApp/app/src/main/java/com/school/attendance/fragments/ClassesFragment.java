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

import com.google.gson.Gson;
import com.school.attendance.R;
import com.school.attendance.activities.ClassDetailActivity;
import com.school.attendance.adapters.ClassCardAdapter;
import com.school.attendance.config.ApiError;
import com.school.attendance.config.RetrofitInstance;
import com.school.attendance.config.Ui;
import com.school.attendance.models.ClassListResponse;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/** Classes tab: GET /classes, one card per class; tap a card → Class details. */
public class ClassesFragment extends Fragment {

    private SwipeRefreshLayout swipeRefresh;
    private TextView txtCount, stateText;
    private ImageView stateIcon;
    private View stateBox, stateProgress, btnRetry;
    private ClassCardAdapter adapter;
    private boolean loadedOnce;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        return inflater.inflate(R.layout.fragment_classes, container, false);
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        Ui.padBelowStatusBar(view.findViewById(R.id.classesHeader));
        swipeRefresh = view.findViewById(R.id.swipeRefresh);
        txtCount = view.findViewById(R.id.txtCount);
        stateBox = view.findViewById(R.id.stateBox);
        stateProgress = view.findViewById(R.id.stateProgress);
        stateIcon = view.findViewById(R.id.stateIcon);
        stateText = view.findViewById(R.id.stateText);
        btnRetry = view.findViewById(R.id.btnRetry);

        adapter = new ClassCardAdapter(Ui.isKhmer(), c -> {
            Intent intent = new Intent(requireContext(), ClassDetailActivity.class);
            intent.putExtra(ClassDetailActivity.EXTRA_CLASS, new Gson().toJson(c));
            startActivity(intent);
        });
        RecyclerView recycler = view.findViewById(R.id.recyclerClasses);
        recycler.setLayoutManager(new LinearLayoutManager(requireContext()));
        recycler.setAdapter(adapter);

        swipeRefresh.setColorSchemeResources(R.color.brand);
        swipeRefresh.setOnRefreshListener(this::load);
        btnRetry.setOnClickListener(v -> load());
        load();
    }

    private void load() {
        if (!loadedOnce) showState(true, null);
        RetrofitInstance.getTeachingApi().classes().enqueue(new Callback<ClassListResponse>() {
            @Override
            public void onResponse(@NonNull Call<ClassListResponse> call, @NonNull Response<ClassListResponse> response) {
                if (!isAdded()) return;
                swipeRefresh.setRefreshing(false);
                if (ApiError.sessionEnded(getActivity(), response)) return;
                if (response.isSuccessful() && response.body() != null) {
                    loadedOnce = true;
                    stateBox.setVisibility(View.GONE);
                    txtCount.setText(getString(R.string.classes_count, response.body().getData().size()));
                    adapter.setClasses(response.body().getData());
                } else {
                    failed(ApiError.message(requireContext(), response));
                }
            }

            @Override
            public void onFailure(@NonNull Call<ClassListResponse> call, @NonNull Throwable t) {
                if (!isAdded()) return;
                swipeRefresh.setRefreshing(false);
                failed(ApiError.network(requireContext()));
            }
        });
    }

    private void failed(String message) {
        if (loadedOnce) {
            Toast.makeText(requireContext(), message, Toast.LENGTH_LONG).show();
        } else {
            showState(false, message);
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
