package com.school.attendance.adapters;

import android.content.Context;
import android.content.res.ColorStateList;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.RecyclerView;

import com.google.android.material.button.MaterialButton;
import com.school.attendance.R;
import com.school.attendance.config.Ui;
import com.school.attendance.models.HistoryRecord;
import com.school.attendance.models.Student;

import java.util.ArrayList;
import java.util.List;

/**
 * History list: a heading for each lesson ("Tuesday, 17 November 2026 · 11B · Mathematics · Period 1"),
 * then one row per student with Present / Absent and the button:
 *   Change (within 24 hours) · Request (after 24 hours) · Pending (a request is waiting for the admin).
 */
public class HistoryAdapter extends RecyclerView.Adapter<RecyclerView.ViewHolder> {

    public interface OnChange {
        void onChange(HistoryRecord record);
    }

    private static final int TYPE_HEADER = 0, TYPE_RECORD = 1;

    /** Mixed list: String = a lesson heading, HistoryRecord = a row. */
    private final List<Object> items = new ArrayList<>();
    private final boolean khmer;
    private final OnChange listener;
    private String lastKey;

    public HistoryAdapter(boolean khmer, OnChange listener) {
        this.khmer = khmer;
        this.listener = listener;
    }

    public void clear() {
        items.clear();
        lastKey = null;
        notifyDataSetChanged();
    }

    /** Add the next page. A heading is added only when the lesson changes. */
    public void add(Context c, List<HistoryRecord> records) {
        int start = items.size();
        for (HistoryRecord r : records) {
            if (!r.lessonKey().equals(lastKey)) {
                lastKey = r.lessonKey();
                items.add(Ui.longDate(r.getDate()) + " · "
                        + c.getString(R.string.lesson_line, r.getSchoolClass().getName(), r.getSubject().name(khmer), r.getPeriod()));
            }
            items.add(r);
        }
        notifyItemRangeInserted(start, items.size() - start);
    }

    @Override
    public int getItemViewType(int position) {
        return items.get(position) instanceof String ? TYPE_HEADER : TYPE_RECORD;
    }

    @NonNull
    @Override
    public RecyclerView.ViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        LayoutInflater inflater = LayoutInflater.from(parent.getContext());
        if (viewType == TYPE_HEADER) {
            return new HeaderHolder(inflater.inflate(R.layout.item_history_header, parent, false));
        }
        return new RecordHolder(inflater.inflate(R.layout.item_history_record, parent, false));
    }

    @Override
    public void onBindViewHolder(@NonNull RecyclerView.ViewHolder holder, int position) {
        Object item = items.get(position);
        if (holder instanceof HeaderHolder) {
            ((HeaderHolder) holder).text.setText((String) item);
            return;
        }
        RecordHolder h = (RecordHolder) holder;
        HistoryRecord r = (HistoryRecord) item;
        Context c = h.itemView.getContext();
        Student s = r.getStudent();
        boolean absent = r.isAbsent();

        h.txtName.setText(s.displayName(khmer));
        h.txtAvatar.setText(s.initials());
        h.txtAvatar.setBackgroundResource(absent ? R.drawable.bg_avatar_absent : R.drawable.bg_avatar_present);
        h.txtAvatar.setTextColor(ContextCompat.getColor(c, absent ? R.color.absent_text : R.color.brand));
        h.txtStatus.setText(absent ? R.string.absent : R.string.present);
        h.txtStatus.setBackgroundResource(absent ? R.drawable.bg_chip_absent : R.drawable.bg_chip_present);
        h.txtStatus.setTextColor(ContextCompat.getColor(c, absent ? R.color.absent_text : R.color.present_text));
        h.txtSub.setText(absent ? Ui.reasonLabel(c, r.getAbsenceReason()) + " · " + s.getStudentCode() : s.getStudentCode());

        if (r.hasPendingRequest()) {
            style(h.btnChange, R.string.request_pending, R.drawable.ic_clock, R.color.warning_soft, R.color.warning_text);
            h.btnChange.setEnabled(false);
        } else if (r.canChangeDirectly()) {
            style(h.btnChange, R.string.change, R.drawable.ic_edit, R.color.brand_soft, R.color.brand);
            h.btnChange.setEnabled(true);
        } else {
            style(h.btnChange, R.string.request_change, R.drawable.ic_send, R.color.field, R.color.text_main);
            h.btnChange.setEnabled(true);
        }
        h.btnChange.setOnClickListener(v -> listener.onChange(r));
    }

    private static void style(MaterialButton b, int text, int icon, int background, int color) {
        Context c = b.getContext();
        b.setText(text);
        b.setIconResource(icon);
        b.setBackgroundTintList(ColorStateList.valueOf(ContextCompat.getColor(c, background)));
        b.setTextColor(ContextCompat.getColor(c, color));
        b.setIconTint(ColorStateList.valueOf(ContextCompat.getColor(c, color)));
    }

    @Override
    public int getItemCount() {
        return items.size();
    }

    static class HeaderHolder extends RecyclerView.ViewHolder {
        final TextView text;

        HeaderHolder(@NonNull View view) {
            super(view);
            text = view.findViewById(R.id.txtHeader);
        }
    }

    static class RecordHolder extends RecyclerView.ViewHolder {
        final TextView txtAvatar, txtName, txtStatus, txtSub;
        final MaterialButton btnChange;

        RecordHolder(@NonNull View view) {
            super(view);
            txtAvatar = view.findViewById(R.id.txtAvatar);
            txtName = view.findViewById(R.id.txtName);
            txtStatus = view.findViewById(R.id.txtStatus);
            txtSub = view.findViewById(R.id.txtSub);
            btnChange = view.findViewById(R.id.btnChange);
        }
    }
}
