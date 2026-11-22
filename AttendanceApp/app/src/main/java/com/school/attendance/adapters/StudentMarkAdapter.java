package com.school.attendance.adapters;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.ImageView;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.RecyclerView;

import com.school.attendance.R;
import com.school.attendance.models.Student;

import java.util.ArrayList;
import java.util.List;
import java.util.Map;

/**
 * The marking list (step 2). Each row: avatar, name, ID, and a ✓ / ✗ switch.
 * Tapping the row switches Present ↔ Absent; the ✓ and ✗ buttons set it directly.
 * The marks live in MarkAttendanceActivity (statuses map); this adapter only shows them.
 */
public class StudentMarkAdapter extends RecyclerView.Adapter<StudentMarkAdapter.StudentViewHolder> {

    public interface OnMark {
        void onMark(Student student, String newStatus);
    }

    private final List<Student> visible = new ArrayList<>();
    private final Map<Integer, String> statuses;
    private final boolean khmer;
    private final OnMark listener;

    public StudentMarkAdapter(Map<Integer, String> statuses, boolean khmer, OnMark listener) {
        this.statuses = statuses;
        this.khmer = khmer;
        this.listener = listener;
    }

    public void show(List<Student> students) {
        visible.clear();
        visible.addAll(students);
        notifyDataSetChanged();
    }

    @NonNull
    @Override
    public StudentViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        return new StudentViewHolder(LayoutInflater.from(parent.getContext()).inflate(R.layout.item_student_mark, parent, false));
    }

    @Override
    public void onBindViewHolder(@NonNull StudentViewHolder h, int position) {
        Student student = visible.get(position);
        boolean absent = Student.ABSENT.equals(statuses.get(student.getId()));
        android.content.Context c = h.itemView.getContext();

        h.txtName.setText(student.displayName(khmer));
        h.txtAvatar.setText(student.initials());
        h.txtAvatar.setBackgroundResource(absent ? R.drawable.bg_avatar_absent : R.drawable.bg_avatar_present);
        h.txtAvatar.setTextColor(ContextCompat.getColor(c, absent ? R.color.absent_text : R.color.brand));
        h.txtSub.setText(absent ? student.getStudentCode() + " · " + c.getString(R.string.absent) : student.getStudentCode());
        h.txtSub.setTextColor(ContextCompat.getColor(c, absent ? R.color.absent_text : R.color.text_muted));
        h.row.setBackgroundResource(absent ? R.drawable.bg_row_absent : android.R.color.transparent);

        // ✓ is green when Present, ✗ is red when Absent; the other one stays grey
        h.btnPresent.setBackgroundResource(absent ? 0 : R.drawable.bg_seg_present);
        h.btnPresent.setColorFilter(ContextCompat.getColor(c, absent ? R.color.text_muted : R.color.white));
        h.btnAbsent.setBackgroundResource(absent ? R.drawable.bg_seg_absent : 0);
        h.btnAbsent.setColorFilter(ContextCompat.getColor(c, absent ? R.color.white : R.color.text_muted));

        h.row.setOnClickListener(v -> listener.onMark(student, absent ? Student.PRESENT : Student.ABSENT));
        h.btnPresent.setOnClickListener(v -> listener.onMark(student, Student.PRESENT));
        h.btnAbsent.setOnClickListener(v -> listener.onMark(student, Student.ABSENT));
        h.divider.setVisibility(position == visible.size() - 1 ? View.GONE : View.VISIBLE);
    }

    @Override
    public int getItemCount() {
        return visible.size();
    }

    static class StudentViewHolder extends RecyclerView.ViewHolder {
        final View row, divider;
        final TextView txtAvatar, txtName, txtSub;
        final ImageView btnPresent, btnAbsent;

        StudentViewHolder(@NonNull View view) {
            super(view);
            row = view.findViewById(R.id.rowStudent);
            divider = view.findViewById(R.id.divider);
            txtAvatar = view.findViewById(R.id.txtAvatar);
            txtName = view.findViewById(R.id.txtName);
            txtSub = view.findViewById(R.id.txtSub);
            btnPresent = view.findViewById(R.id.btnPresent);
            btnAbsent = view.findViewById(R.id.btnAbsent);
        }
    }
}
