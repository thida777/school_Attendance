package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

/** The lesson being marked: class, subject, date, period and time. */
public class RosterLesson {

    @SerializedName("class")
    private ClassRef schoolClass;

    private Subject subject;
    private String date;
    private int period;

    @SerializedName("start_time")
    private String startTime;

    @SerializedName("end_time")
    private String endTime;

    public ClassRef getSchoolClass() { return schoolClass; }
    public Subject getSubject() { return subject; }
    public String getDate() { return date; }
    public int getPeriod() { return period; }
    public String getStartTime() { return startTime; }
    public String getEndTime() { return endTime; }
}
