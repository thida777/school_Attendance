package com.school.attendance.adapters;

import android.content.Context;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import com.google.android.material.progressindicator.LinearProgressIndicator;
import com.school.attendance.R;
import com.school.attendance.config.Ui;
import com.school.attendance.models.SchoolClass;
import com.school.attendance.models.Subject;

import java.util.ArrayList;
import java.util.List;

/** The Classes tab: one card per class, with the rate in the website's colours (green / orange / red). */
public class ClassCardAdapter extends RecyclerView.Adapter<ClassCardAdapter.ClassViewHolder> {

    public interface OnClassClick {
        void onClick(SchoolClass schoolClass);
    }

    private final List<SchoolClass> classes = new ArrayList<>();
    private final boolean khmer;
    private final OnClassClick listener;

    public ClassCardAdapter(boolean khmer, OnClassClick listener) {
        this.khmer = khmer;
        this.listener = listener;
    }

    public void setClasses(List<SchoolClass> newClasses) {
        classes.clear();
        classes.addAll(newClasses);
        notifyDataSetChanged();
    }

    @NonNull
    @Override
    public ClassViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        return new ClassViewHolder(LayoutInflater.from(parent.getContext()).inflate(R.layout.item_class_card, parent, false));
    }

    @Override
    public void onBindViewHolder(@NonNull ClassViewHolder h, int position) {
        SchoolClass c = classes.get(position);
        Context ctx = h.itemView.getContext();
        Double rate = c.getAttendanceRate();

        h.txtName.setText(c.getName());
        h.txtHomeroom.setVisibility(c.isHomeroom() ? View.VISIBLE : View.GONE);
        h.txtGrade.setText(gradeText(ctx, c));
        h.txtRate.setText(rate == null ? "–" : Ui.rateText(rate));
        h.txtRate.setTextColor(Ui.rateColor(ctx, rate));
        h.txtRateLabel.setText(rate == null ? R.string.no_records_yet : R.string.attendance_label);
        h.barRate.setProgressCompat(rate == null ? 0 : (int) Math.round(rate), false);
        h.barRate.setIndicatorColor(Ui.rateColor(ctx, rate));
        h.txtStudents.setText(ctx.getString(R.string.students_count, c.getStudents()));

        StringBuilder subjects = new StringBuilder();
        if (c.getSubjects() != null) {
            for (Subject s : c.getSubjects()) {
                if (subjects.length() > 0) subjects.append(", ");
                subjects.append(s.name(khmer));
            }
        }
        h.txtSubjects.setText(subjects);
        h.card.setOnClickListener(v -> listener.onClick(c));
    }

    /** "Grade 10 · Room 101" (or only the grade when the class has no room). */
    public static String gradeText(Context ctx, SchoolClass c) {
        return c.getRoom() == null || c.getRoom().isEmpty()
                ? ctx.getString(R.string.grade_only, c.getGrade())
                : ctx.getString(R.string.grade_room, c.getGrade(), c.getRoom());
    }

    @Override
    public int getItemCount() {
        return classes.size();
    }

    static class ClassViewHolder extends RecyclerView.ViewHolder {
        final View card;
        final TextView txtName, txtHomeroom, txtGrade, txtRate, txtRateLabel, txtStudents, txtSubjects;
        final LinearProgressIndicator barRate;

        ClassViewHolder(@NonNull View view) {
            super(view);
            card = view.findViewById(R.id.cardClass);
            txtName = view.findViewById(R.id.txtName);
            txtHomeroom = view.findViewById(R.id.txtHomeroom);
            txtGrade = view.findViewById(R.id.txtGrade);
            txtRate = view.findViewById(R.id.txtRate);
            txtRateLabel = view.findViewById(R.id.txtRateLabel);
            txtStudents = view.findViewById(R.id.txtStudents);
            txtSubjects = view.findViewById(R.id.txtSubjects);
            barRate = view.findViewById(R.id.barRate);
        }
    }
}
