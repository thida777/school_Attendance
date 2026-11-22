package com.school.attendance.models;

/** A lesson that was already marked: {"id": 1, "present": 6, "absent": 1}. */
public class SessionSummary {

    private int id;
    private int present;
    private int absent;

    public int getId() { return id; }
    public int getPresent() { return present; }
    public int getAbsent() { return absent; }
    public int getTotal() { return present + absent; }
}
