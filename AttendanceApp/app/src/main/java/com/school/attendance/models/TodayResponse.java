package com.school.attendance.models;

import java.util.ArrayList;
import java.util.List;

/** GET /today: {"date": "2026-11-17", "done": 1, "total": 5, "lessons": [...]}. */
public class TodayResponse {

    private String date;
    private int done;
    private int total;
    private List<Lesson> lessons;

    public String getDate() { return date; }
    public int getDone() { return done; }
    public int getTotal() { return total; }

    public List<Lesson> getLessons() {
        return lessons == null ? new ArrayList<>() : lessons;
    }

    /** The first lesson not marked yet ("Up next"), or null when all are done. */
    public Lesson getNext() {
        for (Lesson lesson : getLessons()) {
            if (!lesson.isDone()) return lesson;
        }
        return null;
    }

    /** Students marked absent in all of today's lessons. */
    public int getAbsentToday() {
        int absent = 0;
        for (Lesson lesson : getLessons()) {
            if (lesson.getSession() != null) absent += lesson.getSession().getAbsent();
        }
        return absent;
    }
}
