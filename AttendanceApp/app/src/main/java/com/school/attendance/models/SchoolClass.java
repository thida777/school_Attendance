package com.school.attendance.models;

import com.google.gson.annotations.SerializedName;

import java.util.List;

/** A class the teacher teaches (GET /classes). */
public class SchoolClass {

    private int id;
    private String name;
    private int grade;
    private String room;

    @SerializedName("is_homeroom")
    private boolean homeroom;

    private int students;

    /** Null when no attendance was taken yet. */
    @SerializedName("attendance_rate")
    private Double attendanceRate;

    private List<Subject> subjects;

    public int getId() { return id; }
    public String getName() { return name; }
    public int getGrade() { return grade; }
    public String getRoom() { return room; }
    public boolean isHomeroom() { return homeroom; }
    public int getStudents() { return students; }
    public Double getAttendanceRate() { return attendanceRate; }
    public List<Subject> getSubjects() { return subjects; }
}
