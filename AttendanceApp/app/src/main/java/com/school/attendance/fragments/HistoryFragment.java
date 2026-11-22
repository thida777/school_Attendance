package com.school.attendance.fragments;

import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.text.Editable;
import android.text.TextWatcher;
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

import com.google.android.material.bottomsheet.BottomSheetDialog;
import com.google.android.material.button.MaterialButton;
import com.google.android.material.button.MaterialButtonToggleGroup;
import com.google.android.material.chip.Chip;
import com.google.android.material.chip.ChipGroup;
import com.google.android.material.textfield.TextInputEditText;
import com.google.android.material.textfield.TextInputLayout;
import com.school.attendance.R;
import com.school.attendance.adapters.HistoryAdapter;
import com.school.attendance.config.ApiError;
import com.school.attendance.config.RetrofitInstance;
import com.school.attendance.config.Ui;
import com.school.attendance.models.ApiMessage;
import com.school.attendance.models.ClassListResponse;
import com.school.attendance.models.CorrectRequest;
import com.school.attendance.models.HistoryRecord;
import com.school.attendance.models.HistoryResponse;
import com.school.attendance.models.SchoolClass;
import com.school.attendance.models.Student;

import java.util.HashMap;
import java.util.Map;

import retrofit2.Call;
import retrofit2.Callback;
import retrofit2.Response;

/**
 * History tab: GET /history (30 records per page; the next page loads when you scroll near the end).
 * Filters: All / Absent only, a class, and a search. Each record can be changed (within 24 hours)
 * or sent as a request (after 24 hours) with POST /records/{id}/correct.
 */
public class HistoryFragment extends Fragment {

    private SwipeRefreshLayout swipeRefresh;
    private TextView txtTotal, stateText;
    private ImageView stateIcon;
    private View stateBox, stateProgress, btnRetry;
    private HistoryAdapter adapter;
    private final Handler handler = new Handler(Looper.getMainLooper());

    private String status = "";      // "" = all, "absent" = absent only
    private int classId = 0;         // 0 = all classes
    private String query = "";
    private int page = 0, lastPage = 1;
    private boolean loading;
    private Call<HistoryResponse> call;

    @Nullable
    @Override
    public View onCreateView(@NonNull LayoutInflater inflater, @Nullable ViewGroup container, @Nullable Bundle savedInstanceState) {
        return inflater.inflate(R.layout.fragment_history, container, false);
    }

    @Override
    public void onViewCreated(@NonNull View view, @Nullable Bundle savedInstanceState) {
        Ui.padBelowStatusBar(view.findViewById(R.id.historyHeader));
        swipeRefresh = view.findViewById(R.id.swipeRefresh);
        txtTotal = view.findViewById(R.id.txtTotal);
        stateBox = view.findViewById(R.id.stateBox);
        stateProgress = view.findViewById(R.id.stateProgress);
        stateIcon = view.findViewById(R.id.stateIcon);
        stateText = view.findViewById(R.id.stateText);
        btnRetry = view.findViewById(R.id.btnRetry);

        adapter = new HistoryAdapter(Ui.isKhmer(), this::showChangeSheet);
        RecyclerView recycler = view.findViewById(R.id.recyclerHistory);
        LinearLayoutManager layout = new LinearLayoutManager(requireContext());
        recycler.setLayoutManager(layout);
        recycler.setAdapter(adapter);
        // Load the next page when the last few rows come into view
        recycler.addOnScrollListener(new RecyclerView.OnScrollListener() {
            @Override
            public void onScrolled(@NonNull RecyclerView rv, int dx, int dy) {
                if (dy > 0 && !loading && page < lastPage
                        && layout.findLastVisibleItemPosition() >= adapter.getItemCount() - 5) {
                    loadPage(page + 1);
                }
            }
        });

        setUpStatusChips(view.findViewById(R.id.chipStatus));
        loadClassChips(view.findViewById(R.id.chipClasses));
        ((TextView) view.findViewById(R.id.edtSearch)).addTextChangedListener(new TextWatcher() {
            @Override public void beforeTextChanged(CharSequence s, int a, int b, int c) { }
            @Override public void onTextChanged(CharSequence s, int a, int b, int c) { }
            @Override public void afterTextChanged(Editable s) {
                // Wait until the teacher stops typing, then search once
                handler.removeCallbacksAndMessages(null);
                handler.postDelayed(() -> {
                    query = s.toString().trim();
                    reload();
                }, 400);
            }
        });

        swipeRefresh.setColorSchemeResources(R.color.brand);
        swipeRefresh.setOnRefreshListener(this::reload);
        btnRetry.setOnClickListener(v -> reload());
        reload();
    }

    // ---------- Filters ----------

    private void setUpStatusChips(ChipGroup group) {
        Chip all = Ui.makeChip(requireContext(), getString(R.string.all_statuses));
        Chip absent = Ui.makeChip(requireContext(), getString(R.string.absent_only));
        all.setId(View.generateViewId());
        absent.setId(View.generateViewId());
        group.addView(all);
        group.addView(absent);
        group.check(all.getId());
        group.setOnCheckedStateChangeListener((g, ids) -> {
            status = ids.contains(absent.getId()) ? Student.ABSENT : "";
            reload();
        });
    }

    /** "All classes" + one chip per class (GET /classes). */
    private void loadClassChips(ChipGroup group) {
        Chip all = Ui.makeChip(requireContext(), getString(R.string.all_classes));
        all.setId(View.generateViewId());
        all.setTag(0);
        group.addView(all);
        group.check(all.getId());
        group.setOnCheckedStateChangeListener((g, ids) -> {
            if (ids.isEmpty()) return;
            Object tag = g.findViewById(ids.get(0)).getTag();
            classId = tag instanceof Integer ? (Integer) tag : 0;
            reload();
        });
        RetrofitInstance.getTeachingApi().classes().enqueue(new Callback<ClassListResponse>() {
            @Override
            public void onResponse(@NonNull Call<ClassListResponse> c, @NonNull Response<ClassListResponse> response) {
                if (!isAdded() || !response.isSuccessful() || response.body() == null) return;
                for (SchoolClass sc : response.body().getData()) {
                    Chip chip = Ui.makeChip(requireContext(), sc.getName());
                    chip.setId(View.generateViewId());
                    chip.setTag(sc.getId());
                    group.addView(chip);
                }
            }

            @Override
            public void onFailure(@NonNull Call<ClassListResponse> c, @NonNull Throwable t) {
                // Only "All classes" then; the list still works
            }
        });
    }

    // ---------- Loading ----------

    private void reload() {
        adapter.clear();
        page = 0;
        lastPage = 1;
        showState(true, null);
        loadPage(1);
    }

    private void loadPage(int number) {
        if (call != null) call.cancel();
        loading = true;
        Map<String, String> filters = new HashMap<>();
        filters.put("page", String.valueOf(number));
        if (!status.isEmpty()) filters.put("status", status);
        if (classId > 0) filters.put("class_id", String.valueOf(classId));
        if (!query.isEmpty()) filters.put("q", query);

        call = RetrofitInstance.getHistoryApi().history(filters);
        call.enqueue(new Callback<HistoryResponse>() {
            @Override
            public void onResponse(@NonNull Call<HistoryResponse> c, @NonNull Response<HistoryResponse> response) {
                if (!isAdded()) return;
                loading = false;
                swipeRefresh.setRefreshing(false);
                if (ApiError.sessionEnded(getActivity(), response)) return;
                if (!response.isSuccessful() || response.body() == null) {
                    showState(false, ApiError.message(requireContext(), response));
                    return;
                }
                HistoryResponse body = response.body();
                page = body.getCurrentPage();
                lastPage = body.getLastPage();
                txtTotal.setText(getString(R.string.records_total, body.getTotal()));
                adapter.add(requireContext(), body.getData());
                if (adapter.getItemCount() == 0) {
                    showEmpty();
                } else {
                    stateBox.setVisibility(View.GONE);
                }
            }

            @Override
            public void onFailure(@NonNull Call<HistoryResponse> c, @NonNull Throwable t) {
                if (!isAdded() || c.isCanceled()) return;
                loading = false;
                swipeRefresh.setRefreshing(false);
                if (adapter.getItemCount() > 0) {
                    Toast.makeText(requireContext(), ApiError.network(requireContext()), Toast.LENGTH_LONG).show();
                } else {
                    showState(false, ApiError.network(requireContext()));
                }
            }
        });
    }

    private void showState(boolean isLoading, String message) {
        stateBox.setVisibility(View.VISIBLE);
        stateProgress.setVisibility(isLoading ? View.VISIBLE : View.GONE);
        stateIcon.setVisibility(isLoading ? View.GONE : View.VISIBLE);
        stateIcon.setImageResource(R.drawable.ic_wifi_off);
        stateText.setVisibility(isLoading ? View.GONE : View.VISIBLE);
        stateText.setText(message);
        btnRetry.setVisibility(isLoading ? View.GONE : View.VISIBLE);
    }

    private void showEmpty() {
        showState(false, getString(R.string.no_history));
        stateIcon.setImageResource(R.drawable.ic_history);
        btnRetry.setVisibility(View.GONE);
    }

    // ---------- Change one record ----------

    private void showChangeSheet(HistoryRecord record) {
        boolean direct = record.canChangeDirectly();
        boolean khmer = Ui.isKhmer();
        BottomSheetDialog sheet = new BottomSheetDialog(requireContext());
        View view = getLayoutInflater().inflate(R.layout.sheet_correct, null);

        ((TextView) view.findViewById(R.id.txtTitle)).setText(direct ? R.string.change_title : R.string.request_title);
        ((TextView) view.findViewById(R.id.txtStudent)).setText(record.getStudent().displayName(khmer) + " · "
                + getString(R.string.lesson_line, record.getSchoolClass().getName(), record.getSubject().name(khmer), record.getPeriod()));

        // Blue note within 24 hours; orange note when it becomes a request
        TextView note = view.findViewById(R.id.txtNote);
        note.setText(direct ? R.string.change_note_direct : R.string.change_note_request);
        if (!direct) {
            note.setBackgroundResource(R.drawable.bg_warning_box);
            note.setTextColor(requireContext().getColor(R.color.warning_text));
            note.setCompoundDrawableTintList(android.content.res.ColorStateList.valueOf(requireContext().getColor(R.color.warning_text)));
        }

        // Start on the other status: that is almost always the change wanted
        MaterialButtonToggleGroup toggle = view.findViewById(R.id.toggleStatus);
        toggle.check(record.isAbsent() ? R.id.btnPresent : R.id.btnAbsent);

        TextInputLayout reasonLayout = view.findViewById(R.id.layoutReason);
        reasonLayout.setHint(direct ? R.string.reason_optional_label : R.string.reason_label);
        TextInputEditText edtReason = view.findViewById(R.id.edtReason);
        TextView txtError = view.findViewById(R.id.txtError);
        MaterialButton btnSave = view.findViewById(R.id.btnSave);
        btnSave.setText(direct ? R.string.save_change : R.string.send_request);

        // Typing or choosing another status hides the old error
        toggle.addOnButtonCheckedListener((g, id, checked) -> txtError.setVisibility(View.GONE));
        edtReason.addTextChangedListener(new TextWatcher() {
            @Override public void beforeTextChanged(CharSequence s, int a, int b, int c) { }
            @Override public void onTextChanged(CharSequence s, int a, int b, int c) { }
            @Override public void afterTextChanged(Editable s) { txtError.setVisibility(View.GONE); }
        });

        btnSave.setOnClickListener(v -> {
            String newStatus = toggle.getCheckedButtonId() == R.id.btnPresent ? Student.PRESENT : Student.ABSENT;
            String reason = edtReason.getText() == null ? "" : edtReason.getText().toString().trim();
            if (newStatus.equals(record.getStatus())) {
                showSheetError(txtError, getString(R.string.same_status));
                return;
            }
            if (!direct && reason.isEmpty()) {
                showSheetError(txtError, getString(R.string.reason_required));
                return;
            }
            btnSave.setEnabled(false);
            btnSave.setText(R.string.saving);
            RetrofitInstance.getHistoryApi().correct(record.getId(), new CorrectRequest(newStatus, reason.isEmpty() ? null : reason))
                    .enqueue(new Callback<ApiMessage>() {
                        @Override
                        public void onResponse(@NonNull Call<ApiMessage> c, @NonNull Response<ApiMessage> response) {
                            if (!isAdded()) return;
                            if (ApiError.sessionEnded(getActivity(), response)) return;
                            if (response.isSuccessful() && response.body() != null) {
                                // 200 "Attendance changed…" or 202 "Request sent…", in the app's language
                                sheet.dismiss();
                                Toast.makeText(requireContext(), response.body().getMessage(), Toast.LENGTH_LONG).show();
                                reload();
                            } else {
                                btnSave.setEnabled(true);
                                btnSave.setText(direct ? R.string.save_change : R.string.send_request);
                                showSheetError(txtError, ApiError.message(requireContext(), response));
                            }
                        }

                        @Override
                        public void onFailure(@NonNull Call<ApiMessage> c, @NonNull Throwable t) {
                            if (!isAdded()) return;
                            btnSave.setEnabled(true);
                            btnSave.setText(direct ? R.string.save_change : R.string.send_request);
                            showSheetError(txtError, ApiError.network(requireContext()));
                        }
                    });
        });

        sheet.setContentView(view);
        sheet.show();
    }

    private static void showSheetError(TextView view, String message) {
        view.setText(message);
        view.setVisibility(View.VISIBLE);
    }

    @Override
    public void onDestroyView() {
        handler.removeCallbacksAndMessages(null);
        if (call != null) call.cancel();
        super.onDestroyView();
    }
}
