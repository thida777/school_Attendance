package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

import java.util.ArrayList;
import java.util.List;

/** GET /attendance/roster?slot_id=… : the lesson and its students (with the saved marks when already taken). */
public class RosterResponse {

    private RosterLesson lesson;

    @SerializedName("already_taken")
    private boolean alreadyTaken;

    @SerializedName("absence_reasons")
    private List<String> absenceReasons;

    private List<Student> students;

    public RosterLesson getLesson() { return lesson; }
    public boolean isAlreadyTaken() { return alreadyTaken; }

    public List<String> getAbsenceReasons() {
        return absenceReasons == null ? new ArrayList<>() : absenceReasons;
    }

    public List<Student> getStudents() {
        return students == null ? new ArrayList<>() : students;
    }
}
