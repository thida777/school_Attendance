package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

/** One student's mark in one lesson (GET /history). */
public class HistoryRecord {

    private int id;
    private String status;

    @SerializedName("absence_reason")
    private String absenceReason;

    private String date;
    private int period;

    @SerializedName("class")
    private ClassRef schoolClass;

    private Subject subject;
    private Student student;

    /** True within 24 hours of the lesson: a change is saved at once. */
    @SerializedName("can_change_directly")
    private boolean canChangeDirectly;

    /** A change request is waiting for the admin. */
    @SerializedName("has_pending_request")
    private boolean hasPendingRequest;

    public int getId() { return id; }
    public String getStatus() { return status; }
    public String getAbsenceReason() { return absenceReason; }
    public String getDate() { return date; }
    public int getPeriod() { return period; }
    public ClassRef getSchoolClass() { return schoolClass; }
    public Subject getSubject() { return subject; }
    public Student getStudent() { return student; }
    public boolean canChangeDirectly() { return canChangeDirectly; }
    public boolean hasPendingRequest() { return hasPendingRequest; }

    public boolean isAbsent() {
        return Student.ABSENT.equals(status);
    }

    /** Records of the same lesson share this key (used to show one heading per lesson). */
    public String lessonKey() {
        return date + "|" + period + "|" + schoolClass.getId() + "|" + subject.getId();
    }
}
