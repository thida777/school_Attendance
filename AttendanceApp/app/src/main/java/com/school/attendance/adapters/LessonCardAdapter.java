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
import com.school.attendance.models.Lesson;

import java.util.ArrayList;
import java.util.List;

/**
 * Today's lessons as cards on the Attendance tab (step 1).
 * Done → green "Done · 6/7" and an Edit button; Up next → teal border, teal Start; the rest → grey "Not marked".
 */
public class LessonCardAdapter extends RecyclerView.Adapter<LessonCardAdapter.CardViewHolder> {

    private final List<Lesson> lessons = new ArrayList<>();
    private final boolean khmer;
    private final LessonAdapter.OnLessonClick listener;
    private int nextSlotId = -1;

    public LessonCardAdapter(boolean khmer, LessonAdapter.OnLessonClick listener) {
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
    public CardViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        return new CardViewHolder(LayoutInflater.from(parent.getContext()).inflate(R.layout.item_lesson_card, parent, false));
    }

    @Override
    public void onBindViewHolder(@NonNull CardViewHolder h, int position) {
        Lesson lesson = lessons.get(position);
        Context c = h.itemView.getContext();
        boolean done = lesson.isDone() && lesson.getSession() != null;
        boolean next = lesson.getSlotId() == nextSlotId;

        h.txtTime.setText(c.getString(R.string.period_time, lesson.getPeriod(), lesson.getStartTime(), lesson.getEndTime()));
        h.txtClass.setText(lesson.getSchoolClass().getName());
        h.txtSubject.setText(c.getString(R.string.subject_students, lesson.getSubject().name(khmer), lesson.getStudents()));
        h.card.setBackgroundResource(next ? R.drawable.bg_card_next : R.drawable.bg_card);

        if (done) {
            h.txtStatus.setText(c.getString(R.string.done_present, lesson.getSession().getPresent(), lesson.getSession().getTotal()));
            h.txtStatus.setBackgroundResource(R.drawable.bg_chip_present);
            h.txtStatus.setTextColor(ContextCompat.getColor(c, R.color.present_text));
            styleButton(h.btnAction, R.string.edit, R.color.field, R.color.text_main);
        } else if (next) {
            h.txtStatus.setText(R.string.up_next);
            h.txtStatus.setBackgroundResource(R.drawable.bg_chip_brand);
            h.txtStatus.setTextColor(ContextCompat.getColor(c, R.color.brand));
            styleButton(h.btnAction, R.string.start, R.color.brand, R.color.on_brand);
        } else {
            h.txtStatus.setText(R.string.not_marked);
            h.txtStatus.setBackgroundResource(R.drawable.bg_chip_grey);
            h.txtStatus.setTextColor(ContextCompat.getColor(c, R.color.text_muted));
            styleButton(h.btnAction, R.string.start, R.color.brand_soft, R.color.brand);
        }

        h.btnAction.setOnClickListener(v -> listener.onClick(lesson));
        h.card.setOnClickListener(v -> listener.onClick(lesson));
    }

    private static void styleButton(MaterialButton button, int text, int background, int textColor) {
        Context c = button.getContext();
        button.setText(text);
        button.setBackgroundTintList(ColorStateList.valueOf(ContextCompat.getColor(c, background)));
        button.setTextColor(ContextCompat.getColor(c, textColor));
    }

    @Override
    public int getItemCount() {
        return lessons.size();
    }

    static class CardViewHolder extends RecyclerView.ViewHolder {
        final View card;
        final TextView txtTime, txtStatus, txtClass, txtSubject;
        final MaterialButton btnAction;

        CardViewHolder(@NonNull View view) {
            super(view);
            card = view.findViewById(R.id.cardLesson);
            txtTime = view.findViewById(R.id.txtTime);
            txtStatus = view.findViewById(R.id.txtStatus);
            txtClass = view.findViewById(R.id.txtClass);
            txtSubject = view.findViewById(R.id.txtSubject);
            btnAction = view.findViewById(R.id.btnAction);
        }
    }
}
