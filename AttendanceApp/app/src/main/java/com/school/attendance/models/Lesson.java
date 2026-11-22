package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

/** One lesson of today's timetable (GET /today). */
public class Lesson {

    public static final String DONE = "done";

    @SerializedName("slot_id")
    private int slotId;

    private int period;

    @SerializedName("start_time")
    private String startTime;

    @SerializedName("end_time")
    private String endTime;

    @SerializedName("class")
    private ClassRef schoolClass;

    private Subject subject;
    private int students;

    /** "done" or "not_marked". */
    private String status;

    /** Null until attendance is taken. */
    private SessionSummary session;

    public int getSlotId() { return slotId; }
    public int getPeriod() { return period; }
    public String getStartTime() { return startTime; }
    public String getEndTime() { return endTime; }
    public ClassRef getSchoolClass() { return schoolClass; }
    public Subject getSubject() { return subject; }
    public int getStudents() { return students; }
    public SessionSummary getSession() { return session; }

    public boolean isDone() {
        return DONE.equals(status);
    }
}
