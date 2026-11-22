package com.school.attendance.adapters;

import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.TextView;

import androidx.annotation.NonNull;
import androidx.core.content.ContextCompat;
import androidx.recyclerview.widget.RecyclerView;

import com.school.attendance.R;
import com.school.attendance.models.Lesson;

import java.util.ArrayList;
import java.util.List;

/**
 * Today's schedule on the Dashboard (RecyclerView, like in class).
 * Each row: start time + period, a dot, class + subject, and a status chip:
 *   done → green "6/7" (present / all), next → teal "Up next", the rest → grey "Later".
 */
public class LessonAdapter extends RecyclerView.Adapter<LessonAdapter.LessonViewHolder> {

    public interface OnLessonClick {
        void onClick(Lesson lesson);
    }

    private final List<Lesson> lessons = new ArrayList<>();
    private final OnLessonClick listener;
    private final boolean khmer;
    private int nextSlotId = -1;

    public LessonAdapter(boolean khmer, OnLessonClick listener) {
        this.khmer = khmer;
        this.listener = listener;
    }

    public void setLessons(List<Lesson> newLessons, Lesson next) {
        lessons.clear();
        lessons.addAll(newLessons);
        nextSlotId = next == null ? -1 : next.getSlotId();
        notifyDataSetChanged();
    }

    @NonNull
    @Override
    public LessonViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext()).inflate(R.layout.item_lesson, parent, false);
        return new LessonViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull LessonViewHolder holder, int position) {
        Lesson lesson = lessons.get(position);
        View item = holder.itemView;

        holder.txtStart.setText(lesson.getStartTime());
        holder.txtPeriod.setText(item.getContext().getString(R.string.period_short, lesson.getPeriod()));
        holder.txtClass.setText(lesson.getSchoolClass().getName());
        holder.txtSubject.setText(lesson.getSubject().name(khmer));

        if (lesson.isDone() && lesson.getSession() != null) {
            holder.dot.setBackgroundResource(R.drawable.bg_dot_done);
            holder.txtStatus.setText(lesson.getSession().getPresent() + "/" + lesson.getSession().getTotal());
            holder.txtStatus.setBackgroundResource(R.drawable.bg_chip_present);
            holder.txtStatus.setTextColor(ContextCompat.getColor(item.getContext(), R.color.present_text));
        } else if (lesson.getSlotId() == nextSlotId) {
            holder.dot.setBackgroundResource(R.drawable.bg_dot_next);
            holder.txtStatus.setText(R.string.up_next);
            holder.txtStatus.setBackgroundResource(R.drawable.bg_chip_brand);
            holder.txtStatus.setTextColor(ContextCompat.getColor(item.getContext(), R.color.brand));
        } else {
            holder.dot.setBackgroundResource(R.drawable.bg_dot_later);
            holder.txtStatus.setText(R.string.later);
            holder.txtStatus.setBackground(null);
            holder.txtStatus.setTextColor(ContextCompat.getColor(item.getContext(), R.color.text_muted));
        }

        holder.divider.setVisibility(position == lessons.size() - 1 ? View.GONE : View.VISIBLE);
        holder.row.setOnClickListener(v -> listener.onClick(lesson));
    }

    @Override
    public int getItemCount() {
        return lessons.size();
    }

    static class LessonViewHolder extends RecyclerView.ViewHolder {
        final View row, dot, divider;
        final TextView txtStart, txtPeriod, txtClass, txtSubject, txtStatus;

        LessonViewHolder(@NonNull View view) {
            super(view);
            row = view.findViewById(R.id.rowLesson);
            dot = view.findViewById(R.id.dot);
            divider = view.findViewById(R.id.divider);
            txtStart = view.findViewById(R.id.txtStart);
            txtPeriod = view.findViewById(R.id.txtPeriod);
            txtClass = view.findViewById(R.id.txtClass);
            txtSubject = view.findViewById(R.id.txtSubject);
            txtStatus = view.findViewById(R.id.txtStatus);
        }
    }
}
